<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `amount` and `currency_code` keep their meaning — what the subscriber
     * owes, in the plan's own currency — so the coupon arithmetic beside them
     * and every admin total still read true. The `charged_*` quartet records
     * what DPO was actually asked to take, with the rate frozen the way
     * documents freeze theirs, because a receipt is proof of a transaction at
     * a moment in time and not of today's rate.
     *
     * `gateway_status` sits beside `status` rather than widening it. The admin
     * index filters on pending/confirmed/rejected and defaults to pending, so
     * a fourth value would land in a row visible under no tab but "all", and
     * would quietly change what Payment::isPending() means everywhere else.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('company_ref', 64)->nullable()->after('payment_reference');
            $table->string('dpo_transaction_token', 128)->nullable()->after('company_ref');

            $table->string('gateway_status')->nullable()->after('status');
            $table->json('gateway_response')->nullable()->after('gateway_status');

            $table->decimal('charged_amount', 14, 2)->nullable()->after('discount_amount');
            $table->string('charged_currency_code', 3)->nullable()->after('charged_amount');
            $table->decimal('charged_exchange_rate', 18, 8)->nullable()->after('charged_currency_code');
            $table->dateTime('charged_rate_fetched_at')->nullable()->after('charged_exchange_rate');

            $table->dateTime('paid_at')->nullable()->after('confirmed_at');
            $table->dateTime('verified_at')->nullable()->after('paid_at');

            // Unique rather than merely indexed: a reference or a token must
            // never be able to name two payments. Nullable uniques permit
            // unlimited NULLs everywhere we deploy, so existing manual rows
            // are untouched.
            $table->unique('company_ref');
            $table->unique('dpo_transaction_token');
            $table->index(['payment_method', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['company_ref']);
            $table->dropUnique(['dpo_transaction_token']);
            $table->dropIndex(['payment_method', 'status']);

            $table->dropColumn([
                'company_ref',
                'dpo_transaction_token',
                'gateway_status',
                'gateway_response',
                'charged_amount',
                'charged_currency_code',
                'charged_exchange_rate',
                'charged_rate_fetched_at',
                'paid_at',
                'verified_at',
            ]);
        });
    }
};
