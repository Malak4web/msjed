<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Custodian;
use App\Models\ExpenseRequest;
use App\Models\LedgerEntry;
use App\Models\Transfer;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * دفتر الحركات هو مصدر الحقيقة: رصيد الشخص = مجموع حركاته.
 * كل الدوال اللي بتكتب لازم تتنادى جوه DB::transaction.
 */
class LedgerService
{
    /**
     * يقفل صفوف الأشخاص بترتيب ثابت لمنع الـ deadlock.
     *
     * @return Collection<int, Custodian>
     */
    public function lockCustodians(array $ids): Collection
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids);

        return Custodian::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
    }

    public function post(
        Custodian $custodian,
        string $type,
        int $amountCents,
        string $date,
        string $description,
        ?Model $related = null,
        ?int $userId = null,
    ): LedgerEntry {
        $entry = LedgerEntry::create([
            'custodian_id' => $custodian->id,
            'type' => $type,
            'amount' => Money::decimal($amountCents),
            'date' => $date,
            'description' => $description,
            'related_type' => $related?->getMorphClass(),
            'related_id' => $related?->getKey(),
            'created_by' => $userId,
        ]);

        $this->refreshCache($custodian);

        return $entry;
    }

    public function balanceCents(int $custodianId): int
    {
        return Money::cents(LedgerEntry::where('custodian_id', $custodianId)->sum('amount'));
    }

    /** المبالغ المحجوزة = طلبات اتوافق عليها ولسه متنفذتش + تحويلات عهدة قيد الانتظار لم يستلمها المستلم */
    public function reservedCents(int $custodianId, ?int $exceptRequestId = null): int
    {
        $expenses = ExpenseRequest::where('custodian_id', $custodianId)
            ->where('status', ExpenseRequest::APPROVED)
            ->when($exceptRequestId, fn ($q) => $q->where('id', '!=', $exceptRequestId))
            ->sum('amount');

        $pendingTransfers = Transfer::where('from_custodian_id', $custodianId)
            ->where('status', Transfer::STATUS_PENDING)
            ->sum('amount');

        return Money::cents($expenses) + Money::cents($pendingTransfers);
    }

    public function availableCents(int $custodianId, ?int $exceptRequestId = null): int
    {
        return $this->balanceCents($custodianId) - $this->reservedCents($custodianId, $exceptRequestId);
    }

    public function assertAvailable(Custodian $custodian, int $amountCents, ?int $exceptRequestId = null): void
    {
        $available = $this->availableCents($custodian->id, $exceptRequestId);

        if ($amountCents > $available) {
            throw new BusinessRuleException(sprintf(
                'المبلغ أكبر من الرصيد المتاح مع %s. المتاح دلوقتي %s بس.',
                $custodian->name,
                Money::format(Money::decimal(max($available, 0)))
            ));
        }
    }

    public function refreshCache(Custodian $custodian): void
    {
        $custodian->forceFill(['balance_cache' => Money::decimal($this->balanceCents($custodian->id))])->saveQuietly();
    }

    /** ملخّص رصيد شخص للعرض */
    public function summary(Custodian $custodian): array
    {
        $balance = $this->balanceCents($custodian->id);
        $reserved = $this->reservedCents($custodian->id);

        return [
            'balance' => Money::decimal($balance),
            'reserved' => Money::decimal($reserved),
            'available' => Money::decimal($balance - $reserved),
        ];
    }
}
