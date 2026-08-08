<?php

use App\Models\Company;
use App\Models\CompanyExchangeRate;
use App\Models\ExchangeRate;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

function seedZmwRate(float $rate = 18.7): ExchangeRate
{
    return ExchangeRate::create([
        'base_code' => 'USD',
        'quote_code' => 'ZMW',
        'rate' => $rate,
        'fetched_at' => now()->subHours(3),
    ]);
}

beforeEach(function () {
    Sleep::fake();
    config(['services.dpo.enabled' => true, 'services.dpo.company_token' => 'TEST']);
});

it('charges in USD while the ledger stays in the plan currency', function () {
    fakeCreateToken();
    $rate = seedZmwRate();
    $plan = dpoPlan();

    $this->actingAs(dpoBuyer())
        ->post('/subscription/payment/dpo', ['plan_id' => $plan->id, 'currency' => 'USD']);

    $payment = Payment::first();

    // 100000 ZMW / 18.7 ZMW-per-USD
    expect((float) $payment->charged_amount)->toBe(5347.59)
        ->and($payment->charged_currency_code)->toBe('USD')
        ->and($payment->charged_exchange_rate)->toBe(18.7)
        ->and($payment->charged_rate_fetched_at->toIso8601String())->toBe($rate->fetched_at->toIso8601String())
        // The ledger is untouched, so every admin total still sums one currency.
        ->and((float) $payment->amount)->toBe(100000.0)
        ->and($payment->currency_code)->toBe('ZMW');

    Http::assertSent(fn ($request) => str_contains($request->body(), '<PaymentCurrency>USD</PaymentCurrency>')
        && str_contains($request->body(), '<PaymentAmount>5347.59</PaymentAmount>'));
});

it('refuses the payment rather than assume parity when the rate is missing', function () {
    Http::fake();
    $plan = dpoPlan();

    $this->actingAs(dpoBuyer())
        ->post('/subscription/payment/dpo', ['plan_id' => $plan->id, 'currency' => 'USD'])
        ->assertSessionHasErrors('currency');

    expect(Payment::count())->toBe(0);
    Http::assertNothingSent();
});

/**
 * A tenant's override exists so they can price their own invoices. Letting one
 * move what Nilo charges for its own subscription would be a self-service
 * discount.
 */
it('ignores a tenant exchange rate override when pricing the subscription', function () {
    fakeCreateToken();
    seedZmwRate(18.7);

    $user = dpoBuyer();
    $company = Company::factory()->create();
    $user->companies()->attach($company->id, ['is_owner' => true, 'status' => 'active']);
    $user->forceFill(['current_company_id' => $company->id])->save();

    CompanyExchangeRate::create([
        'company_id' => $company->id,
        'base_code' => 'USD',
        'quote_code' => 'ZMW',
        'rate' => 1.0,
        'set_at' => now(),
    ]);

    $this->actingAs($user)
        ->post('/subscription/payment/dpo', ['plan_id' => dpoPlan()->id, 'currency' => 'USD']);

    expect(Payment::first()->charged_exchange_rate)->toBe(18.7);
});

it('keeps the rate frozen after the next sync moves it', function () {
    fakeCreateToken();
    $rate = seedZmwRate(18.7);

    $this->actingAs(dpoBuyer())
        ->post('/subscription/payment/dpo', ['plan_id' => dpoPlan()->id, 'currency' => 'USD']);

    $rate->update(['rate' => 25.0, 'fetched_at' => now()]);

    $payment = Payment::first()->fresh();

    expect($payment->charged_exchange_rate)->toBe(18.7)
        ->and((float) $payment->charged_amount)->toBe(5347.59);
});

it('rejects a currency outside the whitelist', function () {
    seedZmwRate();

    $this->actingAs(dpoBuyer())
        ->post('/subscription/payment/dpo', ['plan_id' => dpoPlan()->id, 'currency' => 'GBP'])
        ->assertSessionHasErrors('currency');
});

it('offers USD on the checkout screen only once a rate exists', function () {
    $plan = dpoPlan();
    $user = dpoBuyer();

    $this->actingAs($user)
        ->get("/subscription/payment/{$plan->id}")
        ->assertInertia(fn ($page) => $page
            ->component('subscription/payment')
            ->where('currencies', ['ZMW'])
        );

    seedZmwRate();

    $this->actingAs($user)
        ->get("/subscription/payment/{$plan->id}")
        ->assertInertia(fn ($page) => $page->where('currencies', ['ZMW', 'USD']));
});

it('quotes the converted charge on the checkout screen', function () {
    seedZmwRate();
    $plan = dpoPlan();

    $this->actingAs(dpoBuyer())
        ->get("/subscription/payment/{$plan->id}?currency=USD")
        ->assertInertia(fn ($page) => $page
            ->where('charge.currency', 'USD')
            ->where('charge.amount', 5347.59)
            ->where('charge.rate', 18.7)
        );
});
