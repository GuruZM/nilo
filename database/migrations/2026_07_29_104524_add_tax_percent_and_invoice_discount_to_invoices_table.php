<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            /** Tax is charged on the whole invoice, as a rate rather than an amount. */
            $table->decimal('tax_percent', 5, 2)->default(0)->after('tax_total');

            /**
             * The whole-invoice discount was already being computed into
             * `discount_total` but had nowhere to live on its own, so the
             * split between line and invoice discounts was being lost.
             */
            $table->decimal('invoice_discount', 14, 2)->default(0)->after('discount_total');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['tax_percent', 'invoice_discount']);
        });
    }
};
