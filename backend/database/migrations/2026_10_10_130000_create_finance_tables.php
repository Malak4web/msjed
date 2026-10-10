<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // الأشخاص حاملي العُهد (ممكن يكون ملهمش حساب دخول)
        Schema::create('custodians', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('title')->nullable(); // الصفة
            $table->string('phone', 20)->nullable();
            $table->string('photo_path')->nullable();
            $table->boolean('is_active')->default(true);
            // كاش للرصيد فقط — المرجع هو ledger_entries
            $table->decimal('balance_cache', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('income_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('icon')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('icon')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 10)->default('variable'); // fixed | variable
            $table->string('icon')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        // عدّادات الأرقام المسلسلة (إيصالات، طلبات، تحويلات)
        Schema::create('sequences', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->unsignedBigInteger('value')->default(0);
        });

        Schema::create('incomes', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_no')->unique();
            $table->date('date')->index();
            $table->foreignId('income_source_id')->constrained();
            $table->foreignId('payment_method_id')->constrained();
            $table->decimal('amount', 12, 2);
            $table->foreignId('custodian_id')->constrained();
            $table->string('payer_name')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users');
            $table->string('reversal_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->date('date')->index();
            $table->foreignId('from_custodian_id')->constrained('custodians');
            $table->foreignId('to_custodian_id')->constrained('custodians');
            $table->decimal('amount', 12, 2);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users');
            $table->string('reversal_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('recurring_expenses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('expense_category_id')->constrained();
            $table->decimal('amount', 12, 2);
            $table->unsignedTinyInteger('due_day'); // 1..28
            $table->foreignId('responsible_custodian_id')->constrained('custodians');
            $table->boolean('pre_approved')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('recurring_expense_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recurring_expense_id')->constrained()->cascadeOnDelete();
            $table->date('period'); // أول يوم في الشهر
            $table->date('due_date');
            $table->string('status', 10)->default('due'); // due | overdue | paid
            $table->unsignedBigInteger('expense_request_id')->nullable();
            $table->timestamps();
            $table->unique(['recurring_expense_id', 'period']);
        });

        Schema::create('expense_requests', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('custodian_id')->constrained();
            $table->foreignId('expense_category_id')->constrained();
            $table->string('type', 10); // fixed | variable
            $table->decimal('amount', 12, 2);
            $table->text('description');
            // pending | approved | executed | rejected | returned | cancelled
            $table->string('status', 12)->default('pending')->index();
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->boolean('auto_approved')->default(false);
            $table->string('auto_approval_reason')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('return_reason')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->date('executed_date')->nullable();
            $table->foreignId('executed_by')->nullable()->constrained('users');
            $table->decimal('actual_amount', 12, 2)->nullable();
            $table->foreignId('recurring_expense_period_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users');
            $table->string('reversal_reason')->nullable();
            $table->timestamps();
        });

        // الخط الزمني لكل طلب: مين عمل إيه وإمتى
        Schema::create('expense_request_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_request_id')->constrained()->cascadeOnDelete();
            $table->string('action', 20);
            $table->foreignId('user_id')->nullable()->constrained();
            $table->text('note')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // المرفقات (صور الإيصالات، عروض الأسعار، الفواتير)
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->morphs('attachable');
            $table->string('kind', 20)->default('file'); // receipt | quote | invoice | file
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime', 100)->nullable();
            $table->unsignedInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        // دفتر الحركات — مصدر الحقيقة للأرصدة
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custodian_id')->constrained();
            // income | transfer_in | transfer_out | expense | reversal
            $table->string('type', 15);
            $table->decimal('amount', 12, 2); // موجب = وارد، سالب = منصرف
            $table->date('date');
            $table->string('description');
            $table->nullableMorphs('related');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->index(['custodian_id', 'date']);
        });

        Schema::create('period_locks', function (Blueprint $table) {
            $table->id();
            $table->date('period')->unique(); // أول يوم في الشهر
            $table->foreignId('locked_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('endpoint');
            $table->string('public_key')->nullable();
            $table->string('auth_token')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'push_subscriptions', 'settings', 'period_locks', 'ledger_entries', 'attachments',
            'expense_request_events', 'expense_requests', 'recurring_expense_periods', 'recurring_expenses',
            'transfers', 'incomes', 'sequences', 'expense_categories', 'payment_methods', 'income_sources', 'custodians',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
