<?php

use App\Http\Controllers\Api\AttachmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustodianController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ExpenseRequestController;
use App\Http\Controllers\Api\RecurringExpenseController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\TransferController;
use App\Http\Controllers\Api\IncomeController;
use App\Http\Controllers\Api\LookupController;
use App\Http\Controllers\Api\MetaController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PeriodLockController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::post('auth/login', [AuthController::class, 'login']);
Route::get('settings/logo', [SettingsController::class, 'logo']);
Route::get('attachments/{attachment}', [AttachmentController::class, 'show'])
    ->name('attachments.show')->middleware('signed:relative');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('meta', MetaController::class);

    // الإشعارات + Push
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::post('notifications/read-all', [NotificationController::class, 'readAll']);
    Route::post('notifications/{id}/read', [NotificationController::class, 'read']);
    Route::get('push/key', [NotificationController::class, 'pushKey']);
    Route::post('push/subscribe', [NotificationController::class, 'subscribe']);
    Route::post('push/unsubscribe', [NotificationController::class, 'unsubscribe']);
    Route::post('push/test', [NotificationController::class, 'test'])->middleware('throttle:6,1');

    // الأشخاص حاملي العهد
    Route::get('custodians', [CustodianController::class, 'index']);
    Route::get('custodians/{custodian}', [CustodianController::class, 'show']);

    // الإيرادات
    Route::get('incomes', [IncomeController::class, 'index']);
    Route::post('incomes', [IncomeController::class, 'store']);
    Route::get('incomes/{income}', [IncomeController::class, 'show']);
    Route::get('incomes/{income}/receipt', [IncomeController::class, 'receipt']);
    Route::post('incomes/{income}/reverse', [IncomeController::class, 'reverse'])->middleware('can:entries.reverse');

    // لوحة التحكم
    Route::get('dashboard', DashboardController::class);

    // طلبات الصرف — الموافقة/الرفض/الإرجاع للإمام بس (بتتأكد جوه الـ Service)
    Route::get('expense-requests', [ExpenseRequestController::class, 'index']);
    Route::post('expense-requests', [ExpenseRequestController::class, 'store']);
    Route::get('expense-requests/{expenseRequest}', [ExpenseRequestController::class, 'show']);
    Route::match(['put', 'post'], 'expense-requests/{expenseRequest}/update', [ExpenseRequestController::class, 'update']);
    Route::put('expense-requests/{expenseRequest}', [ExpenseRequestController::class, 'update']);
    Route::post('expense-requests/{expenseRequest}/approve', [ExpenseRequestController::class, 'approve']);
    Route::post('expense-requests/{expenseRequest}/reject', [ExpenseRequestController::class, 'reject']);
    Route::post('expense-requests/{expenseRequest}/return', [ExpenseRequestController::class, 'return']);
    Route::post('expense-requests/{expenseRequest}/execute', [ExpenseRequestController::class, 'execute']);
    Route::post('expense-requests/{expenseRequest}/cancel', [ExpenseRequestController::class, 'cancel']);
    Route::post('expense-requests/{expenseRequest}/reverse', [ExpenseRequestController::class, 'reverse']);

    // تسليم فلوس بين الأشخاص
    Route::get('transfers', [TransferController::class, 'index']);
    Route::post('transfers', [TransferController::class, 'store'])->middleware('can:transfers.create');
    Route::post('transfers/{transfer}/accept', [TransferController::class, 'accept']);
    Route::post('transfers/{transfer}/reject', [TransferController::class, 'reject']);
    Route::post('transfers/{transfer}/cancel', [TransferController::class, 'cancel']);
    Route::post('transfers/{transfer}/reverse', [TransferController::class, 'reverse'])->middleware('can:entries.reverse');

    // المصروفات الثابتة
    Route::get('recurring-expenses', [RecurringExpenseController::class, 'index']);
    Route::post('recurring-expenses/{recurringExpense}/create-request', [RecurringExpenseController::class, 'createRequest'])->middleware('can:expenses.create');
    Route::middleware('can:settings.manage')->group(function () {
        Route::post('recurring-expenses', [RecurringExpenseController::class, 'store']);
        Route::put('recurring-expenses/{recurringExpense}', [RecurringExpenseController::class, 'update']);
    });

    // كشف الحساب والتقارير
    Route::get('custodians/{custodian}/statement', [ReportController::class, 'statement']);
    Route::middleware('can:reports.view')->group(function () {
        Route::get('reports', [ReportController::class, 'index']);
        Route::get('reports/{type}', [ReportController::class, 'show']);
    });

    // إقفال الشهر
    Route::get('period-locks', [PeriodLockController::class, 'index']);
    Route::middleware('can:periods.lock')->group(function () {
        Route::post('period-locks', [PeriodLockController::class, 'store']);
        Route::delete('period-locks/{period}', [PeriodLockController::class, 'destroy'])->where('period', '\d{4}-\d{2}');
    });

    // المستخدمين والإعدادات
    Route::middleware('can:settings.manage')->group(function () {
        Route::get('settings', [SettingsController::class, 'show']);
        Route::put('settings', [SettingsController::class, 'update']);
        Route::post('settings/logo', [SettingsController::class, 'uploadLogo']);

        Route::get('users', [UserController::class, 'index']);
        Route::post('users', [UserController::class, 'store']);
        Route::get('users/{user}', [UserController::class, 'show']);
        Route::put('users/{user}', [UserController::class, 'update']);
        Route::delete('users/{user}', [UserController::class, 'destroy']);
        Route::post('users/{user}/impersonate', [UserController::class, 'impersonate']);

        Route::get('permissions', [PermissionController::class, 'index']);
        Route::put('permissions', [PermissionController::class, 'update']);

        Route::post('custodians', [CustodianController::class, 'store']);
        Route::put('custodians/{custodian}', [CustodianController::class, 'update']);

        Route::get('lookups/{type}', [LookupController::class, 'index']);
        Route::post('lookups/{type}', [LookupController::class, 'store']);
        Route::put('lookups/{type}/{id}', [LookupController::class, 'update']);
    });
});
