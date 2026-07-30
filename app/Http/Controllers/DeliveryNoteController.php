<?php

namespace App\Http\Controllers;

use App\Enums\DocumentType;
use App\Models\DeliveryNote;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Services\DocumentRenderer;
use App\Services\TemplateProvisioner;
use App\Support\DocumentNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryNoteController extends Controller
{
    public function __construct(
        private DocumentRenderer $documents,
        private TemplateProvisioner $templates,
    ) {}

    private function resolveCompanyId(Request $request): ?int
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        $companyId = (int) ($user->current_company_id ?? 0);

        if ($companyId && $user->isMemberOfCompany($companyId)) {
            return $companyId;
        }

        $fallbackCompanyId = (int) ($user->companies()
            ->orderBy('companies.name')
            ->value('companies.id') ?? 0);

        if ($fallbackCompanyId) {
            $user->forceFill(['current_company_id' => $fallbackCompanyId])->save();
        }

        return $fallbackCompanyId ?: null;
    }

    private function companyId(Request $request): int
    {
        $companyId = $this->resolveCompanyId($request);

        if (! $companyId) {
            throw ValidationException::withMessages([
                'company_id' => 'No active company selected.',
            ]);
        }

        return $companyId;
    }

    public function index(Request $request): Response
    {
        $companyId = $this->resolveCompanyId($request);

        if (! $companyId) {
            return Inertia::render('DeliveryNotes/Index', [
                'deliveryNotes' => [],
                'hasActiveCompany' => false,
            ]);
        }

        $deliveryNotes = DeliveryNote::query()
            ->where('company_id', $companyId)
            ->with(['client:id,name', 'invoice:id,number'])
            ->withCount('items')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (DeliveryNote $note) => [
                'id' => $note->id,
                'number' => $note->number,
                'client_name' => $note->client?->name,
                'invoice_number' => $note->invoice?->number,
                'issue_date' => $note->issue_date,
                'delivery_date' => $note->delivery_date,
                'deliver_to' => $note->deliver_to,
                'received_by' => $note->received_by,
                'status' => $note->status,
                'item_count' => $note->items_count,
            ]);

        return Inertia::render('DeliveryNotes/Index', [
            'deliveryNotes' => $deliveryNotes,
            'hasActiveCompany' => true,
        ]);
    }

    /**
     * Generates a delivery note from an invoice.
     *
     * The lines are copied rather than referenced, because what was dispatched
     * is a fact about a moment in time — editing the invoice afterwards must
     * not rewrite what somebody already signed for.
     */
    public function storeForInvoice(Request $request, Invoice $invoice): RedirectResponse
    {
        $companyId = $this->companyId($request);

        abort_unless((int) $invoice->company_id === $companyId, 403);

        $invoice->loadMissing(['items', 'client']);

        $note = DB::transaction(function () use ($request, $invoice, $companyId): DeliveryNote {
            $note = DeliveryNote::query()->create([
                'company_id' => $companyId,
                'client_id' => $invoice->client_id,
                'invoice_id' => $invoice->id,
                'delivery_note_template_id' => $this->templates
                    ->forCompany($companyId, DocumentType::DeliveryNote)->id,
                'created_by' => $request->user()?->id,
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

        return redirect()
            ->route('delivery-notes.show', $note)
            ->with('success', 'Delivery note '.$note->number.' created.');
    }

    public function show(Request $request, DeliveryNote $deliveryNote): Response
    {
        abort_unless((int) $deliveryNote->company_id === $this->companyId($request), 403);

        $deliveryNote->load([
            'client:id,company_id,name,email,address,contact_person',
            'invoice:id,number',
            'items',
        ]);

        return Inertia::render('DeliveryNotes/show', [
            'deliveryNote' => [
                'id' => $deliveryNote->id,
                'number' => $deliveryNote->number,
                'reference' => $deliveryNote->reference,
                'status' => $deliveryNote->status,
                'issue_date' => $deliveryNote->issue_date,
                'delivery_date' => $deliveryNote->delivery_date,
                'deliver_to' => $deliveryNote->deliver_to,
                'delivery_address' => $deliveryNote->delivery_address,
                'received_by' => $deliveryNote->received_by,
                'received_on' => $deliveryNote->received_on,
                'notes' => $deliveryNote->notes,
                'client' => $deliveryNote->client,
                'invoice' => $deliveryNote->invoice,
                'items' => $deliveryNote->items,
            ],
            'statuses' => DeliveryNote::STATUSES,
        ]);
    }

    public function update(Request $request, DeliveryNote $deliveryNote): RedirectResponse
    {
        abort_unless((int) $deliveryNote->company_id === $this->companyId($request), 403);

        $data = $request->validate([
            'status' => ['required', Rule::in(DeliveryNote::STATUSES)],
            'delivery_date' => ['nullable', 'date'],
            'deliver_to' => ['nullable', 'string', 'max:190'],
            'delivery_address' => ['nullable', 'string', 'max:500'],
            'received_by' => ['nullable', 'string', 'max:190'],
            'received_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $deliveryNote->update($data);

        return back()->with('success', 'Delivery note updated.');
    }

    public function preview(Request $request, DeliveryNote $deliveryNote)
    {
        abort_unless((int) $deliveryNote->company_id === $this->companyId($request), 403);

        return response($this->documents->html($deliveryNote, 'preview'));
    }

    public function print(Request $request, DeliveryNote $deliveryNote)
    {
        abort_unless((int) $deliveryNote->company_id === $this->companyId($request), 403);

        return response()->view(
            'invoices.templates.default',
            $this->documents->viewData($deliveryNote, 'print') + [
                'autoPrint' => $request->boolean('download'),
            ]
        );
    }
}
