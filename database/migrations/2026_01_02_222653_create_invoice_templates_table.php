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
        Schema::create('invoice_templates', function (Blueprint $table) {
           $table->id();

            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            $table->string('name'); // e.g. "Default", "Modern", "Thermal"
            $table->boolean('is_default')->default(false);

            // “Room” for template settings. Keep it flexible.
            // We'll store header/footer, colors, layout preference, etc.
            $table->json('settings')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'is_default']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_templates');
    }
};
