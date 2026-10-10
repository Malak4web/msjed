<?php

namespace App\Support;

use App\Models\User;

/**
 * الصلاحيات القابلة للتعديل من الإعدادات.
 * ملحوظة: الموافقة/الرفض مش صلاحية هنا — دي للإمام بس ومتتغيرش.
 */
class Permissions
{
    public const LABELS = [
        'incomes.create' => 'تسجيل إيراد',
        'expenses.create' => 'إنشاء طلب صرف',
        'expenses.execute' => 'تأكيد التنفيذ ورفع الفاتورة',
        'transfers.create' => 'تسليم فلوس بين الأشخاص',
        'reports.view' => 'التقارير',
        'entries.reverse' => 'إلغاء عملية بقيد عكسي',
        'periods.lock' => 'إقفال الشهر',
        'settings.manage' => 'المستخدمين والإعدادات',
    ];

    public const DEFAULTS = [
        User::ROLE_IMAM => ['incomes.create', 'expenses.create', 'expenses.execute', 'transfers.create', 'reports.view', 'entries.reverse', 'periods.lock', 'settings.manage'],
        User::ROLE_TREASURER => ['incomes.create', 'expenses.create', 'expenses.execute', 'transfers.create', 'reports.view'],
        User::ROLE_ACCOUNTANT => ['incomes.create', 'expenses.create', 'expenses.execute', 'transfers.create', 'reports.view', 'entries.reverse', 'periods.lock', 'settings.manage'],
    ];
}
