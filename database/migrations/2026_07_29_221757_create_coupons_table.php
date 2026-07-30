<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Discount codes an admin issues and a customer redeems while buying a plan.
     *
     * `discount_value` carries a percentage (0-100) when `discount_type` is
     * "percentage" and a money amount when it is "fixed"; `currency_code` is
     * only meaningful for the latter and pins the coupon to plans priced in
     * that currency. `redemptions_count` is maintained alongside the
     * coupon_redemptions rows so the cap can be claimed with a single
     * conditional UPDATE instead of a read-then-write race.
     */
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('description')->nullable();
            $table->string('discount_type');
            $table->decimal('discount_value', 14, 2);
            $table->char('currency_code', 3)->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('redemptions_count')->default(0);
            $table->boolean('once_per_user')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
