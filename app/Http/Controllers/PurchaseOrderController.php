<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\DocumentRenderer;
use App\Services\Documents\CreatePurchaseOrder;
use App\Services\Documents\SendDocumentToCounterparty;
use App\Services\SubscriptionLimitService;
use App\Support\DocumentRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Orders placed with a supplier: written, moved through the order workflow and
 * printed.
 *
 * The only document Nilo issues that points away from the customer, and the
 * only one whose currency is genuinely its own — you may order from an overseas
 * supplier in their money while billing your clients in yours. That is why
 * `currency_code` is taken from the request here and deliberately is not on a
 * credit note, which must match what it credits.
 */
class PurchaseOrderController extends Controller
{
    public function __construct(
        private DocumentRenderer $documents,
        private CreatePurchaseOrder $creator,
        private SendDocumentToCounterparty $sender,
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
     * Scopes the supplier to the active company, so a vendor belonging to
     * somebody else cannot be ordered from by submitting their id.
     */
    private function supplierRule(int $companyId): Exists
    {
        return Rule::exists('suppliers', 'id')->where('company_id', $companyId);
    }

    public function index(Request $request): Response
    {
        $companyId = $this->resolveCompanyId($request);

        if (! $companyId) {
            return Inertia::render('PurchaseOrders/Index', [
                'purchaseOrders' => [],
                'hasActiveCompany' => false,
                'hasSuppliers' => false,
            ]);
        }

        $purchaseOrders = PurchaseOrder::query()
            ->where('company_id', $companyId)
            ->with(['supplier:id,name'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (PurchaseOrder $order) => [
                'id' => $order->id,
                'number' => $order->number,
                'supplier_name' => $order->supplier?->name,
                'issue_date' => $order->issue_date,
                'expected_date' => $order->expected_date,
                'currency_code' => $order->currency_code,
                'total' => (float) $order->total,
                'status' => $order->status,
            ]);

        return Inertia::render('PurchaseOrders/Index', [
            'purchaseOrders' => $purchaseOrders,
            'hasActiveCompany' => true,
            'hasSuppliers' => Supplier::query()->where('company_id', $companyId)->exists(),
        ]);
    }

    public function create(Request $request): Response
    {
        $companyId = $this->companyId($request);

        return Inertia::render('PurchaseOrders/Create', [
            'suppliers' => Supplier::query()
                ->where('company_id', $companyId)
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'contact_person', 'address']),
            'defaultCurrencyCode' => Company::defaultCurrencyCodeFor(
                $companyId,
                $request->user()?->current_currency_code,
            ),
            'statuses' => PurchaseOrder::STATUSES,
            'hasActiveCompany' => true,
            'limitNotice' => $request->session()->get('limit_notice'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $companyId = $this->companyId($request);
        $user = $request->user();
        $limiter = new SubscriptionLimitService($user);

        if (! $limiter->canCreatePurchaseOrder($companyId)) {
            return back()->with('limit_notice', $limiter->limitNotice('purchase orders'));
        }

        $data = $request->validate(DocumentRules::purchaseOrder($companyId));

        $order = $this->creator->handle($companyId, $user, $data);

        return redirect()
            ->route('purchase-orders.show', $order)
            ->with('success', 'Purchase order '.$order->number.' created.');
    }

    public function show(Request $request, PurchaseOrder $purchaseOrder): Response
    {
        abort_unless((int) $purchaseOrder->company_id === $this->companyId($request), 403);

        $purchaseOrder->load([
            'supplier:id,company_id,name,email,contact_person,address',
            'items',
        ]);

        return Inertia::render('PurchaseOrders/show', [
            'purchaseOrder' => [
                'id' => $purchaseOrder->id,
                'number' => $purchaseOrder->number,
                'title' => $purchaseOrder->title,
                'reference' => $purchaseOrder->reference,
                'status' => $purchaseOrder->status,
                'issue_date' => $purchaseOrder->issue_date,
                'expected_date' => $purchaseOrder->expected_date,
                'currency_code' => $purchaseOrder->currency_code,
                'delivery_address' => $purchaseOrder->delivery_address,

                'subtotal' => (float) $purchaseOrder->subtotal,
                'discount_total' => (float) $purchaseOrder->discount_total,
                'purchase_order_discount' => (float) ($purchaseOrder->purchase_order_discount ?? 0),

                /**
                 * `discount_total` carries both; split it here rather than in the
                 * page, so the two never disagree about which half is which.
                 * Matches what QuotationController::show() sends.
                 */
                'line_discount' => max(0, (float) $purchaseOrder->discount_total
                    - (float) ($purchaseOrder->purchase_order_discount ?? 0)),
                'tax_percent' => (float) ($purchaseOrder->tax_percent ?? 0),
                'tax_total' => (float) $purchaseOrder->tax_total,
                'total' => (float) $purchaseOrder->total,

                'notes' => $purchaseOrder->notes,
                'terms' => $purchaseOrder->terms,

                'supplier' => $purchaseOrder->supplier,
                'items' => $purchaseOrder->items,
            ],
            'statuses' => PurchaseOrder::STATUSES,
        ]);
    }

    public function updateStatus(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_unless((int) $purchaseOrder->company_id === $this->companyId($request), 403);

        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(PurchaseOrder::STATUSES)],
        ]);

        if ($purchaseOrder->status === $data['status']) {
            return back()->with('info', 'Purchase order is already '.$data['status'].'.');
        }

        $purchaseOrder->update(['status' => $data['status']]);

        return back()->with('success', 'Purchase order is now '.$data['status'].'.');
    }

    /**
     * Emails the purchase order to its supplier, with the PDF attached.
     */
    public function send(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_unless((int) $purchaseOrder->company_id === $this->companyId($request), 403);

        $result = $this->sender->handle($purchaseOrder);

        return back()->with($result['sent'] ? 'success' : 'error', $result['message']);
    }

    public function preview(Request $request, PurchaseOrder $purchaseOrder)
    {
        abort_unless((int) $purchaseOrder->company_id === $this->companyId($request), 403);

        return response($this->documents->html($purchaseOrder, 'preview'));
    }

    public function print(Request $request, PurchaseOrder $purchaseOrder)
    {
        abort_unless((int) $purchaseOrder->company_id === $this->companyId($request), 403);

        return response()->view(
            'invoices.templates.default',
            $this->documents->viewData($purchaseOrder, 'print') + [
                'autoPrint' => $request->boolean('download'),
            ]
        );
    }
}
