<?php

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\SubscriptionLimitService;

it('allows creation while under the plan limit', function () {
    [$user] = invoiceCreationContext('client@example.com');
    $user->activePlan()->update(['max_purchase_orders' => 2]);

    expect((new SubscriptionLimitService($user->fresh()))
        ->canCreatePurchaseOrder($user->current_company_id))->toBeTrue();
});

it('refuses creation once the limit is reached', function () {
    [$user] = invoiceCreationContext('client@example.com');
    $user->activePlan()->update(['max_purchase_orders' => 1]);

    PurchaseOrder::factory()->create([
        'company_id' => $user->current_company_id,
        'supplier_id' => Supplier::factory()->create([
            'company_id' => $user->current_company_id,
        ])->id,
    ]);

    expect((new SubscriptionLimitService($user->fresh()))
        ->canCreatePurchaseOrder($user->current_company_id))->toBeFalse();
});

it('treats -1 as unlimited', function () {
    [$user] = invoiceCreationContext('client@example.com');
    $user->activePlan()->update(['max_purchase_orders' => -1]);

    PurchaseOrder::factory()->count(3)->create([
        'company_id' => $user->current_company_id,
        'supplier_id' => Supplier::factory()->create([
            'company_id' => $user->current_company_id,
        ])->id,
    ]);

    expect((new SubscriptionLimitService($user->fresh()))
        ->canCreatePurchaseOrder($user->current_company_id))->toBeTrue();
});

it('reports purchase order usage alongside the other allowances', function () {
    [$user] = invoiceCreationContext('client@example.com');

    expect((new SubscriptionLimitService($user))->usage())
        ->toHaveKey('purchase_orders');
});

/**
 * UserFactory::withSubscription() calls Plan::firstOrCreate(['slug' => 'free']),
 * so both users below share ONE plan row. That is the point: the limit is 1 for
 * both, and this still passes only because the count is scoped per company.
 */
it('counts per company, not per account', function () {
    [$user] = invoiceCreationContext('client@example.com');
    [$otherUser] = invoiceCreationContext('client@example.com');

    $user->activePlan()->update(['max_purchase_orders' => 1]);

    expect($user->activePlan()->id)->toBe($otherUser->activePlan()->id);

    PurchaseOrder::factory()->create([
        'company_id' => $otherUser->current_company_id,
        'supplier_id' => Supplier::factory()->create([
            'company_id' => $otherUser->current_company_id,
        ])->id,
    ]);

    expect((new SubscriptionLimitService($user->fresh()))
        ->canCreatePurchaseOrder($user->current_company_id))->toBeTrue();
});

/**
 * withSubscription() never sets max_purchase_orders, so the migration default
 * has to carry it. If the default were anything but -1, every existing plan
 * would be capped the moment this shipped.
 */
it('leaves plans that never set the column unlimited', function () {
    [$user] = invoiceCreationContext('client@example.com');

    expect($user->activePlan()->max_purchase_orders)->toBe(-1);
});
