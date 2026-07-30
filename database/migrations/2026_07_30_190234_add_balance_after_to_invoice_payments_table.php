<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What the invoice still owed once this payment landed.
     *
     * Frozen at issuance, exactly as the exchange rate on this same row is: a
     * receipt is proof of a transaction at a moment in time, so recomputing the
     * balance on reprint would make the customer's paper copy and ours
     * disagree. Nullable because rows written before this column existed have
     * no honest value to backfill — those fall back to the live figure rather
     * than being stamped with a guess.
     */
    public function up(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->decimal('balance_after', 14, 2)->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->dropColumn('balance_after');
        });
    }
};
