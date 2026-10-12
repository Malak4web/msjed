<?php

namespace Tests\Feature;

use App\Models\Custodian;
use App\Models\LedgerEntry;
use App\Models\Transfer;
use App\Models\User;
use App\Services\LedgerService;

class TransferAcceptanceTest extends FinanceTestCase
{
    private Custodian $fromC;
    private Custodian $toC;
    private User $recipientUser;
    private User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fromC = $this->mahmoud;
        $this->toC = Custodian::create(['name' => 'الأستاذ خالد', 'title' => 'المحاسب']);

        $this->recipientUser = $this->user(User::ROLE_ACCOUNTANT, $this->toC);
        $this->otherUser = $this->user(User::ROLE_ACCOUNTANT); // مستخدم بدون عهدة

        // إيداع مبدئي للراسل
        $this->addIncome($this->fromC, 1000);
    }

    public function test_transfer_creation_leaves_status_pending_and_reserves_amount(): void
    {
        $ledger = app(LedgerService::class);

        $res = $this->actingAsUser($this->treasurer)->postJson('/api/transfers', [
            'from_custodian_id' => $this->fromC->id,
            'to_custodian_id' => $this->toC->id,
            'amount' => 400,
            'date' => now()->toDateString(),
            'notes' => 'تحويل للتجربة',
        ])->assertCreated();

        $transferId = $res->json('data.id');
        $this->assertSame(Transfer::STATUS_PENDING, $res->json('data.status'));

        // الرصيد الفعلي لم يتغير بعد
        $this->assertSame(100000, $ledger->balanceCents($this->fromC->id));
        $this->assertSame(0, $ledger->balanceCents($this->toC->id));

        // لكن الرصيد المتاح تم حجزه
        $this->assertSame(40000, $ledger->reservedCents($this->fromC->id));
        $this->assertSame(60000, $ledger->availableCents($this->fromC->id));

        // لا توجد قيود تحويل في الدفتر بعد
        $this->assertSame(0, LedgerEntry::whereIn('type', [LedgerEntry::TRANSFER_OUT, LedgerEntry::TRANSFER_IN])->count());
    }

    public function test_only_recipient_or_imam_can_accept_transfer(): void
    {
        $ledger = app(LedgerService::class);

        $res = $this->actingAsUser($this->treasurer)->postJson('/api/transfers', [
            'from_custodian_id' => $this->fromC->id,
            'to_custodian_id' => $this->toC->id,
            'amount' => 300,
            'date' => now()->toDateString(),
        ])->assertCreated();

        $transferId = $res->json('data.id');

        // مستخدم غير معني لا يمكنه قبول التحويل
        $this->actingAsUser($this->otherUser)->postJson("/api/transfers/{$transferId}/accept")
            ->assertStatus(422);

        // المستلم نفسه يمكنه قبول التحويل
        $acceptRes = $this->actingAsUser($this->recipientUser)->postJson("/api/transfers/{$transferId}/accept")
            ->assertOk();

        $acceptRes->assertJsonPath('data.status', Transfer::STATUS_COMPLETED);

        // تم ترحيل المبلغ في الدفتر
        $this->assertSame(70000, $ledger->balanceCents($this->fromC->id));
        $this->assertSame(30000, $ledger->balanceCents($this->toC->id));
        $this->assertSame(0, $ledger->reservedCents($this->fromC->id));

        $this->assertSame(1, LedgerEntry::where('type', LedgerEntry::TRANSFER_OUT)->count());
        $this->assertSame(1, LedgerEntry::where('type', LedgerEntry::TRANSFER_IN)->count());
    }

    public function test_imam_can_accept_on_behalf_of_custodian_without_account(): void
    {
        $ledger = app(LedgerService::class);

        // التحويل لعم سيد (ليس له حساب مستخدم)
        $res = $this->actingAsUser($this->treasurer)->postJson('/api/transfers', [
            'from_custodian_id' => $this->fromC->id,
            'to_custodian_id' => $this->sayed->id,
            'amount' => 200,
            'date' => now()->toDateString(),
        ])->assertCreated();

        $transferId = $res->json('data.id');

        $this->actingAsUser($this->imam)->postJson("/api/transfers/{$transferId}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', Transfer::STATUS_COMPLETED);

        $this->assertSame(80000, $ledger->balanceCents($this->fromC->id));
        $this->assertSame(20000, $ledger->balanceCents($this->sayed->id));
    }

    public function test_recipient_can_reject_transfer_and_release_reservation(): void
    {
        $ledger = app(LedgerService::class);

        $res = $this->actingAsUser($this->treasurer)->postJson('/api/transfers', [
            'from_custodian_id' => $this->fromC->id,
            'to_custodian_id' => $this->toC->id,
            'amount' => 500,
            'date' => now()->toDateString(),
        ])->assertCreated();

        $transferId = $res->json('data.id');

        $this->actingAsUser($this->recipientUser)->postJson("/api/transfers/{$transferId}/reject", [
            'reason' => 'لم أستلم المبلغ يدويًا',
        ])->assertOk()->assertJsonPath('data.status', Transfer::STATUS_REJECTED);

        // تم فك الحجز وبقي الرصيد بالكامل لدى الراسل
        $this->assertSame(100000, $ledger->balanceCents($this->fromC->id));
        $this->assertSame(0, $ledger->reservedCents($this->fromC->id));
        $this->assertSame(100000, $ledger->availableCents($this->fromC->id));
        $this->assertSame(0, $ledger->balanceCents($this->toC->id));
    }

    public function test_creator_can_cancel_pending_transfer(): void
    {
        $ledger = app(LedgerService::class);

        $res = $this->actingAsUser($this->treasurer)->postJson('/api/transfers', [
            'from_custodian_id' => $this->fromC->id,
            'to_custodian_id' => $this->toC->id,
            'amount' => 250,
            'date' => now()->toDateString(),
        ])->assertCreated();

        $transferId = $res->json('data.id');

        // الراسل يلغي التحويل
        $this->actingAsUser($this->treasurer)->postJson("/api/transfers/{$transferId}/cancel", [
            'reason' => 'تم التراجع عن التسليم',
        ])->assertOk()->assertJsonPath('data.status', Transfer::STATUS_CANCELLED);

        $this->assertSame(0, $ledger->reservedCents($this->fromC->id));
        $this->assertSame(100000, $ledger->availableCents($this->fromC->id));
    }

    public function test_cannot_reverse_pending_transfer(): void
    {
        $res = $this->actingAsUser($this->treasurer)->postJson('/api/transfers', [
            'from_custodian_id' => $this->fromC->id,
            'to_custodian_id' => $this->toC->id,
            'amount' => 100,
            'date' => now()->toDateString(),
        ])->assertCreated();

        $transferId = $res->json('data.id');

        // محاولة القيد العكسي قبل الاكتمال مرفوضة
        $this->actingAsUser($this->imam)->postJson("/api/transfers/{$transferId}/reverse", [
            'reason' => 'قيد عكسي',
        ])->assertStatus(422);
    }

    public function test_transfer_with_payment_method(): void
    {
        $instapay = \App\Models\PaymentMethod::firstOrCreate(['name' => 'إنستاباي'], ['icon' => 'bolt', 'sort' => 2]);

        $res = $this->actingAsUser($this->treasurer)->postJson('/api/transfers', [
            'from_custodian_id' => $this->fromC->id,
            'to_custodian_id' => $this->toC->id,
            'amount' => 150,
            'payment_method_id' => $instapay->id,
            'date' => now()->toDateString(),
        ])->assertCreated();

        $res->assertJsonPath('data.payment_method.id', $instapay->id)
            ->assertJsonPath('data.payment_method.name', 'إنستاباي');

        $transferId = $res->json('data.id');
        $this->actingAsUser($this->recipientUser)->postJson("/api/transfers/{$transferId}/accept")
            ->assertOk()
            ->assertJsonPath('data.payment_method.name', 'إنستاباي');

        $outEntry = LedgerEntry::where('type', LedgerEntry::TRANSFER_OUT)->first();
        $this->assertStringContainsString('إنستاباي', $outEntry->description);
    }
}
