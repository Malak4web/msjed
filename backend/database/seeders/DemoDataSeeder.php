<?php

namespace Database\Seeders;

use App\Models\Custodian;
use App\Models\ExpenseCategory;
use App\Models\ExpenseRequest;
use App\Models\IncomeSource;
use App\Models\PaymentMethod;
use App\Models\RecurringExpense;
use App\Models\RecurringExpensePeriod;
use App\Models\User;
use App\Services\ExpenseRequestService;
use App\Services\IncomeService;
use App\Services\RecurringExpenseService;
use App\Services\SettingsService;
use App\Services\TransferService;
use Illuminate\Database\Seeder;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * بيانات تجريبية لآخر 6 شهور — كلها بتعدّي على الـ Services عشان الدفتر يفضل مظبوط.
 */
class DemoDataSeeder extends Seeder
{
    private array $sources;
    private array $methods;
    private array $categories;

    public function run(
        IncomeService $incomes,
        TransferService $transfers,
        ExpenseRequestService $requests,
        RecurringExpenseService $recurring,
        SettingsService $settings,
    ): void {
        mt_srand(2026);

        $this->sources = IncomeSource::pluck('id', 'name')->all();
        $this->methods = PaymentMethod::pluck('id', 'name')->all();
        $this->categories = ExpenseCategory::pluck('id', 'name')->all();

        // الأشخاص حاملي العهد
        $ahmedC = Custodian::create(['name' => 'الشيخ أحمد', 'title' => 'إمام المسجد', 'phone' => '01000000001']);
        $mahmoudC = Custodian::create(['name' => 'الحاج محمود', 'title' => 'أمين الصندوق', 'phone' => '01000000002']);
        $khaledC = Custodian::create(['name' => 'الأستاذ خالد', 'title' => 'المحاسب', 'phone' => '01000000003']);
        $sayedC = Custodian::create(['name' => 'عم سيد', 'title' => 'عامل ومسؤول الخدمات', 'phone' => '01023456789']);

        // المستخدمين (كلمة المرور: 12345678)
        $imam = $this->user('الشيخ أحمد', '01000000001', User::ROLE_IMAM, $ahmedC);
        $mahmoud = $this->user('الحاج محمود', '01000000002', User::ROLE_TREASURER, $mahmoudC);
        $khaled = $this->user('الأستاذ خالد', '01000000003', User::ROLE_ACCOUNTANT, $khaledC);

        // المصروفات الثابتة
        $items = [
            ['فاتورة الكهرباء', 'كهرباء', 1200, 10, $mahmoudC, false],
            ['فاتورة المياه', 'مياه', 300, 12, $mahmoudC, false],
            ['اشتراك الإنترنت', 'إنترنت', 250, 5, $khaledC, false],
            ['مرتب الإمام', 'مرتب الإمام', 4000, 1, $khaledC, false],
            ['مرتب المؤذن', 'مرتب المؤذن', 2500, 1, $khaledC, false],
            ['مرتب العامل', 'مرتب العامل', 2000, 1, $khaledC, false],
            ['مستلزمات النظافة الشهرية', 'نظافة', 400, 15, $sayedC, true],
        ];
        foreach ($items as [$name, $cat, $amount, $day, $custodian, $pre]) {
            RecurringExpense::create([
                'name' => $name,
                'expense_category_id' => $this->categories[$cat],
                'amount' => $amount,
                'due_day' => $day,
                'responsible_custodian_id' => $custodian->id,
                'pre_approved' => $pre,
            ]);
        }

        // الفواتير مش متاحة في البيانات التجريبية
        $settings->set(['require_invoice_on_execute' => false]);

        $today = now()->startOfDay();

        // رصيد افتتاحي قبل أول شهر
        $opening = $today->copy()->subMonthsNoOverflow(5)->startOfMonth()->subDay();
        foreach ([[$khaledC, 15000, $khaled], [$mahmoudC, 5000, $mahmoud], [$ahmedC, 3000, $imam], [$sayedC, 500, $mahmoud]] as [$custodian, $amount, $by]) {
            $this->as($opening->copy()->setTime(12, 0), fn () => $incomes->create([
                'date' => $opening->toDateString(),
                'income_source_id' => $this->sources['أخرى'],
                'payment_method_id' => $this->methods['كاش'],
                'amount' => $amount,
                'custodian_id' => $custodian->id,
                'notes' => 'رصيد افتتاحي عند بدء استخدام النظام',
            ], $by));
        }

        for ($m = 5; $m >= 0; $m--) {
            $month = $today->copy()->subMonthsNoOverflow($m)->startOfMonth();
            $isCurrent = $m === 0;
            $lastDay = $isCurrent ? $today->day : $month->daysInMonth;
            $at = fn (int $day, int $hour = 11) => $month->copy()->day(min($day, $lastDay))->setTime($hour, mt_rand(0, 59));

            // ── الإيرادات
            foreach ([1, 8, 15, 22, 29] as $friday) {
                if ($friday > $lastDay) {
                    continue;
                }
                $this->as($at($friday, 14), fn () => $incomes->create([
                    'date' => $at($friday)->toDateString(),
                    'income_source_id' => $this->sources['تبرعات المصلين'],
                    'payment_method_id' => $this->methods['كاش'],
                    'amount' => mt_rand(30, 60) * 100,
                    'custodian_id' => $mahmoudC->id,
                    'notes' => 'صندوق صلاة الجمعة',
                ], $mahmoud));
            }

            $this->as($at(3), fn () => $incomes->create([
                'date' => $at(3)->toDateString(),
                'income_source_id' => $this->sources['اشتراكات التحفيظ'],
                'payment_method_id' => $this->methods['إنستاباي'],
                'amount' => mt_rand(14, 20) * 100,
                'custodian_id' => $khaledC->id,
            ], $khaled));

            $this->as($at(5), fn () => $incomes->create([
                'date' => $at(5)->toDateString(),
                'income_source_id' => $this->sources['إيجار'],
                'payment_method_id' => $this->methods['تحويل بنكي'],
                'amount' => 2000,
                'custodian_id' => $khaledC->id,
                'notes' => 'إيجار المحل الملحق بالمسجد',
            ], $khaled));

            $this->as($at(18), fn () => $incomes->create([
                'date' => $at(18)->toDateString(),
                'income_source_id' => $this->sources['تبرع فردي'],
                'payment_method_id' => $this->methods['فودافون كاش'],
                'amount' => mt_rand(5, 30) * 100,
                'custodian_id' => $ahmedC->id,
                'payer_name' => 'فاعل خير',
            ], $imam));

            // ── تسليم فلوس بين الأشخاص
            $t1 = $this->as($at(2, 19), fn () => $transfers->create([
                'date' => $at(2)->toDateString(),
                'from_custodian_id' => $mahmoudC->id,
                'to_custodian_id' => $khaledC->id,
                'amount' => 6000,
                'notes' => 'لدفع المرتبات',
            ], $mahmoud));
            $this->as($at(2, 20), fn () => $transfers->accept($t1, $khaled));

            $t2 = $this->as($at(9, 19), fn () => $transfers->create([
                'date' => $at(9)->toDateString(),
                'from_custodian_id' => $mahmoudC->id,
                'to_custodian_id' => $sayedC->id,
                'amount' => 1000,
                'notes' => 'مصاريف نثرية',
            ], $mahmoud));
            $this->as($at(9, 20), fn () => $transfers->accept($t2, $imam));

            // ── المصروفات الثابتة
            $this->as($month->copy(), fn () => $recurring->generateForMonth($month));

            $periods = RecurringExpensePeriod::with('recurringExpense')->whereDate('period', $month->toDateString())->get();
            foreach ($periods as $period) {
                $due = $period->due_date;
                if ($isCurrent && $due->gt($today)) {
                    continue; // لسه ميعاده مجاش
                }
                // في الشهر الحالي نسيب بند متأخر للعرض
                if ($isCurrent && $period->recurringExpense->name === 'فاتورة المياه') {
                    continue;
                }

                $request = $this->as($due->copy()->subDay()->setTime(10, 0), fn () => $recurring->createRequest($period, $khaled));
                if ($request->status === ExpenseRequest::PENDING) {
                    $this->as($due->copy()->subDay()->setTime(20, 0), fn () => $requests->approve($request, $imam));
                }
                $this->as($due->copy()->setTime(13, 0), fn () => $requests->execute($request->fresh(), $khaled, [
                    'executed_date' => $due->toDateString(),
                ]));
            }

            // ── المصروفات غير الثابتة
            if (! $isCurrent) {
                $variable = [
                    [$sayedC, 'مشتريات', 'شراء مناديل ومعطر للمصلى', mt_rand(2, 5) * 100, $mahmoud],
                    [$ahmedC, 'صيانة', 'صيانة مراوح المصلى', mt_rand(6, 15) * 100, $imam],
                ];
                if ($m % 2 === 0) {
                    $variable[] = [$mahmoudC, 'فرش', 'تنظيف وغسيل سجاد المصلى', mt_rand(15, 25) * 100, $mahmoud];
                }
                foreach ($variable as $i => [$custodian, $cat, $desc, $amount, $by]) {
                    $day = 12 + $i * 5;
                    $request = $this->as($at($day, 9), fn () => $requests->create([
                        'custodian_id' => $custodian->id,
                        'expense_category_id' => $this->categories[$cat],
                        'amount' => $amount,
                        'description' => $desc,
                    ], $by));
                    if ($request->status === ExpenseRequest::PENDING) {
                        $this->as($at($day, 20), fn () => $requests->approve($request, $imam));
                    }
                    $this->as($at($day + 1, 12), fn () => $requests->execute($request->fresh(), $by, [
                        'executed_date' => $at($day + 1)->toDateString(),
                        'actual_amount' => $amount - ($i === 0 ? 50 : 0),
                    ]));
                }

                // طلب مرفوض في الشهر اللي فات
                if ($m === 1) {
                    $request = $this->as($at(20, 10), fn () => $requests->create([
                        'custodian_id' => $mahmoudC->id,
                        'expense_category_id' => $this->categories['فرش'],
                        'amount' => 3500,
                        'description' => 'شراء ستائر جديدة لمصلى السيدات',
                    ], $mahmoud));
                    $this->as($at(21, 9), fn () => $requests->reject($request, $imam, 'الستائر الحالية بحالة كويسة، نأجلها للسنة الجاية'));
                }
            }
        }

        // ── طلبات الشهر الحالي بحالات مختلفة (زي الديزاين)
        $this->as($today->copy()->subDays(1)->setTime(20, 30), fn () => $requests->create([
            'custodian_id' => $mahmoudC->id,
            'expense_category_id' => $this->categories['مشتريات'],
            'amount' => 450,
            'description' => 'شراء شمعات فلاتر مياه الشرب للمصلين',
        ], $mahmoud));
        $this->as(now()->subHours(4), fn () => $requests->create([
            'custodian_id' => $khaledC->id,
            'expense_category_id' => $this->categories['صيانة'],
            'amount' => 1200,
            'description' => 'صيانة طارئة لسماعات المئذنة وميكروفون المنبر',
        ], $khaled));
        $this->as(now()->subHours(2), fn () => $requests->create([
            'custodian_id' => $sayedC->id,
            'expense_category_id' => $this->categories['مشتريات'],
            'amount' => 650,
            'description' => 'أدوات نظافة ومطهرات دورات المياه والميضأة',
        ], $mahmoud));

        $this->as(now()->subDays(2), fn () => $requests->create([
            'custodian_id' => $ahmedC->id,
            'expense_category_id' => $this->categories['صيانة'],
            'amount' => 800,
            'description' => 'تغيير لمبات الإضاءة في الصحن الخارجي',
        ], $imam)); // طلب الإمام نفسه → موافقة تلقائية
        $returned = $this->as(now()->subDays(3), fn () => $requests->create([
            'custodian_id' => $sayedC->id,
            'expense_category_id' => $this->categories['فرش'],
            'amount' => 900,
            'description' => 'شراء مصاحف وحوامل جديدة',
        ], $mahmoud));
        $this->as(now()->subDays(2)->setTime(21, 0), fn () => $requests->returnForEdit($returned, $imam, 'محتاج عرض سعر من مكتبتين على الأقل'));

        $settings->set(['require_invoice_on_execute' => true]);
    }

    private function user(string $name, string $phone, string $role, Custodian $custodian): User
    {
        $user = User::create([
            'name' => $name,
            'phone' => $phone,
            'password' => '12345678',
            'custodian_id' => $custodian->id,
        ]);
        $user->assignRole($role);

        return $user;
    }

    /** ينفّذ العملية كأنها حصلت في وقت معيّن عشان التواريخ والخط الزمني يبانوا واقعيين */
    private function as(CarbonInterface $time, callable $callback): mixed
    {
        Carbon::setTestNow($time);
        try {
            return $callback();
        } finally {
            Carbon::setTestNow();
        }
    }
}
