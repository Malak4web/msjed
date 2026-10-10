<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Custodian;
use App\Models\ExpenseCategory;
use App\Models\ExpenseRequest;
use App\Models\IncomeSource;
use App\Models\LedgerEntry;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\SettingsService;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;

/**
 * كل القوائم اللي الفورمز محتاجاها في طلب واحد.
 */
class MetaController extends Controller
{
    public function __invoke(SettingsService $settings): JsonResponse
    {
        $all = $settings->all();

        return response()->json([
            'income_sources' => IncomeSource::where('is_active', true)->orderBy('sort')->get(['id', 'name', 'icon']),
            'payment_methods' => PaymentMethod::where('is_active', true)->orderBy('sort')->get(['id', 'name', 'icon']),
            'expense_categories' => ExpenseCategory::where('is_active', true)->orderBy('sort')->get(['id', 'name', 'type', 'icon']),
            'custodians' => Custodian::where('is_active', true)->orderBy('id')->get(['id', 'name', 'title', 'balance_cache as balance']),
            'settings' => [
                'mosque_name' => $all['mosque_name'],
                'has_logo' => (bool) $all['logo_path'],
                'receipt_format' => $all['receipt_format'],
                'require_invoice_on_execute' => (bool) $all['require_invoice_on_execute'],
                'show_hijri_date' => (bool) $all['show_hijri_date'],
            ],
            'roles' => User::ROLE_LABELS,
            'permissions' => Permissions::LABELS,
            'request_statuses' => ExpenseRequest::STATUS_LABELS,
            'ledger_types' => LedgerEntry::TYPE_LABELS,
        ]);
    }
}
