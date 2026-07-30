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
        foreach (['invoices', 'quotations'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                /**
                 * Units of the document's currency per 1 USD, frozen on the day
                 * the document was issued. Null on rows that pre-date FX support
                 * and on documents issued while the rate feed was unavailable —
                 * both fall back to the latest rate at display time.
                 */
                $table->decimal('exchange_rate_to_base', 20, 10)->nullable()->after('currency_code');
                $table->timestamp('exchange_rate_fetched_at')->nullable()->after('exchange_rate_to_base');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['invoices', 'quotations'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn(['exchange_rate_to_base', 'exchange_rate_fetched_at']);
            });
        }
    }
};
