<?php

use App\Models\Client;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\Invoice;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The dashboard's outstanding roll-up is what a company is still owed, not what
 * it once billed. Part payments make those two figures diverge.
 */
beforeEach(function () {
    foreach (['ZMW' => 'Zambian Kwacha', 'USD' => 'US Dollar'] as $code => $name) {
        Currency::firstOrCreate(['code' => $code], [
            'name' => $name,
            'symbol' => null,
            'precision' => 2,
            'is_active' => true,
        ]);
    }
});

it('counts only the unpaid remainder of a partly settled invoice', function () {
    [$user, $invoice] = payableInvoiceContext(5000);

    $this->actingAs($user)->post("/invoices/{$invoice->id}/payments", [
        'amount' => 4000, 'paid_on' => '2026-07-15', 'method' => 'cash',
    ])->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.pending_revenue', 1000)
        );
});

it('counts nothing for an invoice that has been settled in full', function () {
    [$user, $invoice] = payableInvoiceContext(5000);

    $this->actingAs($user)->post("/invoices/{$invoice->id}/payments", [
        'amount' => 5000, 'paid_on' => '2026-07-15', 'method' => 'cash',
    ])->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.pending_revenue', 0)
            ->where('stats.paid_revenue', 5000)
        );
});

it('counts the whole total of an invoice nothing has been paid against', function () {
    [$user] = payableInvoiceContext(5000);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.pending_revenue', 5000)
        );
});

/**
 * The overdue and due-soon tiles read the same ledger, so a part payment has to
 * come off them too — otherwise the headline figure and the tiles disagree.
 */
it('takes settled money off the overdue and due-soon figures', function () {
    [$user, $invoice] = payableInvoiceContext(5000);

    $invoice->update(['due_date' => now()->subDay()->toDateString()]);

    $this->actingAs($user)->post("/invoices/{$invoice->id}/payments", [
        'amount' => 4000, 'paid_on' => '2026-07-15', 'method' => 'cash',
    ])->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.overdue_revenue', 1000)
            ->where('stats.due_in_7_revenue', 0)
        );
});

it('ranks top clients on what they still owe rather than what they were billed', function () {
    [$user, $invoice] = payableInvoiceContext(5000);

    $this->actingAs($user)->post("/invoices/{$invoice->id}/payments", [
        'amount' => 4000, 'paid_on' => '2026-07-15', 'method' => 'cash',
    ])->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('top_clients.0.pending_total', 1000)
        );
});

/**
 * Payments are recorded in the invoice's currency, so the settled money must
 * come off *before* the rate is applied. Converting the total and then
 * subtracting a raw foreign amount would be arithmetic across two currencies.
 */
it('subtracts the settled money before converting it', function () {
    [$user, $invoice] = payableInvoiceContext(1870);

    foreach (['USD' => 1.0, 'ZMW' => 18.7] as $code => $rate) {
        ExchangeRate::create([
            'base_code' => 'USD',
            'quote_code' => $code,
            'rate' => $rate,
            'fetched_at' => now(),
        ]);
    }

    $this->actingAs($user)->post("/invoices/{$invoice->id}/payments", [
        'amount' => 935, 'paid_on' => '2026-07-15', 'method' => 'cash',
    ])->assertSessionHasNoErrors();

    $user->forceFill(['current_currency_code' => 'USD'])->save();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.currency_code', 'USD')
            ->where('stats.pending_revenue', 50)
        );
});

/**
 * A second, untouched invoice must still contribute in full — the subtraction
 * is per invoice, not spread across the company.
 */
it('subtracts each invoice its own payments', function () {
    [$user, $invoice] = payableInvoiceContext(5000);

    $second = Invoice::query()->create([
        'company_id' => $user->current_company_id,
        'client_id' => Client::query()->where('company_id', $user->current_company_id)->value('id'),
        'number' => 'INV-000002',
        'issue_date' => '2026-07-01',
        'currency_code' => 'ZMW',
        'subtotal' => 2000,
        'total' => 2000,
        'status' => 'sent',
    ]);

    $this->actingAs($user)->post("/invoices/{$invoice->id}/payments", [
        'amount' => 4000, 'paid_on' => '2026-07-15', 'method' => 'cash',
    ])->assertSessionHasNoErrors();

    expect($second->fresh()->status)->toBe('sent');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.pending_revenue', 3000)
        );
});

/**
 * The companies list and the dashboard read the same money from two different
 * queries. If they disagree, one of them is lying to the user about what they
 * are owed — so this pins them together.
 */
it('nets payments off the companies list the same way the dashboard does', function () {
    [$user, $invoice] = payableInvoiceContext();

    $this->actingAs($user)->post("/invoices/{$invoice->id}/payments", [
        'amount' => 4000, 'paid_on' => '2026-07-15', 'method' => 'cash',
    ])->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->get(route('companies.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Companies/Index')
            ->where('companies.0.pending_revenue', 1000)
        );

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('stats.pending_revenue', 1000));
});
