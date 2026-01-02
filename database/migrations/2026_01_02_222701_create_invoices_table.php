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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_template_id')->nullable()->constrained('invoice_templates')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            // Invoice identity
            $table->string('number')->nullable(); // We'll generate later: INV-000001, etc.
            $table->string('reference')->nullable(); // PO / internal ref
            $table->string('title')->nullable(); // "Invoice for January Services"

            // Dates
            $table->date('issue_date');
            $table->date('due_date')->nullable();

            // Currency context (use ISO code for simplicity)
            $table->string('currency_code', 3);

            // Totals (store final amounts; compute on server)
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);

            // Status
            $table->string('status')->default('draft'); // draft|sent|paid|void|overdue

            // Delivery note “option”
            $table->boolean('has_delivery_note')->default(false);

            // Recurrence (for cron jobs later)
            $table->boolean('is_recurring')->default(false);
            $table->string('recurrence_frequency')->nullable(); // daily|weekly|monthly|yearly
            $table->unsignedSmallInteger('recurrence_interval')->nullable(); // e.g. every 2 months
            $table->date('recurrence_start_date')->nullable();
            $table->date('recurrence_end_date')->nullable();
            $table->dateTime('next_run_at')->nullable(); // cron uses this

            // Notes / terms
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'issue_date']);
            $table->index(['company_id', 'client_id']);
            $table->index(['company_id', 'currency_code']);
            $table->index(['company_id', 'is_recurring', 'next_run_at']);
            $table->unique(['company_id', 'number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
