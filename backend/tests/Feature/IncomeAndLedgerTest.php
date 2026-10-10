<?php

namespace Tests\Feature;

use App\Models\Income;
use App\Models\LedgerEntry;
use App\Models\PeriodLock;

class IncomeAndLedgerTest extends FinanceTestCase
{
    public function test_income_adds_to_custodian_balance_through_ledger(): void
    {
        $this->addIncome($this->mahmoud, 1500)->assertCreated()->assertJsonPath('data.receipt_no', 'IN-'.now()->year.'-00001');
        $this->addIncome($this->mahmoud, 250.50)->assertCreated()->assertJsonPath('data.receipt_no', 'IN-'.now()->year.'-00002');

        $this->assertSame(1750.5, $this->balance($this->mahmoud));
        $this->assertEquals(1750.5, LedgerEntry::where('custodian_id', $this->mahmoud->id)->sum('amount'));
        $this->assertSame(1750.5, (float) $this->mahmoud->fresh()->balance_cache);
    }

    public function test_reversal_keeps_original_and_posts_negative_entry(): void
    {
        $id = $this->addIncome($this->mahmoud, 1000)->json('data.id');

        $this->actingAsUser($this->imam)->postJson("/api/incomes/{$id}/reverse", ['reason' => 'اتسجل بالغلط'])
            ->assertOk()->assertJsonPath('data.is_reversed', true);

        $this->assertSame(0.0, $this->balance($this->mahmoud));
        $this->assertNotNull(Income::find($id), 'العملية الأصلية لازم تفضل موجودة');
        $this->assertSame(1, LedgerEntry::where('type', LedgerEntry::REVERSAL)->count());

        $this->actingAsUser($this->imam)->postJson("/api/incomes/{$id}/reverse", ['reason' => 'تاني'])->assertStatus(422);
    }

    public function test_reversal_requires_reason_and_permission(): void
    {
        $id = $this->addIncome($this->mahmoud, 1000)->json('data.id');

        $this->actingAsUser($this->imam)->postJson("/api/incomes/{$id}/reverse", [])->assertJsonValidationErrors('reason');
        $this->actingAsUser($this->treasurer)->postJson("/api/incomes/{$id}/reverse", ['reason' => 'غلط'])->assertForbidden();
    }

    public function test_cannot_record_in_locked_period(): void
    {
        $lastMonth = now()->subMonthNoOverflow()->startOfMonth();
        PeriodLock::create(['period' => $lastMonth->toDateString(), 'locked_by' => $this->imam->id]);

        $this->addIncome($this->mahmoud, 500, $lastMonth->copy()->addDays(3)->toDateString())
            ->assertStatus(422);
        $this->assertSame(0, Income::count());

        $this->addIncome($this->mahmoud, 500)->assertCreated();
    }

    public function test_period_lock_only_for_allowed_roles(): void
    {
        $period = now()->subMonthNoOverflow()->format('Y-m');

        $this->actingAsUser($this->treasurer)->postJson('/api/period-locks', ['period' => $period])->assertForbidden();
        $this->actingAsUser($this->accountant)->postJson('/api/period-locks', ['period' => $period])->assertCreated();
        $this->actingAsUser($this->imam)->postJson('/api/period-locks', ['period' => now()->format('Y-m')])->assertStatus(422);
    }

    public function test_income_validation_messages_are_arabic(): void
    {
        $this->actingAsUser($this->treasurer)->postJson('/api/incomes', ['amount' => -5])
            ->assertStatus(422)
            ->assertJsonPath('errors.amount.0', 'اكتب مبلغ أكبر من صفر.')
            ->assertJsonPath('errors.custodian_id.0', 'اكتب الشخص.');
    }

    public function test_receipt_pdf_is_generated(): void
    {
        $id = $this->addIncome($this->mahmoud, 2300)->json('data.id');

        $response = $this->actingAsUser($this->treasurer)->get("/api/incomes/{$id}/receipt");
        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_login_by_phone(): void
    {
        $this->postJson('/api/auth/login', ['phone' => $this->imam->phone, 'password' => 'password'])
            ->assertOk()->assertJsonPath('user.is_imam', true);

        foreach (range(1, 5) as $i) {
            $this->postJson('/api/auth/login', ['phone' => $this->imam->phone, 'password' => 'wrong'])
                ->assertStatus(422)->assertJsonValidationErrors('phone');
        }

        // تسجيل الدخول الصحيح ينجح دائماً بدون قفل المحاولات
        $this->postJson('/api/auth/login', ['phone' => $this->imam->phone, 'password' => 'password'])
            ->assertOk()->assertJsonPath('user.is_imam', true);
    }
}
