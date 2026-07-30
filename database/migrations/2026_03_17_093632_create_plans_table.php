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
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 14, 2)->default(0);
            $table->string('currency_code', 3)->default('ZMW');
            $table->string('billing_period')->default('monthly');
            $table->integer('max_companies')->default(1);
            $table->integer('max_invoices')->default(1);
            $table->integer('max_quotations')->default(1);
            $table->integer('max_invoice_templates')->default(1);
            $table->integer('max_quotation_templates')->default(1);
            $table->boolean('can_upload_custom_template')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->json('features')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
