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
        Schema::create('currencies', function (Blueprint $table) {
              $table->id();

            /**
             * ISO 4217 currency code
             * Examples: ZMW, USD, ZAR
             */
            $table->string('code', 3)->unique();

            /**
             * Human-readable name
             * Examples: Zambian Kwacha, US Dollar
             */
            $table->string('name');

            /**
             * Currency symbol
             * Examples: K, $, R
             */
            $table->string('symbol')->nullable();
 
            $table->unsignedTinyInteger('precision')->default(2);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
