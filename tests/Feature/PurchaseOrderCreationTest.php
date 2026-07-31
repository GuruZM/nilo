<?php

use App\Models\Currency;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;

beforeEach(function () {
    Currency::firstOrCreate(['code' => 'ZMW'], [
        'name' => 'Zambian Kwacha',
        'symbol' => 'K',
        'precision' => 2,
        'is_active' => true,
    ]);
});

/* ---------------------------- Creation ---------------------------- */

it('creates a purchase order for a supplier', function () {
    [$user, $supplier] = purchaseOrderContext();

    $this->actingAs($user)
        ->post('/purchase-orders', purchaseOrderPayload($supplier))
        ->assertSessionHasNoErrors();

    $order = PurchaseOrder::query()->latest('id')->first();

    expect($order->supplier_id)->toBe($supplier->id)
        ->and($order->company_id)->toBe($user->current_company_id)
        ->and($order->number)->toBe('PO-000001')
        ->and((float) $order->total)->toBe(5000.0);
});

it('numbers purchase orders sequentially per company', function () {
    [$user, $supplier] = purchaseOrderContext();

    $this->actingAs($user)->post('/purchase-orders', purchaseOrderPayload($supplier));
    $this->actingAs($user)->post('/purchase-orders', purchaseOrderPayload($supplier));

    expect(PurchaseOrder::query()->orderBy('id')->pluck('number')->all())
        ->toBe(['PO-000001', 'PO-000002']);
});

it('stores the ordered lines', function () {
    [$user, $supplier] = purchaseOrderContext();

    $this->actingAs($user)->post('/purchase-orders', purchaseOrderPayload($supplier));
    $order = PurchaseOrder::query()->latest('id')->first();

    $item = PurchaseOrderItem::query()->where('purchase_order_id', $order->id)->first();

    expect($item->description)->toBe('Steel bolts')
        ->and($item->unit)->toBe('box')
        ->and((float) $item->line_total)->toBe(5000.0);
});

/* ---------------------------- Money ---------------------------- */

it('carves tax out of the ordered price the same way an invoice does', function () {
    [$user, $supplier] = purchaseOrderContext();

    $payload = purchaseOrderPayload($supplier);
    $payload['tax_percent'] = 16;

    $this->actingAs($user)->post('/purchase-orders', $payload);
    $order = PurchaseOrder::query()->latest('id')->first();

    expect((float) $order->total)->toBe(5000.0)
        ->and((float) $order->subtotal)->toBe(4310.34)
        ->and((float) $order->tax_total)->toBe(689.66);
});

it('rejects a tax rate outside 0 to 100', function () {
    [$user, $supplier] = purchaseOrderContext();

    $payload = purchaseOrderPayload($supplier);
    $payload['tax_percent'] = 140;

    $this->actingAs($user)
        ->post('/purchase-orders', $payload)
        ->assertSessionHasErrors('tax_percent');
});

/* ---------------------------- Currency ---------------------------- */

/**
 * A purchase order carries its own currency on purpose — you may order from an
 * overseas supplier in their money while billing your own clients in yours. It
 * is therefore accepted from the request, but only after being checked against
 * the currencies table and normalised, the same way a quotation does it.
 */
it('stores the ordered currency in the canonical upper case', function () {
    [$user, $supplier] = purchaseOrderContext();

    $this->actingAs($user)
        ->post('/purchase-orders', purchaseOrderPayload($supplier))
        ->assertSessionHasNoErrors();

    expect(PurchaseOrder::query()->latest('id')->first()->currency_code)->toBe('ZMW');
});

it('refuses a currency that is not on the currencies table', function () {
    [$user, $supplier] = purchaseOrderContext();

    $payload = purchaseOrderPayload($supplier);
    $payload['currency_code'] = 'XXZ';

    $this->actingAs($user)
        ->post('/purchase-orders', $payload)
        ->assertSessionHasErrors('currency_code');
});

/**
 * The `exists` check runs against the raw submission, and both sqlite and
 * postgres compare it case sensitively, so a lower case code is refused before
 * the `strtoupper()` in the controller ever sees it. That is exactly what a
 * quotation does with the same rule; the normalisation is the belt to that
 * braces, and only earns its keep on a case-insensitive collation.
 */
it('refuses a lower case currency code, as a quotation does', function () {
    [$user, $supplier] = purchaseOrderContext();

    $payload = purchaseOrderPayload($supplier);
    $payload['currency_code'] = 'zmw';

    $this->actingAs($user)
        ->post('/purchase-orders', $payload)
        ->assertSessionHasErrors('currency_code');
});

/* ---------------------------- Dates ---------------------------- */

it('accepts goods expected the same day they are ordered', function () {
    [$user, $supplier] = purchaseOrderContext();

    $payload = purchaseOrderPayload($supplier);
    $payload['expected_date'] = $payload['issue_date'];

    $this->actingAs($user)
        ->post('/purchase-orders', $payload)
        ->assertSessionHasNoErrors();

    expect(PurchaseOrder::query()->count())->toBe(1);
});

it('refuses goods expected before the order was placed', function () {
    [$user, $supplier] = purchaseOrderContext();

    $payload = purchaseOrderPayload($supplier);
    $payload['expected_date'] = '2026-06-30';

    $this->actingAs($user)
        ->post('/purchase-orders', $payload)
        ->assertSessionHasErrors('expected_date');
});

/* ---------------------------- Guards ---------------------------- */

it('refuses a supplier from another company', function () {
    [$user] = purchaseOrderContext();
    [, $foreignSupplier] = purchaseOrderContext();

    $this->actingAs($user)
        ->post('/purchase-orders', purchaseOrderPayload($foreignSupplier))
        ->assertSessionHasErrors('supplier_id');

    expect(PurchaseOrder::query()->count())->toBe(0);
});

it('refuses to exceed the plan allowance', function () {
    [$user, $supplier] = purchaseOrderContext();
    $user->activePlan()->update(['max_purchase_orders' => 1]);

    $this->actingAs($user)->post('/purchase-orders', purchaseOrderPayload($supplier));

    $this->actingAs($user)
        ->post('/purchase-orders', purchaseOrderPayload($supplier))
        ->assertSessionHas('limit_notice');

    expect(PurchaseOrder::query()->count())->toBe(1);
});

it('rejects a status the order workflow does not use', function () {
    [$user, $supplier] = purchaseOrderContext();

    $payload = purchaseOrderPayload($supplier);
    $payload['status'] = 'paid';

    $this->actingAs($user)
        ->post('/purchase-orders', $payload)
        ->assertSessionHasErrors('status');
});

/* ---------------------------- Rendering ---------------------------- */

it('renders addressed to the supplier', function () {
    [$user, $supplier] = purchaseOrderContext();

    $this->actingAs($user)->post('/purchase-orders', purchaseOrderPayload($supplier));
    $order = PurchaseOrder::query()->latest('id')->first();

    $response = $this->actingAs($user)->get("/purchase-orders/{$order->id}/preview");
    $response->assertSuccessful();

    /**
     * The shared sheet reads `name`, `address`, `email` and `contact_person`
     * off whatever counterparty it is handed, and `Supplier` carries all four
     * under the same names — so a purchase order addresses its vendor through
     * exactly the block an invoice uses for its client.
     */
    expect($response->getContent())
        ->toContain('PURCHASE ORDER')
        ->toContain(e($supplier->name))
        ->toContain(e($supplier->address))
        ->toContain(e($supplier->email))
        ->toContain('Attn: '.e($supplier->contact_person))
        ->toContain('Expected: 2026-08-15')

        /** The date closure must strip the Carbon time off a saved document. */
        ->not->toContain('2026-08-15 00:00:00');
});

it('will not preview an order from another company', function () {
    [$user] = purchaseOrderContext();
    [$otherUser, $foreignSupplier] = purchaseOrderContext();

    $this->actingAs($otherUser)->post('/purchase-orders', purchaseOrderPayload($foreignSupplier));
    $foreignOrder = PurchaseOrder::query()->latest('id')->first();

    $this->actingAs($user)
        ->get("/purchase-orders/{$foreignOrder->id}/preview")
        ->assertForbidden();
});

/* ---------------------------- Status and index ---------------------------- */

it('updates the order status', function () {
    [$user, $supplier] = purchaseOrderContext();

    $this->actingAs($user)->post('/purchase-orders', purchaseOrderPayload($supplier));
    $order = PurchaseOrder::query()->latest('id')->first();

    $this->actingAs($user)
        ->post("/purchase-orders/{$order->id}/status", ['status' => 'received'])
        ->assertSessionHasNoErrors();

    expect($order->fresh()->status)->toBe('received');
});

it('lists purchase orders for the active company', function () {
    [$user, $supplier] = purchaseOrderContext();

    $this->actingAs($user)->post('/purchase-orders', purchaseOrderPayload($supplier));

    $this->actingAs($user)
        ->get('/purchase-orders')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('PurchaseOrders/Index')
            ->has('purchaseOrders', 1)
            ->where('purchaseOrders.0.number', 'PO-000001')
            ->where('purchaseOrders.0.supplier_name', $supplier->name)
        );
});

/**
 * The page `store()` redirects to, rendered for the leanest order the form can
 * produce: a draft with no delivery date agreed. Both are optional, so the show
 * page has to survive them being absent.
 */
it('shows a freshly created draft order with no expected date', function () {
    [$user, $supplier] = purchaseOrderContext();

    $payload = purchaseOrderPayload($supplier);
    unset($payload['expected_date']);

    $this->actingAs($user)
        ->post('/purchase-orders', $payload)
        ->assertRedirect();

    $order = PurchaseOrder::query()->latest('id')->first();

    $this->actingAs($user)
        ->get("/purchase-orders/{$order->id}")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('PurchaseOrders/show')
            ->where('purchaseOrder.status', 'draft')
            ->where('purchaseOrder.expected_date', null)
            ->where('purchaseOrder.supplier.name', $supplier->name)
            ->has('purchaseOrder.items', 1)
            ->has('statuses', 5)
        );
});

it('offers the company suppliers on the create page', function () {
    [$user, $supplier] = purchaseOrderContext();

    $this->actingAs($user)
        ->get('/purchase-orders/create')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('PurchaseOrders/Create')
            ->has('suppliers', 1)
            ->where('suppliers.0.name', $supplier->name)
        );
});
