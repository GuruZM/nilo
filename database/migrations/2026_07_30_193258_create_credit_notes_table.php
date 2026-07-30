<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A credit raised against an invoice.
     *
     * `invoice_id` is required: a credit note that floats free of the invoice
     * it corrects cannot be reconciled, which is the whole reason it exists.
     */
    public function up(): void
    {
        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('credit_note_template_id')->nullable()
                ->constrained('invoice_templates')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('number')->nullable();
            $table->string('reference')->nullable();
            $table->string('title')->nullable();
            $table->string('reason')->nullable();

            $table->date('issue_date');
            $table->string('currency_code', 3);

            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('credit_note_discount', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('tax_percent', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);

            $table->string('status')->default('draft'); // draft|issued|void

            $table->text('notes')->nullable();
            $table->text('terms')->nullable();

            $table->decimal('exchange_rate_to_base', 18, 8)->nullable();
            $table->timestamp('exchange_rate_fetched_at')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'issue_date']);
            $table->index(['invoice_id', 'status']);
            $table->unique(['company_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_notes');
    }
};
