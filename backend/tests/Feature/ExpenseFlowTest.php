<?php

namespace Tests\Feature;

use App\Exceptions\BusinessRuleException;
use App\Models\ExpenseRequest;
use App\Services\ExpenseRequestService;
use App\Services\LedgerService;
use App\Services\SettingsService;
use App\Services\TransferService;

/**
 * قواعد الصرف على مستوى الـ Services (الـ API بتاعها في المرحلة التانية).
 */
class ExpenseFlowTest extends FinanceTestCase
{
    private ExpenseRequestService $requests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requests = app(ExpenseRequestService::class);
        app(SettingsService::class)->set(['require_invoice_on_execute' => false]);
        $this->addIncome($this->sayed, 1000);
    }

    private function request(float $amount, $by = null): ExpenseRequest
    {
        return $this->requests->create([
            'custodian_id' => $this->sayed->id,
            'expense_category_id' => $this->category()->id,
            'amount' => $amount,
            'description' => 'أدوات نظافة',
        ], $by ?? $this->treasurer);
    }

    public function test_cannot_request_more_than_available_balance(): void
    {
        $this->expectException(BusinessRuleException::class);
        $this->request(1500);
    }

    public function test_only_imam_can_approve(): void
    {
        $request = $this->request(600);

        foreach ([$this->treasurer, $this->accountant] as $user) {
            try {
                $this->requests->approve($request, $user);
                $this->fail('مينفعش غير الإمام يوافق');
            } catch (BusinessRuleException) {
            }
        }

        $this->assertSame(ExpenseRequest::APPROVED, $this->requests->approve($request, $this->imam)->status);
    }

    public function test_approval_reserves_and_execution_deducts(): void
    {
        $ledger = app(LedgerService::class);
        $request = $this->requests->approve($this->request(600), $this->imam);

        $this->assertSame(100000, $ledger->balanceCents($this->sayed->id), 'الموافقة متخصمش');
        $this->assertSame(40000, $ledger->availableCents($this->sayed->id), 'الموافقة بتحجز');

        // الحجز يمنع طلب تاني أكبر من المتاح
        try {
            $this->request(500);
            $this->fail('المبلغ المحجوز لازم يتحسب');
        } catch (BusinessRuleException) {
        }

        $this->requests->execute($request, $this->treasurer, ['actual_amount' => 550]);
        $this->assertSame(45000, $ledger->balanceCents($this->sayed->id));
        $this->assertSame(45000, $ledger->availableCents($this->sayed->id));
    }

    public function test_actual_amount_cannot_exceed_approved(): void
    {
        $request = $this->requests->approve($this->request(300), $this->imam);

        $this->expectException(BusinessRuleException::class);
        $this->requests->execute($request, $this->treasurer, ['actual_amount' => 350]);
    }

    public function test_imam_own_request_is_auto_approved_by_default(): void
    {
        $request = $this->request(200, $this->imam);

        $this->assertSame(ExpenseRequest::APPROVED, $request->status);
        $this->assertTrue($request->auto_approved);
    }

    public function test_imam_own_request_needs_accountant_when_configured(): void
    {
        app(SettingsService::class)->set(['imam_self_request_mode' => 'accountant']);
        $request = $this->request(200, $this->imam);

        $this->assertSame(ExpenseRequest::PENDING, $request->status);
        $this->assertFalse($this->requests->canDecide($request, $this->imam));
        $this->assertSame(ExpenseRequest::APPROVED, $this->requests->approve($request, $this->accountant)->status);
    }

    public function test_reject_and_return_require_reason_and_keep_balance(): void
    {
        $rejected = $this->requests->reject($this->request(100), $this->imam, 'مش محتاجينه');
        $returned = $this->requests->returnForEdit($this->request(100), $this->imam, 'محتاج عرض سعر');

        $this->assertSame(ExpenseRequest::REJECTED, $rejected->status);
        $this->assertSame(ExpenseRequest::RETURNED, $returned->status);
        $this->assertSame(100000, app(LedgerService::class)->balanceCents($this->sayed->id));

        $resubmitted = $this->requests->update($returned, ['amount' => 120], $this->treasurer);
        $this->assertSame(ExpenseRequest::PENDING, $resubmitted->status);
    }

    public function test_transfer_moves_money_and_blocks_overdraft(): void
    {
        $transfers = app(TransferService::class);
        $ledger = app(LedgerService::class);

        $transfer = $transfers->create(['from_custodian_id' => $this->sayed->id, 'to_custodian_id' => $this->mahmoud->id, 'amount' => 400, 'date' => now()->toDateString()], $this->treasurer);

        // قبل التأكيد: المبلغ محجوز من الراسل، ولم يصل لحساب المستلم بعد
        $this->assertSame(100000, $ledger->balanceCents($this->sayed->id));
        $this->assertSame(60000, $ledger->availableCents($this->sayed->id));
        $this->assertSame(0, $ledger->balanceCents($this->mahmoud->id));

        // المستلم (الحاج محمود، مستخدمه أمين الصندوق) يؤكد الاستلام
        $transfers->accept($transfer, $this->treasurer);

        $this->assertSame(60000, $ledger->balanceCents($this->sayed->id));
        $this->assertSame(40000, $ledger->balanceCents($this->mahmoud->id));

        $this->expectException(BusinessRuleException::class);
        $transfers->create(['from_custodian_id' => $this->sayed->id, 'to_custodian_id' => $this->mahmoud->id, 'amount' => 601, 'date' => now()->toDateString()], $this->treasurer);
    }

    public function test_executed_expense_reversal_returns_money(): void
    {
        $request = $this->requests->approve($this->request(300), $this->imam);
        $request = $this->requests->execute($request, $this->treasurer, []);
        $this->requests->reverse($request, $this->imam, 'الفاتورة اترجعت');

        $this->assertSame(100000, app(LedgerService::class)->balanceCents($this->sayed->id));
        $this->assertNotNull($request->fresh()->reversed_at);
    }
}
