<?php

namespace App\Http\Controllers;

use App\Enums\DocumentType;
use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\DocumentRenderer;
use App\Services\SubscriptionLimitService;
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

    /**
     * Scopes the supplier to the active company, so a vendor belonging to
     * somebody else cannot be ordered from by submitting their id.
     */
    private function supplierRule(int $companyId): Exists
    {
        return Rule::exists('suppliers', 'id')->where('company_id', $companyId);
    }

    /**
     * Totals for a purchase order.
     *
     * Identical arithmetic to {@see QuotationController::computeTotals()} and
     * {@see CreditNoteController::computeTotals()} — prices are tax-inclusive,
     * discounts come off the gross, tax is carved back out by subtraction. All
     * three must not drift apart.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array{items: array<int, array<string, mixed>>, subtotal: float, purchase_order_discount: float, discount_total: float, tax_percent: float, tax_total: float, total: float}
     */
    private function computeTotals(array $items, float $orderDiscount, float $taxPercent): array
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

        $discountTotal = $lineDiscountTotal + $orderDiscount;

        $total = round(max(0, $itemsGross - $discountTotal), 2);
        $subtotal = round($total / (1 + ($taxPercent / 100)), 2);
        $taxTotal = round($total - $subtotal, 2);

        return [
            'items' => $items,
            'subtotal' => $subtotal,
            'purchase_order_discount' => $orderDiscount,
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

        $data = $request->validate([
            'supplier_id' => ['required', 'integer', $this->supplierRule($companyId)],
            'title' => ['nullable', 'string', 'max:190'],
            'reference' => ['nullable', 'string', 'max:190'],
            'issue_date' => ['required', 'date'],

            /** Same-day delivery is ordinary, so `after_or_equal` rather than `after`. */
            'expected_date' => ['nullable', 'date', 'after_or_equal:issue_date'],

            /**
             * A purchase order is denominated in whatever the supplier invoices
             * in, so the code comes from the form — but only one the currencies
             * table knows, normalised to upper case before it is stored.
             */
            'currency_code' => ['required', 'string', 'size:3', Rule::exists('currencies', 'code')],

            'status' => ['required', Rule::in(PurchaseOrder::STATUSES)],
            'delivery_address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'terms' => ['nullable', 'string'],
            'purchase_order_discount' => ['nullable', 'numeric', 'min:0'],
            'tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $totals = $this->computeTotals(
            $data['items'],
            (float) ($data['purchase_order_discount'] ?? 0),
            (float) ($data['tax_percent'] ?? 0),
        );

        $order = DB::transaction(function () use ($companyId, $user, $data, $totals): PurchaseOrder {
            $order = PurchaseOrder::query()->create([
                'company_id' => $companyId,
                'supplier_id' => (int) $data['supplier_id'],

                /**
                 * Provisioning runs inside the transaction on purpose: it may
                 * create the company's first purchase order template, and a
                 * rolled back order must not leave that row behind.
                 */
                'purchase_order_template_id' => $this->templates
                    ->forCompany($companyId, DocumentType::PurchaseOrder)->id,

                'created_by' => $user?->id,
                'number' => DocumentNumber::nextFor(PurchaseOrder::class, $companyId, DocumentType::PurchaseOrder),
                'reference' => $data['reference'] ?? null,
                'title' => $data['title'] ?? null,
                'issue_date' => $data['issue_date'],
                'expected_date' => $data['expected_date'] ?? null,
                'currency_code' => strtoupper((string) $data['currency_code']),
                'delivery_address' => $data['delivery_address'] ?? null,

                'subtotal' => $totals['subtotal'],
                'purchase_order_discount' => $totals['purchase_order_discount'],
                'discount_total' => $totals['discount_total'],
                'tax_percent' => $totals['tax_percent'],
                'tax_total' => $totals['tax_total'],
                'total' => $totals['total'],

                'status' => $data['status'],
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
            ]);

            $order->items()->createMany($totals['items']);

            return $order;
        });

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
