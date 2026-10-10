<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\LedgerEntry;
use App\Models\Transfer;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class TransferService
{
    public function __construct(
        private LedgerService $ledger,
        private SequenceService $sequences,
        private PeriodLockService $periods,
    ) {}

    public function create(array $data, User $user): Transfer
    {
        if ((int) $data['from_custodian_id'] === (int) $data['to_custodian_id']) {
            throw new BusinessRuleException('مينفعش تسلّم فلوس لنفس الشخص.');
        }

        $this->periods->assertOpen($data['date']);

        return DB::transaction(function () use ($data, $user) {
            $locked = $this->ledger->lockCustodians([$data['from_custodian_id'], $data['to_custodian_id']]);
            $from = $locked[(int) $data['from_custodian_id']];
            $to = $locked[(int) $data['to_custodian_id']];
            $amount = Money::cents($data['amount']);

            $this->ledger->assertAvailable($from, $amount);

            $transfer = Transfer::create([
                'number' => $this->sequences->next('TR', (int) substr($data['date'], 0, 4)),
                'date' => $data['date'],
                'from_custodian_id' => $from->id,
                'to_custodian_id' => $to->id,
                'amount' => Money::decimal($amount),
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
            ]);

            $this->ledger->post($from, LedgerEntry::TRANSFER_OUT, -$amount, $data['date'], "تسليم فلوس إلى {$to->name}", $transfer, $user->id);
            $this->ledger->post($to, LedgerEntry::TRANSFER_IN, $amount, $data['date'], "استلام فلوس من {$from->name}", $transfer, $user->id);

            return $transfer;
        });
    }

    public function reverse(Transfer $transfer, string $reason, User $user): Transfer
    {
        $today = now()->toDateString();
        $this->periods->assertOpen($today);

        return DB::transaction(function () use ($transfer, $reason, $user, $today) {
            $transfer = Transfer::lockForUpdate()->findOrFail($transfer->id);

            if ($transfer->isReversed()) {
                throw new BusinessRuleException('التسليم ده اتلغى قبل كده.');
            }

            $locked = $this->ledger->lockCustodians([$transfer->from_custodian_id, $transfer->to_custodian_id]);
            $from = $locked[$transfer->from_custodian_id];
            $to = $locked[$transfer->to_custodian_id];
            $amount = Money::cents($transfer->amount);

            $this->ledger->assertAvailable($to, $amount);

            $this->ledger->post($to, LedgerEntry::REVERSAL, -$amount, $today, "قيد عكسي لتسليم {$transfer->number} — {$reason}", $transfer, $user->id);
            $this->ledger->post($from, LedgerEntry::REVERSAL, $amount, $today, "قيد عكسي لتسليم {$transfer->number} — {$reason}", $transfer, $user->id);

            $transfer->forceFill([
                'reversed_at' => now(),
                'reversed_by' => $user->id,
                'reversal_reason' => $reason,
            ])->save();

            return $transfer;
        });
    }
}
