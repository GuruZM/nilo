<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Services\DocumentRenderer;
use App\Services\Documents\CreateCreditNote;
use App\Services\Documents\CreditNoteHeadroom;
use App\Services\InvoiceSettlement;
use App\Support\DocumentRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Credits raised against an invoice: written, issued, voided and printed.
 *
 * The invoice status is never written here. {@see InvoiceSettlement} derives it
 * from the ledger, so cash and credit cannot disagree about whether an invoice
 * is settled.
 */
class CreditNoteController extends Controller
{
    public function __construct(
        private DocumentRenderer $documents,
        private InvoiceSettlement $settlement,
        private CreateCreditNote $creator,
        private CreditNoteHeadroom $headroom,
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

    private function invoiceRule(int $companyId): Exists
    {
        return Rule::exists('invoices', 'id')->where('company_id', $companyId);
    }

    public function index(Request $request): Response
    {
        $companyId = $this->resolveCompanyId($request);

        if (! $companyId) {
            return Inertia::render('CreditNotes/Index', [
                'creditNotes' => [],
                'hasActiveCompany' => false,
            ]);
        }

        $creditNotes = CreditNote::query()
            ->where('company_id', $companyId)
            ->with(['client:id,name', 'invoice:id,number'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (CreditNote $note) => [
                'id' => $note->id,
                'number' => $note->number,
                'client_name' => $note->client?->name,
                'invoice_number' => $note->invoice?->number,
                'issue_date' => $note->issue_date,
                'currency_code' => $note->currency_code,
                'total' => (float) $note->total,
                'status' => $note->status,
                'reason' => $note->reason,
            ]);

        return Inertia::render('CreditNotes/Index', [
            'creditNotes' => $creditNotes,
            'hasActiveCompany' => true,
        ]);
    }

    /**
     * The page is rendered even with no company to render it for. Throwing here
     * would redirect back with an error keyed to a field this form does not
     * have, so nothing would say why the page never opened — the button would
     * simply look broken. {@see CreditNotes/Create} owns the empty state and
     * points at /companies.
     */
    public function create(Request $request): Response
    {
        $companyId = $this->resolveCompanyId($request);

        if (! $companyId) {
            return Inertia::render('CreditNotes/Create', [
                'invoices' => [],
                'defaultCurrencyCode' => Company::defaultCurrencyCodeFor(
                    null,
                    $request->user()?->current_currency_code,
                ),
                'hasActiveCompany' => false,
            ]);
        }

        $invoices = Invoice::query()
            ->where('company_id', $companyId)
            ->whereNotIn('status', [Invoice::STATUS_VOID])
            ->with(['client:id,name'])
            ->orderByDesc('issue_date')
            ->get(['id', 'number', 'client_id', 'currency_code', 'total', 'status'])
            ->map(fn (Invoice $invoice) => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'client_name' => $invoice->client?->name,
                'currency_code' => $invoice->currency_code,
                'total' => (float) $invoice->total,
                'balance_due' => $this->settlement->balanceDue($invoice),
            ]);

        return Inertia::render('CreditNotes/Create', [
            'invoices' => $invoices,
            'defaultCurrencyCode' => Company::defaultCurrencyCodeFor(
                $companyId,
                $request->user()?->current_currency_code,
            ),
            'hasActiveCompany' => true,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $companyId = $this->companyId($request);
        $user = $request->user();

        /**
         * `currency_code` is absent on purpose. A credit note is denominated by
         * the invoice it credits, so there is no currency here for the client to
         * submit — validating one would imply otherwise. {@see InvoiceSettlement}
         * sums credit totals against the invoice total without conversion, so a
         * note in a different currency would subtract a foreign number from a
         * local balance and could wrongly settle the invoice.
         */
        $data = $request->validate(DocumentRules::creditNote($companyId));

        $invoice = Invoice::query()
            ->where('id', (int) $data['invoice_id'])
            ->where('company_id', $companyId)
            ->firstOrFail();

        /** Checked against the same figure that will be written, not a second one. */
        $this->headroom->guard($invoice, $this->creator->totals($data)['total'], $data['status']);

        $note = $this->creator->handle($companyId, $user, $invoice, $data);

        return redirect()
            ->route('credit-notes.show', $note)
            ->with('success', 'Credit note created.');
    }

    public function show(Request $request, CreditNote $creditNote): Response
    {
        $companyId = $this->companyId($request);

        abort_unless((int) $creditNote->company_id === $companyId, 403);

        $creditNote->load([
            'client:id,company_id,name,email,contact_person',
            'invoice:id,number,total,status',
            'items',
        ]);

        return Inertia::render('CreditNotes/show', [
            'creditNote' => [
                'id' => $creditNote->id,
                'number' => $creditNote->number,
                'title' => $creditNote->title,
                'reference' => $creditNote->reference,
                'reason' => $creditNote->reason,
                'status' => $creditNote->status,
                'issue_date' => $creditNote->issue_date,
                'currency_code' => $creditNote->currency_code,

                'subtotal' => (float) $creditNote->subtotal,
                'discount_total' => (float) $creditNote->discount_total,
                'credit_note_discount' => (float) ($creditNote->credit_note_discount ?? 0),
                'tax_percent' => (float) ($creditNote->tax_percent ?? 0),
                'tax_total' => (float) $creditNote->tax_total,
                'total' => (float) $creditNote->total,

                'notes' => $creditNote->notes,
                'terms' => $creditNote->terms,

                'client' => $creditNote->client,
                'invoice' => $creditNote->invoice,
                'items' => $creditNote->items,
            ],
        ]);
    }

    /**
     * Issuing and voiding are the two moves that change what the invoice owes,
     * so both re-derive the settlement afterwards. Issuing is also re-guarded:
     * the balance may have been settled some other way since the note was
     * written.
     */
    public function updateStatus(Request $request, CreditNote $creditNote): RedirectResponse
    {
        $companyId = $this->companyId($request);

        abort_unless((int) $creditNote->company_id === $companyId, 403);

        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(['draft', 'issued', 'void'])],
        ]);

        if ($creditNote->status === $data['status']) {
            return back()->with('info', 'Credit note is already '.$data['status'].'.');
        }

        $invoice = $creditNote->invoice;

        $this->headroom->guard($invoice, (float) $creditNote->total, $data['status'], $creditNote->id);

        DB::transaction(function () use ($creditNote, $data, $invoice): void {
            $creditNote->update(['status' => $data['status']]);
            $this->settlement->sync($invoice->fresh());
        });

        return back()->with('success', 'Credit note is now '.$data['status'].'.');
    }

    public function preview(Request $request, CreditNote $creditNote)
    {
        abort_unless((int) $creditNote->company_id === $this->companyId($request), 403);

        return response($this->documents->html($creditNote, 'preview'));
    }

    public function print(Request $request, CreditNote $creditNote)
    {
        abort_unless((int) $creditNote->company_id === $this->companyId($request), 403);

        return response()->view(
            'invoices.templates.default',
            $this->documents->viewData($creditNote, 'print') + [
                'autoPrint' => $request->boolean('download'),
            ]
        );
    }
}
