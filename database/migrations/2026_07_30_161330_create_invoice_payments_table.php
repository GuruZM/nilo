<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Money received against an invoice.
     *
     * Deliberately not called `payments` — that table already holds Nilo's own
     * subscription billing. This one is the customer-facing ledger.
     */
    public function up(): void
    {
        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('receipt_number')->nullable();

            $table->decimal('amount', 14, 2);
            $table->string('currency_code', 3);

            $table->date('paid_on');
            $table->string('method'); // cash|bank_transfer|mobile_money|cheque|card|other
            $table->string('reference')->nullable(); // cheque no, transaction id

            /**
             * No `notes` column by design. The printed receipt's notes line is
             * computed from the remaining balance (see InvoicePayment), and a
             * stored column would let a caller shadow that figure.
             */
            $table->decimal('exchange_rate_to_base', 18, 8)->nullable();
            $table->timestamp('exchange_rate_fetched_at')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'paid_on']);
            $table->index(['invoice_id', 'paid_on']);
            $table->unique(['company_id', 'receipt_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');
    }
};
