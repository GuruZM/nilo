<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Restricts a coupon to specific plans. No rows means the coupon applies to
     * every purchasable plan, so the empty state is the permissive one.
     */
    public function up(): void
    {
        Schema::create('coupon_plan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();

            $table->unique(['coupon_id', 'plan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_plan');
    }
};
