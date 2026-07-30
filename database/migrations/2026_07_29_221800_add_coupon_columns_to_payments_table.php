<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `amount` keeps its meaning — what the customer actually owes — so every
     * existing reconciliation still reads true. The list price and the money
     * taken off it are recorded beside it, plus a snapshot of the code so a
     * receipt survives the coupon being renamed or deleted.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('coupon_id')->nullable()->after('plan_id')->constrained()->nullOnDelete();
            $table->string('coupon_code')->nullable()->after('coupon_id');
            $table->decimal('original_amount', 14, 2)->nullable()->after('amount');
            $table->decimal('discount_amount', 14, 2)->default(0)->after('original_amount');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn(['coupon_code', 'original_amount', 'discount_amount']);
        });
    }
};
