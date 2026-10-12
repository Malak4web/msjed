<?php

namespace Tests\Feature;

use App\Models\RecurringExpense;
use App\Models\RecurringExpensePeriod;
use App\Services\SettingsService;
use Illuminate\Support\Carbon;

class RecurringAndReportsTest extends FinanceTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        app(SettingsService::class)->set(['require_invoice_on_execute' => false]);
    }

    private function recurring(bool $preApproved = false, int $dueDay = 5): RecurringExpense
    {
        return RecurringExpense::create([
            'name' => 'فاتورة الكهرباء',
            'expense_category_id' => $this->category('fixed')->id,
            'amount' => 1200,
            'due_day' => $dueDay,
            'responsible_custodian_id' => $this->mahmoud->id,
            'pre_approved' => $preApproved,
        ]);
    }

    public function test_monthly_command_is_idempotent_and_marks_overdue(): void
    {
        Carbon::setTestNow('2026-10-10 09:00');
        $this->recurring(dueDay: 5);
        $this->recurring(dueDay: 20);

        $this->artisan('recurring:run')->assertSuccessful();
        $this->artisan('recurring:run')->assertSuccessful();

        $this->assertSame(2, RecurringExpensePeriod::count());
        $this->assertSame(1, RecurringExpensePeriod::where('status', 'overdue')->count());
        $this->assertSame(1, RecurringExpensePeriod::where('status', 'due')->count());
        Carbon::setTestNow();
    }

    public function test_create_request_from_recurring_goes_to_imam_unless_pre_approved(): void
    {
        $this->addIncome($this->mahmoud, 5000);
        $normal = $this->recurring();
        $pre = $this->recurring(preApproved: true);

        $this->actingAsUser($this->treasurer)->postJson("/api/recurring-expenses/{$normal->id}/create-request")
            ->assertCreated()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.amount', '1200.00');
        $this->actingAsUser($this->treasurer)->postJson("/api/recurring-expenses/{$normal->id}/create-request")
            ->assertStatus(422);
        $this->actingAsUser($this->treasurer)->postJson("/api/recurring-expenses/{$pre->id}/create-request")
            ->assertCreated()->assertJsonPath('data.status', 'approved');

        $list = $this->actingAsUser($this->treasurer)->getJson('/api/recurring-expenses')->assertOk();
        $this->assertSame('1200.00', collect($list->json('data'))->firstWhere('id', $normal->id)['period']['request']['amount']);
    }

    public function test_statement_running_balance_and_exports(): void
    {
        $this->addIncome($this->mahmoud, 1000, now()->subMonthNoOverflow()->startOfMonth()->toDateString());
        $this->addIncome($this->mahmoud, 500);
        $res = $this->actingAsUser($this->treasurer)->postJson('/api/transfers', [
            'from_custodian_id' => $this->mahmoud->id, 'to_custodian_id' => $this->sayed->id, 'amount' => 300, 'date' => now()->toDateString(),
        ])->assertCreated();
        $transferId = $res->json('data.id');
        $this->actingAsUser($this->imam)->postJson("/api/transfers/{$transferId}/accept")->assertOk();

        $from = now()->startOfMonth()->toDateString();
        $s = $this->actingAsUser($this->treasurer)->getJson("/api/custodians/{$this->mahmoud->id}/statement?from={$from}")->assertOk();

        $s->assertJsonPath('opening', '1000.00')
            ->assertJsonPath('total_in', '500.00')
            ->assertJsonPath('total_out', '300.00')
            ->assertJsonPath('closing', '1200.00')
            ->assertJsonPath('rows.1.balance', '1200.00');

        $pdf = $this->actingAsUser($this->treasurer)->get("/api/custodians/{$this->mahmoud->id}/statement?from={$from}&format=pdf");
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $xlsx = $this->actingAsUser($this->treasurer)->get("/api/custodians/{$this->mahmoud->id}/statement?format=xlsx");
        $xlsx->assertOk();
        $this->assertStringStartsWith('PK', $xlsx->getContent());
    }

    public function test_reports_totals_and_permissions(): void
    {
        $this->addIncome($this->mahmoud, 2000);
        $this->addIncome($this->sayed, 500);

        $from = now()->startOfMonth()->toDateString();
        $to = now()->toDateString();

        $this->actingAsUser($this->accountant)->getJson("/api/reports/monthly?from={$from}&to={$to}")
            ->assertOk()->assertJsonPath('totals.income', '2500.00')->assertJsonPath('totals.expense', '0.00');
        $this->actingAsUser($this->accountant)->getJson("/api/reports/balances?from={$from}&to={$to}")
            ->assertOk()->assertJsonPath('totals.closing', '2500.00');
        $this->actingAsUser($this->accountant)->getJson("/api/reports/audit?from={$from}&to={$to}")->assertOk();
        $this->actingAsUser($this->accountant)->getJson('/api/reports/unknown')->assertNotFound();

        foreach (['expenses-by-category', 'incomes-by-source', 'approvals'] as $type) {
            $this->actingAsUser($this->accountant)->get("/api/reports/{$type}?format=xlsx")->assertOk();
        }

        $this->accountant->roles->first()->revokePermissionTo('reports.view');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAsUser($this->accountant->fresh())->getJson('/api/reports/monthly')->assertForbidden();
    }
}
