<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * No prices here by design. A delivery note proves what arrived; putting
     * money on it hands your margins to whoever signs for the goods.
     */
    public function up(): void
    {
        Schema::create('delivery_note_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('delivery_note_id')->constrained('delivery_notes')->cascadeOnDelete();

            $table->string('description');
            $table->string('unit')->nullable();
            $table->decimal('quantity', 14, 2)->default(1);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['delivery_note_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_note_items');
    }
};
