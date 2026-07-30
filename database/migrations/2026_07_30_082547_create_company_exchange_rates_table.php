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
        Schema::create('company_exchange_rates', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            /** Mirrors `exchange_rates`: everything is quoted against USD. */
            $table->string('base_code', 3);
            $table->string('quote_code', 3);

            /** Same precision as the synced table so no rounding is introduced. */
            $table->decimal('rate', 20, 10);

            /**
             * Drives precedence. An override applies only while it is newer
             * than the last sync that wrote the matching `exchange_rates` row.
             */
            $table->timestamp('set_at');

            $table->foreignId('set_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['company_id', 'base_code', 'quote_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_exchange_rates');
    }
};
