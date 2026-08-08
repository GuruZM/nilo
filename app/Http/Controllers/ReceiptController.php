<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Company;
use App\Models\InvoicePayment;
use App\Services\DocumentRenderer;
use App\Services\Documents\IssueReceipt;
use App\Services\Documents\RecordInvoicePayment;
use App\Support\DocumentRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The receipt register: every receipt the company has issued, of either kind.
 *
 * Receipts against an invoice are still recorded and removed through
 * {@see InvoicePaymentController}, which owns the settlement side. This
 * controller is the front door — listing, viewing and printing both kinds, and
 * issuing the standalone ones that have no invoice to be recorded against.
 */
class ReceiptController extends Controller
{
    public function __construct(
        private DocumentRenderer $documents,
        private IssueReceipt $issuer,
        private RecordInvoicePayment $recorder,
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
     * Route model binding resolves a receipt by id alone, so a row from another
     * company binds perfectly well and has to be turned away here.
     */
    private function guard(Request $request, InvoicePayment $receipt): void
    {
        abort_unless((int) $receipt->company_id === $this->companyId($request), 403);
    }

    public function index(Request $request): Response
    {
        $companyId = $this->resolveCompanyId($request);

        if (! $companyId) {
            return Inertia::render('Receipts/Index', [
                'receipts' => [],
                'hasActiveCompany' => false,
            ]);
        }

        $receipts = InvoicePayment::query()
            ->where('company_id', $companyId)
            ->with(['client:id,name', 'invoice:id,number,client_id', 'invoice.client:id,name'])
            ->orderByDesc('paid_on')
            ->orderByDesc('id')
            ->get()
            ->map(fn (InvoicePayment $receipt) => [
                'id' => $receipt->id,
                'number' => $receipt->receipt_number,
                'client_name' => $receipt->counterparty()?->name,
                'invoice_id' => $receipt->invoice?->id,
                'invoice_number' => $receipt->invoice?->number,
                'paid_on' => $receipt->paid_on?->toDateString(),
                'amount' => (float) $receipt->amount,
                'currency_code' => $receipt->currency_code,
                'method' => $receipt->method,
                'method_label' => $receipt->methodLabel(),
                'reference' => $receipt->reference,
                'description' => $receipt->description,
            ]);

        return Inertia::render('Receipts/Index', [
            'receipts' => $receipts,
            'hasActiveCompany' => true,
        ]);
    }

    /**
     * No template prerequisite, unlike the invoice and quotation forms:
     * {@see \App\Services\TemplateProvisioner} creates a receipt template on
     * first print, and making somebody design one before they can acknowledge
     * cash in hand would be a wall rather than a feature.
     */
    public function create(Request $request): Response
    {
        $companyId = $this->companyId($request);

        return Inertia::render('Receipts/Create', [
            'clients' => Client::query()
                ->where('company_id', $companyId)
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'contact_person', 'address']),
            'defaultCurrencyCode' => Company::defaultCurrencyCodeFor(
                $companyId,
                $request->user()?->current_currency_code,
            ),
            'paymentMethods' => $this->paymentMethods(),
            'hasActiveCompany' => true,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $companyId = $this->companyId($request);

        $data = $request->validate(DocumentRules::standaloneReceipt($companyId));

        $receipt = $this->issuer->handle($companyId, $request->user(), $data);

        return redirect()
            ->route('receipts.show', $receipt)
            ->with('success', 'Receipt '.$receipt->receipt_number.' issued.');
    }

    public function show(Request $request, InvoicePayment $receipt): Response
    {
        $this->guard($request, $receipt);

        $receipt->load([
            'client:id,company_id,name,email,address,contact_person',
            'invoice:id,number,client_id,currency_code',
            'invoice.client:id,name',
            'recorder:id,name',
        ]);

        return Inertia::render('Receipts/show', [
            'receipt' => [
                'id' => $receipt->id,
                'number' => $receipt->receipt_number,
                'amount' => (float) $receipt->amount,

                /**
                 * The balance the invoice was left with, frozen when the payment
                 * was recorded. Null on a standalone receipt, which settles no
                 * invoice and so leaves no balance behind.
                 */
                'balance_after' => $receipt->balance_after !== null
                    ? (float) $receipt->balance_after
                    : null,

                'currency_code' => $receipt->currency_code,
                'paid_on' => $receipt->paid_on?->toDateString(),
                'method' => $receipt->method,
                'method_label' => $receipt->methodLabel(),
                'reference' => $receipt->reference,
                'description' => $receipt->description,
                'recorded_by' => $receipt->recorder?->name,
                'client' => $receipt->counterparty(),
                'invoice' => $receipt->invoice
                    ? [
                        'id' => $receipt->invoice->id,
                        'number' => $receipt->invoice->number,
                    ]
                    : null,
            ],
        ]);
    }

    /**
     * Removing an invoice-backed receipt has to go back through the recorder so
     * the invoice status re-derives from what is left in the ledger. A
     * standalone one settles nothing, so there is nothing to re-derive.
     */
    public function destroy(Request $request, InvoicePayment $receipt): RedirectResponse
    {
        $this->guard($request, $receipt);

        if ($receipt->invoice) {
            $this->recorder->remove($receipt->invoice, $receipt);
        } else {
            $receipt->delete();
        }

        return redirect()
            ->route('receipts.index')
            ->with('success', 'Receipt removed.');
    }

    public function preview(Request $request, InvoicePayment $receipt)
    {
        $this->guard($request, $receipt);

        return response($this->documents->html($receipt, 'preview'));
    }

    public function print(Request $request, InvoicePayment $receipt)
    {
        $this->guard($request, $receipt);

        return response()->view(
            'invoices.templates.default',
            $this->documents->viewData($receipt, 'print') + [
                'autoPrint' => $request->boolean('download'),
            ]
        );
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function paymentMethods(): array
    {
        return collect(InvoicePayment::METHODS)
            ->map(fn (string $method) => [
                'value' => $method,
                'label' => (new InvoicePayment(['method' => $method]))->methodLabel(),
            ])
            ->all();
    }
}
