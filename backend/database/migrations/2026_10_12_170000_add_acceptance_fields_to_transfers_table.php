<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->string('status', 20)->default('completed')->after('amount')->index();
            $table->timestamp('received_at')->nullable()->after('notes');
            $table->foreignId('received_by')->nullable()->after('received_at')->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable()->after('received_by');
            $table->foreignId('rejected_by')->nullable()->after('rejected_at')->constrained('users')->nullOnDelete();
            $table->string('rejection_reason')->nullable()->after('rejected_by');
            $table->timestamp('cancelled_at')->nullable()->after('rejection_reason');
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason')->nullable()->after('cancelled_by');
        });
    }

    public function down(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->dropForeign(['received_by']);
            $table->dropForeign(['rejected_by']);
            $table->dropForeign(['cancelled_by']);
            $table->dropColumn([
                'status',
                'received_at',
                'received_by',
                'rejected_at',
                'rejected_by',
                'rejection_reason',
                'cancelled_at',
                'cancelled_by',
                'cancellation_reason',
            ]);
        });
    }
};
