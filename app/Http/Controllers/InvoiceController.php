<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\InvoiceTemplate;
use App\Services\DocumentPrerequisites;
use App\Services\Documents\CreateInvoice;
use App\Services\Documents\SendInvoiceToClient;
use App\Services\InvoiceDocumentRenderer;
use App\Services\InvoiceSettlement;
use App\Services\SubscriptionLimitService;
use App\Support\DocumentRules;
use App\Support\DocumentTotals;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class InvoiceController extends Controller
{
    public function __construct(
        private InvoiceDocumentRenderer $documents,
        private InvoiceSettlement $settlement,
        private CreateInvoice $creator,
        private SendInvoiceToClient $sender,
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

    /**
     * Rejects the write before any validation runs when the company, template,
     * client or currency prerequisites are not met. The UI hides the form in this
     * state, so reaching here means the gate was bypassed.
     */
    private function guardPrerequisites(?int $companyId): void
    {
        $prerequisites = DocumentPrerequisites::forInvoices($companyId);

        if ($prerequisites->canCreate()) {
            return;
        }

        throw ValidationException::withMessages([
            'prerequisites' => $prerequisites->blockers()[0]['description'],
        ]);
    }

    /**
     * Scopes the template to the active company and the invoice type so a template
     * id belonging to another company (or to quotations) cannot be submitted.
     */
    private function templateRule(int $companyId): Exists
    {
        return Rule::exists('invoice_templates', 'id')
            ->where('company_id', $companyId)
            ->where('type', 'invoice');
    }

    private function clientRule(int $companyId): Exists
    {
        return Rule::exists('clients', 'id')->where('company_id', $companyId);
    }

    private function invoiceTemplateDefaults(): array
    {
        return $this->documents->templateDefaults();
    }

    private function resolveInvoiceTemplate(int $companyId, ?int $templateId = null): ?InvoiceTemplate
    {
        return $this->documents->resolveTemplate($companyId, $templateId);
    }

    private function normalizedTemplateSettings(?InvoiceTemplate $template): array
    {
        return $this->documents->normalizedSettings($template);
    }

    /**
     * Totals for an invoice.
     *
     * Prices are entered tax-inclusive: what you type on a line is what the
     * client pays for it. Discounts come off that gross figure, then the tax
     * is carved back out of what remains, so `subtotal` is the net (tax
     * exclusive) amount and `total` is the gross (tax inclusive) one.
     *
     *   items_gross   Σ qty × price           5000.00
     *   − discounts                              0.00
     *   = total       gross, tax inclusive    5000.00
     *     subtotal    total ÷ (1 + rate)      4310.34
     *     tax_total   total − subtotal         689.66
     *
     * Tax is derived by subtraction rather than multiplication so subtotal and
     * tax always add back to exactly the total, with no rounding drift.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array{items: array<int, array<string, mixed>>, items_gross: float, subtotal: float, line_discount_total: float, invoice_discount: float, discount_total: float, tax_percent: float, tax_total: float, total: float}
     */
    private function computeTotals(array $items, float $invoiceDiscount, float $taxPercent): array
    {
        $totals = DocumentTotals::compute($items, $invoiceDiscount, $taxPercent);

        /** The shared class names the whole-document discount generically. */
        $totals['invoice_discount'] = $totals['document_discount'];
        unset($totals['document_discount']);

        return $totals;
    }

    public function index(Request $request)
    {
        $companyId = $this->resolveCompanyId($request);
        $prerequisites = DocumentPrerequisites::forInvoices($companyId);

        if (! $companyId) {
            return Inertia::render('Invoices/Index', [
                'invoices' => [],
                'clients' => [],
                'hasActiveCompany' => false,
                'prerequisites' => $prerequisites->toArray(),
            ]);
        }

        $invoices = Invoice::query()
            ->where('company_id', $companyId)
            ->with(['client:id,name', 'items:id,invoice_id,line_total'])
            ->orderByDesc('created_at')
            ->get([
                'id', 'number', 'client_id', 'issue_date', 'due_date', 'currency_code', 'total', 'status', 'is_recurring', 'created_at',
            ])
            ->map(fn (Invoice $invoice) => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'client_id' => $invoice->client_id,
                'client_name' => $invoice->client?->name,
                'issue_date' => $invoice->issue_date,
                'due_date' => $invoice->due_date,
                'currency_code' => $invoice->currency_code,
                'total' => (float) $invoice->total,
                'status' => $invoice->status,
                'is_recurring' => (bool) $invoice->is_recurring,
                'created_at' => $invoice->created_at,
            ]);

        // Minimal client list for display
        $clients = Client::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Invoices/Index', [
            'invoices' => $invoices,
            'clients' => $clients,
            'hasActiveCompany' => true,
            'prerequisites' => $prerequisites->toArray(),
        ]);
    }

    public function previewNew(Request $request)
    {
        $companyId = $this->companyId($request);
        $this->guardPrerequisites($companyId);
        $user = $request->user();

        $data = $request->validate([
            'client_id' => ['required', 'integer', $this->clientRule($companyId)],
            'invoice_template_id' => ['required', 'integer', $this->templateRule($companyId)],
            'title' => ['nullable', 'string', 'max:190'],
            'reference' => ['nullable', 'string', 'max:190'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'currency_code' => ['required', 'string', 'size:3', Rule::exists('currencies', 'code')],
            'status' => ['required', Rule::in(['pending', 'paid'])],
            'notes' => ['nullable', 'string'],
            'terms' => ['nullable', 'string'],
            'invoice_discount' => ['nullable', 'numeric', 'min:0'],
            'tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],

            'embed' => ['nullable', 'boolean'],
        ]);

        $client = Client::query()
            ->where('id', (int) $data['client_id'])
            ->where('company_id', $companyId)
            ->first();

        if (! $client) {
            throw ValidationException::withMessages([
                'client_id' => 'That client is not in the active company.',
            ]);
        }

        $template = null;
        if (! empty($data['invoice_template_id'])) {
            $template = InvoiceTemplate::query()
                ->where('id', (int) $data['invoice_template_id'])
                ->where('company_id', $companyId)
                ->where('type', 'invoice')
                ->first();

            if (! $template) {
                throw ValidationException::withMessages([
                    'invoice_template_id' => 'That template is not in the active company.',
                ]);
            }
        } else {
            $template = $this->resolveInvoiceTemplate($companyId, null);
        }

        $currency = Currency::query()
            ->where('code', strtoupper($data['currency_code']))
            ->first();

        $totals = $this->computeTotals(
            $data['items'],
            (float) ($data['invoice_discount'] ?? 0),
            (float) ($data['tax_percent'] ?? 0),
        );

        $items = $totals['items'];

        $company = $user->companies()
            ->where('companies.id', $companyId)
            ->first(['companies.id', 'companies.name']);

        // ✅ IMPORTANT: point to a real blade view that exists
        // resources/views/invoices/templates/default.blade.php
        $view = 'invoices.templates.default';

        $settings = $this->normalizedTemplateSettings($template);
        if (! $template) {
            $template = new InvoiceTemplate(['settings' => $settings]);
        }

        $html = View::make($view, [
            'company' => $company,
            'client' => $client,
            'template' => $template,
            'currency' => $currency,
            'invoice' => [
                'number' => 'PREVIEW',
                'title' => $data['title'] ?? null,
                'reference' => $data['reference'] ?? null,
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'] ?? null,
                'status' => $data['status'],
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
                'invoice_discount' => $totals['invoice_discount'],
                'subtotal' => $totals['subtotal'],
                'discount_total' => $totals['discount_total'],
                'tax_percent' => $totals['tax_percent'],
                'tax_total' => $totals['tax_total'],
                'total' => $totals['total'],
            ],
            'items' => $items,
            'settings' => $settings,
            'mode' => filter_var($data['embed'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 'embed' : 'preview',
        ])->render();

        return response($html);
    }

    public function updateStatus(Request $request, Invoice $invoice)
    {
        $companyId = $this->companyId($request);

        // ✅ Ensure invoice belongs to active company
        if ((int) $invoice->company_id !== (int) $companyId) {
            throw ValidationException::withMessages([
                'invoice' => 'Invoice not found in the active company.',
            ]);
        }

        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(['pending', 'paid', 'partially_paid'])],
        ]);

        // ✅ No-op protection
        if ($invoice->status === $data['status']) {
            return back()->with('info', 'Invoice status is already set to '.$data['status'].'.');
        }

        $update = ['status' => $data['status']];

        // ✅ Optional: paid_at support if you added the column
        if ($data['status'] === 'paid') {
            $update['paid_at'] = now();
        } else {
            $update['paid_at'] = null;
        }

        $invoice->update($update);

        return back()->with('success', 'Invoice status updated to '.$data['status'].'.');
    }

    public function create(Request $request)
    {
        $companyId = $this->resolveCompanyId($request);
        $prerequisites = DocumentPrerequisites::forInvoices($companyId);

        if (! $prerequisites->canCreate()) {
            return Inertia::render('Invoices/Create', [
                'clients' => [],
                'templates' => [],
                'defaultCurrencyCode' => Company::defaultCurrencyCodeFor($companyId, $request->user()?->current_currency_code),
                'hasActiveCompany' => $prerequisites->hasActiveCompany(),
                'prerequisites' => $prerequisites->toArray(),
            ]);
        }

        $clients = Client::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'contact_person']);

        $templates = InvoiceTemplate::query()
            ->where('company_id', $companyId)
            ->where('type', 'invoice')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'name', 'is_default']);

        // currency default: the company's billing currency, else the user's active currency, else ZMW
        $activeCurrencyCode = Company::defaultCurrencyCodeFor($companyId, $request->user()?->current_currency_code);

        return Inertia::render('Invoices/Create', [
            'clients' => $clients,
            'templates' => $templates,
            'defaultCurrencyCode' => $activeCurrencyCode,
            'hasActiveCompany' => true,
            'prerequisites' => $prerequisites->toArray(),
            'limitNotice' => $request->session()->get('limit_notice'),
        ]);
    }

    public function preview(Request $request, Invoice $invoice)
    {
        $companyId = $this->companyId($request);

        abort_unless((int) $invoice->company_id === (int) $companyId, 403);

        $invoice->load(['client', 'items', 'template', 'company']);

        $currency = Currency::where('code', $invoice->currency_code)->first();

        $template = $this->resolveInvoiceTemplate(
            $companyId,
            (int) ($invoice->invoice_template_id ?? 0)
        );
        $settings = $this->normalizedTemplateSettings($template);
        if (! $template) {
            $template = new InvoiceTemplate(['settings' => $settings]);
        }

        $view = 'invoices.templates.default';

        $html = View::make($view, [
            'company' => $invoice->company,
            'client' => $invoice->client,
            'template' => $template,
            'currency' => $currency,
            'invoice' => $invoice,
            'items' => $invoice->items,
            'settings' => $settings,
            'mode' => 'preview',
        ])->render();

        return response($html);
    }

    public function store(Request $request)
    {
        $companyId = $this->companyId($request);
        $this->guardPrerequisites($companyId);
        $user = $request->user();
        $limiter = new SubscriptionLimitService($user);

        if (! $limiter->canCreateInvoice($companyId)) {
            return back()->with('limit_notice', $limiter->limitNotice('invoices'));
        }

        try {
            $data = $request->validate(DocumentRules::invoice($companyId));

            // ✅ Ensure client belongs to active company
            $clientOk = Client::query()
                ->where('id', (int) $data['client_id'])
                ->where('company_id', $companyId)
                ->exists();

            if (! $clientOk) {
                throw ValidationException::withMessages([
                    'client_id' => 'That client is not in the active company.',
                ]);
            }

            // ✅ Ensure template belongs to active company (if provided)
            if (! empty($data['invoice_template_id'])) {
                $tplOk = InvoiceTemplate::query()
                    ->where('id', (int) $data['invoice_template_id'])
                    ->where('company_id', $companyId)
                    ->where('type', 'invoice')
                    ->exists();

                if (! $tplOk) {
                    throw ValidationException::withMessages([
                        'invoice_template_id' => 'That template is not in the active company.',
                    ]);
                }
            }

            $invoice = $this->creator->handle($companyId, $user, $data);

            $delivery = $this->sender->afterCreate($invoice, (bool) ($data['send_to_client'] ?? false));

            // ✅ Inertia-friendly redirect with flash (this is what makes onSuccess + global flash work)
            return redirect("/invoices/{$invoice->id}")
                ->with($delivery['level'], $delivery['message'])
                ->with('invoice_created', true);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Invoice store failed', [
                'user_id' => $user?->id,
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'invoice' => 'Failed to create invoice. Please try again.',
            ]);
        }
    }

    public function print(Request $request, Invoice $invoice)
    {
        $companyId = $this->companyId($request);

        abort_unless((int) $invoice->company_id === (int) $companyId, 403);

        $invoice->load(['client', 'items', 'company', 'template']);

        $currency = Currency::query()
            ->where('code', $invoice->currency_code)
            ->first();

        $template = $this->resolveInvoiceTemplate(
            $companyId,
            (int) ($invoice->invoice_template_id ?? 0)
        );
        $settings = $this->normalizedTemplateSettings($template);
        if (! $template) {
            $template = new InvoiceTemplate(['settings' => $settings]);
        }

        // ✅ normal HTML view
        return response()->view('invoices.templates.default', [
            'company' => $invoice->company,
            'client' => $invoice->client,
            'template' => $template,          // ✅ use the effective/forced template
            'currency' => $currency,
            'invoice' => $invoice,
            'items' => $invoice->items,
            'settings' => $settings,          // ✅ normalized builder-shape array
            'mode' => 'print',
            'autoPrint' => $request->boolean('download'),
        ]);
    }

    public function show(Request $request, Invoice $invoice)
    {
        $companyId = $this->companyId($request);

        if ((int) $invoice->company_id !== (int) $companyId) {
            throw ValidationException::withMessages([
                'invoice' => 'Invoice not found in the active company.',
            ]);
        }

        $invoice->load([
            'client:id,company_id,name,email,contact_person',
            'items',
            'payments.recorder:id,name',
            'quotation:id,number',
        ]);

        return Inertia::render('Invoices/show', [
            /** Only true on the redirect straight after creating it. */
            'justCreated' => (bool) $request->session()->get('invoice_created', false),

            'invoice' => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'title' => $invoice->title,
                'reference' => $invoice->reference,
                'status' => $invoice->status,
                'issue_date' => $invoice->issue_date,
                'due_date' => $invoice->due_date,
                'currency_code' => $invoice->currency_code,

                'subtotal' => (float) $invoice->subtotal,
                'discount_total' => (float) $invoice->discount_total,
                'invoice_discount' => (float) ($invoice->invoice_discount ?? 0),

                /** `discount_total` carries both; split it so neither is shown twice. */
                'line_discount' => (float) $invoice->discount_total
                    - (float) ($invoice->invoice_discount ?? 0),

                'tax_percent' => (float) ($invoice->tax_percent ?? 0),
                'tax_total' => (float) $invoice->tax_total,
                'total' => (float) $invoice->total,

                'notes' => $invoice->notes,
                'terms' => $invoice->terms,

                'client' => $invoice->client,
                'items' => $invoice->items,

                /** Present only on an invoice raised from a quotation. */
                'quotation' => $invoice->quotation
                    ? ['id' => $invoice->quotation->id, 'number' => $invoice->quotation->number]
                    : null,

                'amount_paid' => $this->settlement->amountPaid($invoice),
                'balance_due' => $this->settlement->balanceDue($invoice),

                /** Newest first — {@see Invoice::payments()} orders the ledger. */
                'payments' => $invoice->payments->map(fn (InvoicePayment $payment) => [
                    'id' => $payment->id,
                    'receipt_number' => $payment->receipt_number,
                    'amount' => (float) $payment->amount,
                    'paid_on' => $payment->paid_on?->toDateString(),
                    'method' => $payment->method,
                    'method_label' => $payment->methodLabel(),
                    'reference' => $payment->reference,
                    'recorded_by' => $payment->recorder?->name,
                ]),
            ],

            'paymentMethods' => collect(InvoicePayment::METHODS)
                ->map(fn (string $method) => [
                    'value' => $method,
                    'label' => (new InvoicePayment(['method' => $method]))->methodLabel(),
                ])
                ->all(),
        ]);
    }

    // edit/update/destroy next — we’ll wire after Create is perfect
}
