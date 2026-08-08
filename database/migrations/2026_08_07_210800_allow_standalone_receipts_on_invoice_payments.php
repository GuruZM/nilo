<?php

use App\Models\InvoicePayment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lets a receipt stand on its own.
     *
     * Until now every receipt was the printed face of a payment against an
     * invoice. Money is also received where no invoice was ever raised — a
     * deposit, a cash sale, payment on delivery of a project — and refusing to
     * issue a receipt for it does not stop the payment, it only leaves the
     * client without proof of it.
     *
     * The rows stay in this table rather than moving to a `receipts` table so
     * that both kinds draw from one `RCP-` sequence. {@see App\Support\DocumentNumber}
     * finds the next number by scanning a single model class, so a second table
     * issuing the same prefix would hand out a number this one had already used
     * and lose the race against the unique index.
     */
    public function up(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table) {
            /**
             * Who the money came from, when there is no invoice to ask. Null on
             * invoice-backed rows, which reach the client through the invoice —
             * copying it here would give the two a way to disagree.
             */
            $table->foreignId('client_id')->nullable()->after('invoice_id')
                ->constrained()->nullOnDelete();

            /**
             * What the money was for. A standalone receipt has no invoice number
             * to name on its single printed line, so this fills that slot.
             */
            $table->string('description')->nullable()->after('reference');

            $table->index(['company_id', 'client_id']);
        });

        /**
         * On its own statement: SQLite executes a column change by rebuilding
         * the table, and it must rebuild one that already has the columns above.
         *
         * Changed rather than dropped and re-added because SQLite cannot drop a
         * foreign key at all. The original attributes are restated because
         * anything omitted from a `change()` is dropped.
         */
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->foreignId('invoice_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        /**
         * A receipt with no invoice cannot be represented once the column is
         * required again, and inventing an invoice for one would be worse than
         * losing it.
         */
        InvoicePayment::query()->whereNull('invoice_id')->delete();

        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->foreignId('invoice_id')->nullable(false)->change();
        });

        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'client_id']);
            $table->dropConstrainedForeignId('client_id');
            $table->dropColumn('description');
        });
    }
};
