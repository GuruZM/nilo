<?php

namespace App\Http\Controllers;

use App\Models\DeliveryNote;
use App\Models\Invoice;
use App\Services\DocumentRenderer;
use App\Services\Documents\CreateDeliveryNoteFromInvoice;
use App\Support\DocumentRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryNoteController extends Controller
{
    public function __construct(
        private DocumentRenderer $documents,
        private CreateDeliveryNoteFromInvoice $creator,
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

        $note = $this->creator->handle($companyId, $request->user(), $invoice);

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
                /**
                 * Sent as `Y-m-d`, not as a serialised Carbon. The sign-off form
                 * binds these to `<input type="date">`, which silently renders
                 * blank for anything other than that exact shape.
                 */
                'issue_date' => $deliveryNote->issue_date?->toDateString(),
                'delivery_date' => $deliveryNote->delivery_date?->toDateString(),
                'deliver_to' => $deliveryNote->deliver_to,
                'delivery_address' => $deliveryNote->delivery_address,
                'received_by' => $deliveryNote->received_by,
                'received_on' => $deliveryNote->received_on?->toDateString(),
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

        $data = $request->validate(DocumentRules::deliveryNoteUpdate());

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
