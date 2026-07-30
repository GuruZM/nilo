<?php

namespace App\Http\Controllers;

use App\Enums\DocumentType;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Services\DocumentRenderer;
use App\Services\InvoiceSettlement;
use App\Services\TemplateProvisioner;
use App\Support\DocumentNumber;
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

    private function invoiceRule(int $companyId): Exists
    {
        return Rule::exists('invoices', 'id')->where('company_id', $companyId);
    }

    /**
     * Totals for a credit note.
     *
     * Identical arithmetic to {@see QuotationController::computeTotals()} —
     * prices are tax-inclusive, discounts come off the gross, and tax is carved
     * back out by subtraction so subtotal and tax always reconcile to the
     * total. The three must not drift apart.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array{items: array<int, array<string, mixed>>, subtotal: float, credit_note_discount: float, discount_total: float, tax_percent: float, tax_total: float, total: float}
     */
    private function computeTotals(array $items, float $creditNoteDiscount, float $taxPercent): array
    {
        $itemsGross = 0.0;
        $lineDiscountTotal = 0.0;

        foreach ($items as $i => $row) {
            $qty = (float) $row['quantity'];
            $price = (float) $row['unit_price'];
            $discount = (float) ($row['discount'] ?? 0);

            $lineBase = $qty * $price;

            $items[$i]['discount'] = $discount;
            $items[$i]['tax'] = 0;
            $items[$i]['line_total'] = max(0, $lineBase - $discount);
            $items[$i]['sort_order'] = $i;

            $itemsGross += $lineBase;
            $lineDiscountTotal += $discount;
        }

        $discountTotal = $lineDiscountTotal + $creditNoteDiscount;

        $total = round(max(0, $itemsGross - $discountTotal), 2);
        $subtotal = round($total / (1 + ($taxPercent / 100)), 2);
        $taxTotal = round($total - $subtotal, 2);

        return [
            'items' => $items,
            'subtotal' => $subtotal,
            'credit_note_discount' => $creditNoteDiscount,
            'discount_total' => $discountTotal,
            'tax_percent' => $taxPercent,
            'tax_total' => $taxTotal,
            'total' => $total,
        ];
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

    public function create(Request $request): Response
    {
        $companyId = $this->companyId($request);

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
        $data = $request->validate([
            'invoice_id' => ['required', 'integer', $this->invoiceRule($companyId)],
            'issue_date' => ['required', 'date'],
            'status' => ['required', Rule::in(['draft', 'issued', 'void'])],
            'reason' => ['nullable', 'string', 'max:190'],
            'title' => ['nullable', 'string', 'max:190'],
            'reference' => ['nullable', 'string', 'max:190'],
            'notes' => ['nullable', 'string'],
            'terms' => ['nullable', 'string'],
            'credit_note_discount' => ['nullable', 'numeric', 'min:0'],
            'tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $invoice = Invoice::query()
            ->where('id', (int) $data['invoice_id'])
            ->where('company_id', $companyId)
            ->firstOrFail();

        $totals = $this->computeTotals(
            $data['items'],
            (float) ($data['credit_note_discount'] ?? 0),
            (float) ($data['tax_percent'] ?? 0),
        );

        $this->guardCreditFits($invoice, $totals['total'], $data['status']);

        $note = DB::transaction(function () use ($companyId, $user, $data, $totals, $invoice): CreditNote {
            $note = CreditNote::query()->create([
                'company_id' => $companyId,
                'client_id' => $invoice->client_id,
                'invoice_id' => $invoice->id,

                /**
                 * Provisioning happens inside the transaction on purpose: it may
                 * create the company's first credit note template, and a rolled
                 * back note must not leave that row behind.
                 */
                'credit_note_template_id' => $this->templates
                    ->forCompany($companyId, DocumentType::CreditNote)->id,

                'created_by' => $user?->id,
                'number' => DocumentNumber::nextFor(CreditNote::class, $companyId, DocumentType::CreditNote),
                'reference' => $data['reference'] ?? null,
                'title' => $data['title'] ?? null,
                'reason' => $data['reason'] ?? null,
                'issue_date' => $data['issue_date'],

                /** Never the submitted currency — a credit must match what it credits. */
                'currency_code' => $invoice->currency_code,

                'subtotal' => $totals['subtotal'],
                'credit_note_discount' => $totals['credit_note_discount'],
                'discount_total' => $totals['discount_total'],
                'tax_percent' => $totals['tax_percent'],
                'tax_total' => $totals['tax_total'],
                'total' => $totals['total'],

                'status' => $data['status'],
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
            ]);

            $note->items()->createMany($totals['items']);

            $this->settlement->sync($invoice->fresh());

            return $note;
        });

        return redirect()
            ->route('credit-notes.show', $note)
            ->with('success', 'Credit note created.');
    }

    /**
     * A credit that is about to be applied may not exceed what the invoice is
     * still owed, or the ledger would show a negative balance. Drafts and voids
     * are let through because they move nothing.
     *
     * `$ignoreNoteId` covers the case where the note under consideration is
     * *already* counted in the balance: its own total is added back so it cannot
     * block itself. The `applied()` scope is what makes that safe — a draft or
     * voided note sums to zero and nothing is added back, which is right,
     * because neither was ever subtracted.
     *
     * The balance is read outside the caller's transaction, so two credits
     * raised at the same instant can both see the same room and both be
     * accepted. That is the same concurrency gap as the payment cap in
     * {@see InvoicePaymentController::store()}, and gets the same v1 answer:
     * noted, not locked.
     */
    private function guardCreditFits(Invoice $invoice, float $total, string $status, ?int $ignoreNoteId = null): void
    {
        if (! in_array($status, CreditNote::APPLIED_STATUSES, true)) {
            return;
        }

        $available = $this->settlement->balanceDue($invoice);

        if ($ignoreNoteId) {
            $existing = (float) $invoice->creditNotes()
                ->applied()
                ->where('id', $ignoreNoteId)
                ->sum('total');

            $available = round($available + $existing, 2);
        }

        /**
         * Both figures are already rounded to the cent, so this epsilon only
         * absorbs binary float noise — it is two orders of magnitude below the
         * smallest real amount, and a credit one cent over is still refused.
         */
        if ($total > $available + 0.001) {
            throw ValidationException::withMessages([
                'items' => 'This credit of '.number_format($total, 2).' is more than the '
                    .number_format($available, 2).' still outstanding on invoice '
                    .$invoice->number.'.',
            ]);
        }
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

        $this->guardCreditFits($invoice, (float) $creditNote->total, $data['status'], $creditNote->id);

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
