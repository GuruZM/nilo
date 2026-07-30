<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quotation templates live in the invoice_templates table under type
     * 'quotation'. The column stays nullable so existing quotations survive the
     * migration; selection is required at the validation layer instead.
     */
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            if (! Schema::hasColumn('quotations', 'quotation_template_id')) {
                $table->foreignId('quotation_template_id')
                    ->nullable()
                    ->after('client_id')
                    ->constrained('invoice_templates')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            if (Schema::hasColumn('quotations', 'quotation_template_id')) {
                $table->dropConstrainedForeignId('quotation_template_id');
            }
        });
    }
};
