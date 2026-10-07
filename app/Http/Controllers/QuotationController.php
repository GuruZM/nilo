<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuotationInvoiceRequest;
use App\Models\Client;
use App\Models\Company;
use App\Models\Currency;
use App\Models\InvoiceTemplate;
use App\Models\Quotation;
use App\Services\DocumentPrerequisites;
use App\Services\Documents\CreateInvoiceFromQuotation;
use App\Services\Documents\CreateQuotation;
use App\Services\Documents\SendInvoiceToClient;
use App\Services\Documents\SendQuotationToClient;
use App\Services\QuotationDocumentRenderer;
use App\Services\SubscriptionLimitService;
use App\Support\DocumentRules;
use App\Support\DocumentTotals;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class QuotationController extends Controller
{
    public function __construct(
        private QuotationDocumentRenderer $documents,
        private CreateQuotation $creator,
        private CreateInvoiceFromQuotation $invoicer,
        private SendInvoiceToClient $sender,
        private SendQuotationToClient $quotationSender,
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
        $prerequisites = DocumentPrerequisites::forQuotations($companyId);

        if ($prerequisites->canCreate()) {
            return;
        }

        throw ValidationException::withMessages([
            'prerequisites' => $prerequisites->blockers()[0]['description'],
        ]);
    }

    /**
     * Scopes the template to the active company and the quotation type so a
     * template id belonging to another company (or to invoices) cannot be submitted.
     */
    private function templateRule(int $companyId): Exists
    {
        return Rule::exists('invoice_templates', 'id')
            ->where('company_id', $companyId)
            ->where('type', 'quotation');
    }

    private function clientRule(int $companyId): Exists
    {
        return Rule::exists('clients', 'id')->where('company_id', $companyId);
    }

    /**
     * Totals for a quotation.
     *
     * Prices are quoted tax-inclusive: what you type on a line is what the
     * client would pay for it. Discounts come off that gross figure, then the
     * tax is carved back out of what remains, so `subtotal` is the net (tax
     * exclusive) amount and `total` is the gross (tax inclusive) one.
     *
     *   items_gross   Σ qty × price           5000.00
     *   − discounts                              0.00
     *   = total       gross, tax inclusive    5000.00
     *     subtotal    total ÷ (1 + rate)      4310.34
     *     tax_total   total − subtotal         689.66
     *
     * Tax is derived by subtraction rather than multiplication so subtotal and
     * tax always add back to exactly the total, with no rounding drift. This
     * mirrors InvoiceController::computeTotals — the two must not drift apart.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array{items: array<int, array<string, mixed>>, items_gross: float, subtotal: float, line_discount_total: float, quotation_discount: float, discount_total: float, tax_percent: float, tax_total: float, total: float}
     */
    private function computeTotals(array $items, float $quotationDiscount, float $taxPercent): array
    {
        $totals = DocumentTotals::compute($items, $quotationDiscount, $taxPercent);

        /** The shared class names the whole-document discount generically. */
        $totals['quotation_discount'] = $totals['document_discount'];
        unset($totals['document_discount']);

        return $totals;
    }

    /**
     * Display a listing of the quotations.
     */
    public function index(Request $request): Response
    {
        $companyId = $this->resolveCompanyId($request);
        $prerequisites = DocumentPrerequisites::forQuotations($companyId);

        if (! $companyId) {
            return Inertia::render('Quotations/Index', [
                'quotations' => [],
                'hasActiveCompany' => false,
                'prerequisites' => $prerequisites->toArray(),
            ]);
        }

        $quotations = collect();

        try {
            $quotations = Quotation::query()
                ->where('company_id', $companyId)
                ->with(['client:id,name'])
                ->orderByDesc('created_at')
                ->get([
                    'id',
                    'number',
                    'client_id',
                    'issue_date',
                    'valid_until',
                    'currency_code',
                    'total',
                    'status',
                    'created_at',
                ])
                ->map(fn (Quotation $quotation) => [
                    'id' => $quotation->id,
                    'number' => $quotation->number,
                    'client_id' => $quotation->client_id,
                    'client_name' => $quotation->client?->name,
                    'issue_date' => $quotation->issue_date,
                    'valid_until' => $quotation->valid_until,
                    'currency_code' => $quotation->currency_code,
                    'total' => (float) $quotation->total,
                    'status' => $quotation->status,
                    'created_at' => $quotation->created_at,
                ]);
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() !== '42P01') {
                throw $exception;
            }
        }

        return Inertia::render('Quotations/Index', [
            'quotations' => $quotations,
            'hasActiveCompany' => true,
            'prerequisites' => $prerequisites->toArray(),
        ]);
    }

    /**
     * Show the form for creating a new quotation.
     */
    public function create(Request $request): Response
    {
        $companyId = $this->resolveCompanyId($request);
        $prerequisites = DocumentPrerequisites::forQuotations($companyId);

        if (! $prerequisites->canCreate()) {
            return Inertia::render('Quotations/Create', [
                'clients' => [],
                'templates' => [],
                'defaultCurrencyCode' => Company::defaultCurrencyCodeFor($companyId, $request->user()?->current_currency_code),
                'hasActiveCompany' => $prerequisites->hasActiveCompany(),
                'prerequisites' => $prerequisites->toArray(),
                'limitNotice' => $request->session()->get('limit_notice'),
            ]);
        }

        $clients = Client::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'contact_person']);

        $templates = InvoiceTemplate::query()
            ->where('company_id', $companyId)
            ->where('type', 'quotation')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'name', 'is_default']);

        return Inertia::render('Quotations/Create', [
            'clients' => $clients,
            'templates' => $templates,
            'defaultCurrencyCode' => Company::defaultCurrencyCodeFor($companyId, $request->user()?->current_currency_code),
            'hasActiveCompany' => true,
            'prerequisites' => $prerequisites->toArray(),
            'limitNotice' => $request->session()->get('limit_notice'),
        ]);
    }

    /**
     * Renders the document for a quotation that has not been saved yet, so the
     * wizard can show exactly what the PDF will contain before committing.
     */
    public function previewNew(Request $request)
    {
        $companyId = $this->companyId($request);
        $this->guardPrerequisites($companyId);
        $user = $request->user();

        /**
         * The create rules plus `embed`, which only the preview understands —
         * it picks the render mode below, and an unvalidated key would be
         * dropped from `$data` and silently fall back to the framed preview.
         */
        $data = $request->validate([
            ...DocumentRules::quotation($companyId),
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

        $template = $this->documents->resolveTemplate(
            $companyId,
            (int) ($data['quotation_template_id'] ?? 0)
        );

        $currency = Currency::query()
            ->where('code', strtoupper((string) $data['currency_code']))
            ->first();

        $totals = $this->computeTotals(
            $data['items'],
            (float) ($data['quotation_discount'] ?? 0),
            (float) ($data['tax_percent'] ?? 0),
        );

        $company = $user->companies()
            ->where('companies.id', $companyId)
            ->first();

        $settings = $this->documents->normalizedSettings($template);

        if (! $template) {
            $template = new InvoiceTemplate(['settings' => $settings]);
        }

        $html = View::make('invoices.templates.default', [
            'company' => $company,
            'client' => $client,
            'template' => $template,
            'currency' => $currency,
            'invoice' => [
                'number' => 'PREVIEW',
                'title' => $data['title'] ?? null,
                'reference' => $data['reference'] ?? null,
                'issue_date' => $data['issue_date'],
                'valid_until' => $data['valid_until'] ?? null,
                'status' => $data['status'],
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
                'quotation_discount' => $totals['quotation_discount'],
                'subtotal' => $totals['subtotal'],
                'discount_total' => $totals['discount_total'],
                'tax_percent' => $totals['tax_percent'],
                'tax_total' => $totals['tax_total'],
                'total' => $totals['total'],
            ],
            'items' => $totals['items'],
            'settings' => $settings,
            'documentType' => 'quotation',
            'mode' => filter_var($data['embed'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 'embed' : 'preview',
        ])->render();

        return response($html);
    }

    public function store(Request $request): RedirectResponse
    {
        $companyId = $this->companyId($request);
        $this->guardPrerequisites($companyId);
        $user = $request->user();
        $limiter = new SubscriptionLimitService($user);

        if (! $limiter->canCreateQuotation($companyId)) {
            return back()->with('limit_notice', $limiter->limitNotice('quotations'));
        }

        $data = $request->validate([
            'client_id' => ['required', 'integer', $this->clientRule($companyId)],
            'quotation_template_id' => ['required', 'integer', $this->templateRule($companyId)],
            'title' => ['nullable', 'string', 'max:190'],
            'reference' => ['nullable', 'string', 'max:190'],
            'issue_date' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'currency_code' => ['required', 'string', 'size:3', Rule::exists('currencies', 'code')],
            'status' => ['required', Rule::in(['draft', 'sent', 'accepted', 'expired'])],
            'notes' => ['nullable', 'string'],
            'terms' => ['nullable', 'string'],
            'quotation_discount' => ['nullable', 'numeric', 'min:0'],
            'tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'send_to_client' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $clientOk = Client::query()
            ->where('id', (int) $data['client_id'])
            ->where('company_id', $companyId)
            ->exists();

        if (! $clientOk) {
            throw ValidationException::withMessages([
                'client_id' => 'That client is not in the active company.',
            ]);
        }

        try {
            $quotation = $this->creator->handle($companyId, $user, $data);
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '42P01') {
                return back()
                    ->withErrors([
                        'quotation' => 'Quotation storage is not set up yet. Run the quotations migrations first.',
                    ])
                    ->withInput();
            }

            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Quotation creation failed', [
                'company_id' => $companyId,
                'user_id' => $user?->id,
                'error' => $exception->getMessage(),
            ]);

            return back()
                ->withErrors([
                    'quotation' => 'Failed to create quotation. Please try again.',
                ])
                ->withInput();
        }

        $delivery = $this->quotationSender->afterCreate($quotation, (bool) ($data['send_to_client'] ?? false));

        return redirect()
            ->route('quotations.show', $quotation)
            ->with($delivery['level'], $delivery['message'])
            ->with('quotation_created', true);
    }

    public function show(Request $request, Quotation $quotation): Response
    {
        $companyId = $this->companyId($request);

        if ((int) $quotation->company_id !== (int) $companyId) {
            throw ValidationException::withMessages([
                'quotation' => 'Quotation not found in the active company.',
            ]);
        }

        $quotation->load([
            'client:id,company_id,name,email,contact_person',
            'items',
            'invoice:id,quotation_id,number',
        ]);

        return Inertia::render('Quotations/show', [
            /** Only true on the redirect straight after creating it. */
            'justCreated' => (bool) $request->session()->get('quotation_created', false),

            /** Set when a plan limit refused the invoice this page tried to raise. */
            'limitNotice' => $request->session()->get('limit_notice'),

            /** Present once this quotation has been billed, so the page links to it. */
            'invoice' => $quotation->invoice
                ? ['id' => $quotation->invoice->id, 'number' => $quotation->invoice->number]
                : null,

            'quotation' => [
                'id' => $quotation->id,
                'number' => $quotation->number,
                'title' => $quotation->title,
                'reference' => $quotation->reference,
                'status' => $quotation->status,
                'issue_date' => $quotation->issue_date,
                'valid_until' => $quotation->valid_until,
                'currency_code' => $quotation->currency_code,

                'subtotal' => (float) $quotation->subtotal,
                'discount_total' => (float) $quotation->discount_total,
                'quotation_discount' => (float) ($quotation->quotation_discount ?? 0),

                /** `discount_total` carries both; split it so neither is shown twice. */
                'line_discount' => (float) $quotation->discount_total
                    - (float) ($quotation->quotation_discount ?? 0),

                'tax_percent' => (float) ($quotation->tax_percent ?? 0),
                'tax_total' => (float) $quotation->tax_total,
                'total' => (float) $quotation->total,

                'notes' => $quotation->notes,
                'terms' => $quotation->terms,

                'client' => $quotation->client,
                'items' => $quotation->items,
            ],
        ]);
    }

    /**
     * Raises the invoice that bills this quotation, and lands on it.
     *
     * A quotation that has already been invoiced is not an error worth
     * shouting about — the second click is almost always an impatient one, so
     * it redirects to the invoice that already exists.
     */
    public function storeInvoice(StoreQuotationInvoiceRequest $request, Quotation $quotation): RedirectResponse
    {
        $companyId = $this->companyId($request);

        abort_unless((int) $quotation->company_id === $companyId, 403);

        $existing = $quotation->invoice()->first();

        if ($existing) {
            return redirect()
                ->route('invoices.show', $existing)
                ->with('info', 'This quotation was already invoiced as '.$existing->number.'.');
        }

        $user = $request->user();
        $limiter = new SubscriptionLimitService($user);

        if (! $limiter->canCreateInvoice($companyId)) {
            return back()->with('limit_notice', $limiter->limitNotice('invoices'));
        }

        $invoice = $this->invoicer->handle($companyId, $user, $quotation);

        $delivery = $request->boolean('send_to_client')
            ? $this->sender->afterCreate($invoice, true)
            : ['level' => 'success', 'message' => 'Invoice '.$invoice->number.' created from '.$quotation->number.'.'];

        return redirect()
            ->route('invoices.show', $invoice)
            ->with($delivery['level'], $delivery['message']);
    }

    public function updateStatus(Request $request, Quotation $quotation): RedirectResponse
    {
        $companyId = $this->companyId($request);

        if ((int) $quotation->company_id !== (int) $companyId) {
            throw ValidationException::withMessages([
                'quotation' => 'Quotation not found in the active company.',
            ]);
        }

        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(['draft', 'sent', 'accepted', 'expired'])],
        ]);

        if ($quotation->status === $data['status']) {
            return back()->with('info', 'Quotation status is already set to '.$data['status'].'.');
        }

        $quotation->update(['status' => $data['status']]);

        return back()->with('success', 'Quotation status updated to '.$data['status'].'.');
    }

    /**
     * Emails a saved quotation to its client, with the PDF attached.
     */
    public function send(Request $request, Quotation $quotation): RedirectResponse
    {
        abort_unless((int) $quotation->company_id === $this->companyId($request), 403);

        $result = $this->quotationSender->handle($quotation);

        return back()->with($result['sent'] ? 'success' : 'error', $result['message']);
    }

    public function preview(Request $request, Quotation $quotation)
    {
        $companyId = $this->companyId($request);

        abort_unless((int) $quotation->company_id === (int) $companyId, 403);

        return response($this->documents->html($quotation, 'preview'));
    }

    public function print(Request $request, Quotation $quotation)
    {
        $companyId = $this->companyId($request);

        abort_unless((int) $quotation->company_id === (int) $companyId, 403);

        return response()->view(
            'invoices.templates.default',
            $this->documents->viewData($quotation, 'print') + [
                'autoPrint' => $request->boolean('download'),
            ]
        );
    }
}
