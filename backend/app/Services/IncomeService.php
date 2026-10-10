<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Income;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class IncomeService
{
    public function __construct(
        private LedgerService $ledger,
        private SequenceService $sequences,
        private PeriodLockService $periods,
        private AttachmentService $attachments,
    ) {}

    public function create(array $data, User $user, ?UploadedFile $file = null): Income
    {
        $this->periods->assertOpen($data['date']);

        return DB::transaction(function () use ($data, $user, $file) {
            $custodian = $this->ledger->lockCustodians([$data['custodian_id']])->first();

            $income = Income::create([
                'receipt_no' => $this->sequences->next('IN', (int) substr($data['date'], 0, 4)),
                'date' => $data['date'],
                'income_source_id' => $data['income_source_id'],
                'payment_method_id' => $data['payment_method_id'],
                'amount' => Money::decimal(Money::cents($data['amount'])),
                'custodian_id' => $custodian->id,
                'payer_name' => $data['payer_name'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
            ]);

            $income->load('source', 'paymentMethod');

            $this->ledger->post(
                $custodian,
                LedgerEntry::INCOME,
                Money::cents($income->amount),
                $income->date->toDateString(),
                "إيراد: {$income->source->name} ({$income->paymentMethod->name}) — إيصال {$income->receipt_no}",
                $income,
                $user->id,
            );

            if ($file) {
                $this->attachments->store($income, $file, 'receipt', $user->id);
            }

            return $income;
        });
    }

    public function reverse(Income $income, string $reason, User $user): Income
    {
        $today = now()->toDateString();
        $this->periods->assertOpen($today);

        return DB::transaction(function () use ($income, $reason, $user, $today) {
            $income = Income::lockForUpdate()->findOrFail($income->id);

            if ($income->isReversed()) {
                throw new BusinessRuleException('الإيراد ده اتلغى قبل كده.');
            }

            $custodian = $this->ledger->lockCustodians([$income->custodian_id])->first();
            $amount = Money::cents($income->amount);
            $this->ledger->assertAvailable($custodian, $amount);

            $this->ledger->post(
                $custodian,
                LedgerEntry::REVERSAL,
                -$amount,
                $today,
                "قيد عكسي لإيصال {$income->receipt_no} — {$reason}",
                $income,
                $user->id,
            );

            $income->forceFill([
                'reversed_at' => now(),
                'reversed_by' => $user->id,
                'reversal_reason' => $reason,
            ])->save();

            return $income;
        });
    }
}
