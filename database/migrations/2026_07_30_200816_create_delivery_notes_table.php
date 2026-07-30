<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Goods dispatched against an invoice.
     *
     * Carries a currency_code only so the shared sheet can resolve a currency
     * without a special case — no money is ever printed on this document.
     */
    public function up(): void
    {
        Schema::create('delivery_notes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('delivery_note_template_id')->nullable()
                ->constrained('invoice_templates')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('number')->nullable();
            $table->string('reference')->nullable();

            $table->date('issue_date');
            $table->date('delivery_date')->nullable();
            $table->string('currency_code', 3);

            $table->string('deliver_to')->nullable();
            $table->text('delivery_address')->nullable();
            $table->string('received_by')->nullable();
            $table->date('received_on')->nullable();

            $table->string('status')->default('draft'); // draft|dispatched|delivered

            $table->text('notes')->nullable();
            $table->text('terms')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'delivery_date']);
            $table->index(['invoice_id']);
            $table->unique(['company_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_notes');
    }
};
