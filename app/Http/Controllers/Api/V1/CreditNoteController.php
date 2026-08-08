<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\PaginatesApiResults;
use App\Http\Controllers\Api\V1\Concerns\ResolvesCompany;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CreditNoteResource;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Services\DocumentRenderer;
use App\Services\Documents\CreateCreditNote;
use App\Services\Documents\CreditNoteHeadroom;
use App\Services\InvoiceSettlement;
use App\Support\DocumentRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Credits raised against an invoice.
 *
 * The invoice status is never written here — {@see InvoiceSettlement} derives
 * it from the ledger, so cash and credit cannot disagree about whether an
 * invoice is settled. Credit notes are not metered by any plan limit, matching
 * the web app.
 */
class CreditNoteController extends Controller
{
    use PaginatesApiResults, ResolvesCompany;

    public function __construct(
        private CreateCreditNote $creator,
        private CreditNoteHeadroom $headroom,
        private InvoiceSettlement $settlement,
        private DocumentRenderer $documents,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $notes = CreditNote::query()
            ->where('company_id', $this->companyId($request))
            ->with(['client:id,company_id,name', 'invoice:id,number'])
            ->when($request->string('status')->value(), fn ($query, $status) => $query->where('status', $status))
            ->when($request->integer('invoice_id'), fn ($query, $invoiceId) => $query->where('invoice_id', $invoiceId))
            ->orderByDesc('created_at')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return CreditNoteResource::collection($notes);
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);

        $data = $request->validate(DocumentRules::creditNote($companyId));

        $invoice = Invoice::query()
            ->where('id', (int) $data['invoice_id'])
            ->where('company_id', $companyId)
            ->firstOrFail();

        /** Checked against the same figure that will be written, not a second one. */
        $this->headroom->guard($invoice, $this->creator->totals($data)['total'], $data['status']);

        $note = $this->creator->handle($companyId, $request->user(), $invoice, $data);

        return CreditNoteResource::make($this->loadForShow($note))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, CreditNote $creditNote): CreditNoteResource
    {
        $this->guardCompany($request, $creditNote);

        return CreditNoteResource::make($this->loadForShow($creditNote));
    }

    /**
     * Moving a draft to issued applies it to the invoice, so the headroom has
     * to be rechecked — the invoice may have been settled in the meantime.
     */
    public function updateStatus(Request $request, CreditNote $creditNote): CreditNoteResource
    {
        $this->guardCompany($request, $creditNote);

        $data = $request->validate([
            'status' => ['required', Rule::in(['draft', 'issued', 'void'])],
        ]);

        $invoice = $creditNote->invoice;

        $this->headroom->guard($invoice, (float) $creditNote->total, $data['status'], $creditNote->id);

        DB::transaction(function () use ($creditNote, $data, $invoice): void {
            $creditNote->update(['status' => $data['status']]);

            $this->settlement->sync($invoice->fresh());
        });

        return CreditNoteResource::make($this->loadForShow($creditNote->fresh()));
    }

    public function pdf(Request $request, CreditNote $creditNote): Response
    {
        $this->guardCompany($request, $creditNote);

        return response($this->documents->pdf($creditNote), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->documents->filename($creditNote).'"',
        ]);
    }

    private function loadForShow(CreditNote $note): CreditNote
    {
        return $note->load([
            'client:id,company_id,name,email,contact_person,phone,address',
            'invoice:id,number',
            'items',
        ]);
    }
}
