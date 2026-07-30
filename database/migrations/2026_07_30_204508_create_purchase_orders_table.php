<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An order placed with a supplier. Standalone — unlike every other document
     * added in v1, a purchase order does not hang off an invoice.
     */
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('purchase_order_template_id')->nullable()
                ->constrained('invoice_templates')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('number')->nullable();
            $table->string('reference')->nullable();
            $table->string('title')->nullable();

            $table->date('issue_date');
            $table->date('expected_date')->nullable();
            $table->string('currency_code', 3);

            $table->string('delivery_address')->nullable();

            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('purchase_order_discount', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('tax_percent', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);

            $table->string('status')->default('draft'); // draft|sent|approved|received|cancelled

            $table->text('notes')->nullable();
            $table->text('terms')->nullable();

            $table->decimal('exchange_rate_to_base', 18, 8)->nullable();
            $table->timestamp('exchange_rate_fetched_at')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'issue_date']);
            $table->index(['company_id', 'supplier_id']);
            $table->unique(['company_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
