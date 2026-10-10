<?php

namespace Tests\Feature;

use App\Models\Custodian;
use App\Models\ExpenseCategory;
use App\Models\IncomeSource;
use App\Models\PaymentMethod;
use App\Models\User;
use Database\Seeders\LookupsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

abstract class FinanceTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $imam;
    protected User $treasurer;
    protected User $accountant;
    protected Custodian $mahmoud;
    protected Custodian $sayed;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, LookupsSeeder::class]);

        $this->mahmoud = Custodian::create(['name' => 'الحاج محمود', 'title' => 'أمين الصندوق']);
        $this->sayed = Custodian::create(['name' => 'عم سيد', 'title' => 'عامل المسجد']);
        $imamC = Custodian::create(['name' => 'الشيخ أحمد']);

        $this->imam = $this->user(User::ROLE_IMAM, $imamC);
        $this->treasurer = $this->user(User::ROLE_TREASURER, $this->mahmoud);
        $this->accountant = $this->user(User::ROLE_ACCOUNTANT);
    }

    protected function user(string $role, ?Custodian $custodian = null): User
    {
        $user = User::factory()->create(['custodian_id' => $custodian?->id]);
        $user->assignRole($role);

        return $user;
    }

    protected function actingAsUser(User $user): static
    {
        Sanctum::actingAs($user);

        return $this;
    }

    protected function addIncome(Custodian $custodian, float $amount, ?string $date = null)
    {
        return $this->actingAsUser($this->treasurer)->postJson('/api/incomes', [
            'date' => $date ?? now()->toDateString(),
            'income_source_id' => IncomeSource::first()->id,
            'payment_method_id' => PaymentMethod::first()->id,
            'amount' => $amount,
            'custodian_id' => $custodian->id,
        ]);
    }

    protected function balance(Custodian $custodian): float
    {
        return (float) $this->actingAsUser($this->treasurer)->getJson("/api/custodians/{$custodian->id}")->json('data.balance');
    }

    protected function category(string $type = 'variable'): ExpenseCategory
    {
        return ExpenseCategory::where('type', $type)->first();
    }
}
