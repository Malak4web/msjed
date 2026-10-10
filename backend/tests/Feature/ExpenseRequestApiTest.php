<?php

namespace Tests\Feature;

use App\Models\ExpenseRequest;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

class ExpenseRequestApiTest extends FinanceTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->addIncome($this->sayed, 1000);
    }

    private function createRequest(float $amount = 600, ?User $by = null)
    {
        return $this->actingAsUser($by ?? $this->treasurer)->postJson('/api/expense-requests', [
            'custodian_id' => $this->sayed->id,
            'expense_category_id' => $this->category()->id,
            'amount' => $amount,
            'description' => 'أدوات نظافة للميضأة',
        ]);
    }

    public function test_full_cycle_create_approve_execute_with_invoice(): void
    {
        Notification::fake();

        $id = $this->createRequest()->assertCreated()->assertJsonPath('data.status', 'pending')->json('data.id');
        Notification::assertSentTo($this->imam, \App\Notifications\ExpenseRequestNotification::class);

        $this->actingAsUser($this->treasurer)->postJson("/api/expense-requests/{$id}/approve")->assertForbidden();
        $this->actingAsUser($this->accountant)->postJson("/api/expense-requests/{$id}/approve")->assertForbidden();
        $this->actingAsUser($this->imam)->postJson("/api/expense-requests/{$id}/approve")
            ->assertOk()->assertJsonPath('data.status', 'approved')->assertJsonPath('data.custodian.available', '400.00');
        Notification::assertSentTo($this->treasurer, \App\Notifications\ExpenseRequestNotification::class);

        // الفاتورة مطلوبة افتراضيًا
        $this->actingAsUser($this->treasurer)->postJson("/api/expense-requests/{$id}/execute", [
            'actual_amount' => 580, 'executed_date' => now()->toDateString(),
        ])->assertStatus(422);

        $this->actingAsUser($this->treasurer)->post("/api/expense-requests/{$id}/execute", [
            'actual_amount' => 580,
            'executed_date' => now()->toDateString(),
            'invoice' => UploadedFile::fake()->image('invoice.jpg', 2400, 3200),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.status', 'executed')
            ->assertJsonPath('data.actual_amount', '580.00')
            ->assertJsonCount(3, 'data.timeline'); // أنشئ ← اتوافق ← اتنفذ

        $this->assertSame(420.0, $this->balance($this->sayed));

        $attachment = ExpenseRequest::find($id)->attachments()->first();
        $this->assertSame('image/jpeg', $attachment->mime);
        $this->get($attachment->signedUrl())->assertOk();
        $this->get("/api/attachments/{$attachment->id}")->assertForbidden();
    }

    public function test_over_balance_request_rejected_with_arabic_message(): void
    {
        $this->createRequest(1500)->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'الرصيد المتاح'));
    }

    public function test_reject_requires_reason(): void
    {
        $id = $this->createRequest()->json('data.id');

        $this->actingAsUser($this->imam)->postJson("/api/expense-requests/{$id}/reject", [])->assertJsonValidationErrors('reason');
        $this->actingAsUser($this->imam)->postJson("/api/expense-requests/{$id}/reject", ['reason' => 'مش ضروري دلوقتي'])
            ->assertOk()->assertJsonPath('data.status', 'rejected');
    }

    public function test_returned_request_can_be_edited_and_resubmitted(): void
    {
        $id = $this->createRequest()->json('data.id');
        $this->actingAsUser($this->imam)->postJson("/api/expense-requests/{$id}/return", ['reason' => 'محتاج عرض سعر'])->assertOk();

        $this->actingAsUser($this->treasurer)->postJson("/api/expense-requests/{$id}/update", ['amount' => 500])
            ->assertOk()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.amount', '500.00');
    }

    public function test_transfer_endpoint_and_dashboard(): void
    {
        $this->actingAsUser($this->treasurer)->postJson('/api/transfers', [
            'from_custodian_id' => $this->sayed->id, 'to_custodian_id' => $this->sayed->id, 'amount' => 10, 'date' => now()->toDateString(),
        ])->assertJsonValidationErrors('to_custodian_id');

        $this->actingAsUser($this->treasurer)->postJson('/api/transfers', [
            'from_custodian_id' => $this->sayed->id, 'to_custodian_id' => $this->mahmoud->id, 'amount' => 300, 'date' => now()->toDateString(),
        ])->assertCreated();

        $this->createRequest(200);
        $this->actingAsUser($this->imam)->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('pending_count', 1)
            ->assertJsonPath('month.income', '1000.00')
            ->assertJsonCount(6, 'chart');
    }
}
