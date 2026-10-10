<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use App\Models\IncomeSource;
use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class LookupsSeeder extends Seeder
{
    public function run(): void
    {
        $sources = [
            ['تبرعات المصلين', 'volunteer_activism'],
            ['تبرع فردي', 'favorite'],
            ['اشتراكات التحفيظ', 'menu_book'],
            ['إيجار', 'storefront'],
            ['أخرى', 'more_horiz'],
        ];
        foreach ($sources as $i => [$name, $icon]) {
            IncomeSource::firstOrCreate(['name' => $name], ['icon' => $icon, 'sort' => $i]);
        }

        $methods = [
            ['كاش', 'payments'],
            ['تحويل بنكي', 'account_balance'],
            ['إنستاباي', 'bolt'],
            ['فودافون كاش', 'smartphone'],
            ['شيك', 'receipt'],
        ];
        foreach ($methods as $i => [$name, $icon]) {
            PaymentMethod::firstOrCreate(['name' => $name], ['icon' => $icon, 'sort' => $i]);
        }

        $categories = [
            ['كهرباء', 'fixed', 'bolt'],
            ['مياه', 'fixed', 'water_drop'],
            ['إنترنت', 'fixed', 'wifi'],
            ['مرتب الإمام', 'fixed', 'badge'],
            ['مرتب المؤذن', 'fixed', 'campaign'],
            ['مرتب العامل', 'fixed', 'engineering'],
            ['نظافة', 'fixed', 'cleaning_services'],
            ['صيانة', 'variable', 'build'],
            ['فرش', 'variable', 'chair'],
            ['مشتريات', 'variable', 'shopping_cart'],
            ['أخرى', 'variable', 'more_horiz'],
        ];
        foreach ($categories as $i => [$name, $type, $icon]) {
            ExpenseCategory::firstOrCreate(['name' => $name, 'type' => $type], ['icon' => $icon, 'sort' => $i]);
        }
    }
}
