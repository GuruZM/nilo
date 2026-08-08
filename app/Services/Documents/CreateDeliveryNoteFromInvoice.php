<?php

namespace App\Services\Documents;

use App\Enums\DocumentType;
use App\Models\DeliveryNote;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use App\Services\TemplateProvisioner;
use App\Support\DocumentNumber;
use Illuminate\Support\Facades\DB;

/**
 * Raises the delivery note for an invoice.
 *
 * The lines are copied rather than referenced on purpose: a delivery note
 * records what actually left the building, so editing the invoice afterwards
 * must not rewrite history on a sheet somebody has already signed.
 */
class CreateDeliveryNoteFromInvoice
{
    public function __construct(private TemplateProvisioner $templates) {}

    public function handle(int $companyId, ?User $user, Invoice $invoice): DeliveryNote
    {
        $invoice->loadMissing(['items', 'client']);

        return DB::transaction(function () use ($companyId, $user, $invoice): DeliveryNote {
            $note = DeliveryNote::query()->create([
                'company_id' => $companyId,
                'client_id' => $invoice->client_id,
                'invoice_id' => $invoice->id,
                'delivery_note_template_id' => $this->templates
                    ->forCompany($companyId, DocumentType::DeliveryNote)->id,
                'created_by' => $user?->id,
                'number' => DocumentNumber::nextFor(DeliveryNote::class, $companyId, DocumentType::DeliveryNote),
                'reference' => $invoice->number,
                'issue_date' => now()->toDateString(),
                'currency_code' => $invoice->currency_code,
                'deliver_to' => $invoice->client?->name,
                'delivery_address' => $invoice->client?->address,
                'status' => 'draft',
            ]);

            $note->items()->createMany(
                $invoice->items->map(fn (InvoiceItem $item, int $index) => [
                    'description' => $item->description,
                    'unit' => $item->unit,
                    'quantity' => $item->quantity,
                    'sort_order' => $index,
                ])->all()
            );

            $invoice->update(['has_delivery_note' => true]);

            return $note;
        });
    }
}
