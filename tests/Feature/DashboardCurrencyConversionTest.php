<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    foreach (['ZMW' => 'Zambian Kwacha', 'USD' => 'US Dollar'] as $code => $name) {
        Currency::create([
            'code' => $code,
            'name' => $name,
            'symbol' => null,
            'precision' => 2,
            'is_active' => true,
        ]);
    }

    foreach (['USD' => 1.0, 'ZMW' => 18.7] as $code => $rate) {
        ExchangeRate::create([
            'base_code' => 'USD',
            'quote_code' => $code,
            'rate' => $rate,
            'fetched_at' => now(),
        ]);
    }
});

/**
 * A user with one active company that bills in the given currency.
 */
function dashboardUser(string $companyCurrency = 'USD'): User
{
    $user = User::factory()->withSubscription()->create([
        'current_currency_code' => $companyCurrency,
    ]);

    $company = Company::factory()->create(['currency_code' => $companyCurrency]);

    $user->companies()->attach($company->id, ['is_owner' => true, 'status' => 'active']);
    $user->forceFill(['current_company_id' => $company->id])->save();

    return $user;
}

function invoiceIn(User $user, string $currency, float $total, string $status = 'paid'): Invoice
{
    $companyId = $user->current_company_id;

    return Invoice::factory()->in($currency)->worth($total)->create([
        'company_id' => $companyId,
        'client_id' => Client::factory()->create(['company_id' => $companyId])->id,
        'status' => $status,
        'issue_date' => Carbon::today(),
    ]);
}

it('converts mixed-currency revenue into the display currency', function () {
    $user = dashboardUser('USD');

    invoiceIn($user, 'USD', 100);
    invoiceIn($user, 'ZMW', 1870);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(
            fn (Assert $page) => $page->where('stats.paid_revenue', fn ($v) => (float) $v === 200.0)
        );
});

it('no longer adds raw amounts across currencies', function () {
    $user = dashboardUser('USD');

    invoiceIn($user, 'USD', 100);
    invoiceIn($user, 'ZMW', 1870);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(
            fn (Assert $page) => $page->where('stats.paid_revenue', fn ($v) => $v !== 1970.0)
        );
});

it('leaves a single-currency company\'s totals untouched', function () {
    $user = dashboardUser('ZMW');

    invoiceIn($user, 'ZMW', 1000);
    invoiceIn($user, 'ZMW', 500);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(
            fn (Assert $page) => $page->where('stats.paid_revenue', fn ($v) => (float) $v === 1500.0)
        );
});

it('reports amounts it could not convert rather than dropping them', function () {
    $user = dashboardUser('USD');

    invoiceIn($user, 'USD', 100);
    invoiceIn($user, 'XOF', 50000);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('stats.paid_revenue', fn ($v) => (float) $v === 100.0)
                ->where('fx.unconvertible', ['XOF'])
        );
});

it('exposes the display currency and rate date', function () {
    $user = dashboardUser('USD');

    invoiceIn($user, 'USD', 100);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('fx.display_code', 'USD')
                ->whereNot('fx.rates_as_of', null)
        );
});

it('uses the rate frozen on an invoice for the monthly series', function () {
    $user = dashboardUser('USD');

    // Issued when the kwacha was 10 per dollar, not today's 18.7.
    $invoice = invoiceIn($user, 'ZMW', 1000);
    $invoice->forceFill(['exchange_rate_to_base' => 10.0])->save();

    $month = Carbon::today()->format('M Y');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(function (Assert $page) use ($month) {
            $labels = $page->toArray()['props']['charts']['stat_series']['labels'];
            $paid = $page->toArray()['props']['charts']['stat_series']['paid'];

            $index = array_search($month, $labels, true);

            expect((float) $paid[$index])->toBe(100.0);
        });
});

it('still renders when no rates have been synced at all', function () {
    ExchangeRate::query()->delete();

    $user = dashboardUser('ZMW');

    invoiceIn($user, 'ZMW', 1000);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page->where('stats.paid_revenue', fn ($v) => (float) $v === 1000.0)
        );
});
