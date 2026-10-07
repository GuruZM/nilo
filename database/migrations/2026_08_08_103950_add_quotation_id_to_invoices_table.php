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
            /**
             * The quotation this invoice was raised from, when it was raised
             * from one at all.
             *
             * Nulled rather than cascaded on delete: an invoice is money owed
             * and must outlive the quote that led to it. The unique index is
             * what actually enforces one invoice per quotation — the
             * controller checks first for a civil answer, but two concurrent
             * clicks both pass that check and only the index settles it.
             */
            $table->foreignId('quotation_id')
                ->nullable()
                ->after('client_id')
                ->constrained('quotations')
                ->nullOnDelete();

            $table->unique('quotation_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['quotation_id']);
            $table->dropConstrainedForeignId('quotation_id');
        });
    }
};
