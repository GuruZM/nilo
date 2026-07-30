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
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();

            /**
             * Rates are stored against a single base (USD), matching what the
             * upstream feed returns. Cross-rates are derived at read time.
             */
            $table->string('base_code', 3);
            $table->string('quote_code', 3);

            /**
             * Units of the quote currency per 1 unit of the base. Wide enough
             * for currencies that trade in the tens of thousands per dollar.
             */
            $table->decimal('rate', 20, 10);

            $table->timestamp('fetched_at');

            $table->timestamps();

            /** Rows are upserted in place — this table does not grow. */
            $table->unique(['base_code', 'quote_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
