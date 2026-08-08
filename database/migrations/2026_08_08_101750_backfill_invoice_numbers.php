<?php

use App\Enums\DocumentType;
use App\Models\Invoice;
use App\Support\DocumentNumber;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Gives every invoice issued before there was invoice numbering a number.
     *
     * `number` has been nullable since the table was created and nothing ever
     * filled it, so the UI fell back to printing the autoincrement id — which is
     * global, not per company, so the first invoice a company raised could show
     * as "Invoice #17". Numbering only new invoices would leave the two side by
     * side in one register, so the old rows are numbered too.
     *
     * Oldest first, so the printed order matches the order they were raised.
     * Numbering starts past whatever the company has already issued rather than
     * at one — seeded and imported data carries real numbers, and reusing one
     * would hit the unique index on (company_id, number).
     */
    public function up(): void
    {
        Invoice::query()
            ->whereNull('number')
            ->orderBy('id')
            ->chunkById(200, function ($invoices): void {
                foreach ($invoices as $invoice) {
                    $invoice->forceFill([
                        'number' => DocumentNumber::nextFor(
                            Invoice::class,
                            (int) $invoice->company_id,
                            DocumentType::Invoice,
                        ),
                    ])->saveQuietly();
                }
            });
    }

    /**
     * Irreversible on purpose. Nulling every `INV-` number would also wipe the
     * ones created after this ran, and there is nothing in the row that says
     * which of the two it was.
     */
    public function down(): void
    {
        //
    }
};
