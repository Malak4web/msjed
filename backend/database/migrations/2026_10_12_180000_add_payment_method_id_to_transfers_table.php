<?php

use App\Models\PaymentMethod;
use App\Models\Transfer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->foreignId('payment_method_id')->nullable()->after('amount')->constrained('payment_methods')->nullOnDelete();
        });

        // تعيين طريقة التسليم الافتراضية (كاش) للسجلات السابقة
        $defaultMethodId = PaymentMethod::where('name', 'كاش')->value('id') ?? PaymentMethod::value('id');
        if ($defaultMethodId) {
            Transfer::whereNull('payment_method_id')->update(['payment_method_id' => $defaultMethodId]);
        }
    }

    public function down(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->dropForeign(['payment_method_id']);
            $table->dropColumn('payment_method_id');
        });
    }
};
