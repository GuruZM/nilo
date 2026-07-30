<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Timestamped one second after `purchase_orders` on purpose: Laravel runs
     * migrations in filename order, and `purchase_order_items` sorts before
     * `purchase_orders` alphabetically, so a shared timestamp would break the
     * foreign key below.
     */
    public function up(): void
    {
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();

            $table->string('description');
            $table->string('unit')->nullable();
            $table->decimal('quantity', 14, 2)->default(1);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('tax', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['purchase_order_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
