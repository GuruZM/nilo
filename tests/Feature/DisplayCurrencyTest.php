<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\Invoice;
use App\Models\User;
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

    /** A company that bills in kwacha, with one K1,870 invoice. */
    $this->company = Company::factory()->create(['currency_code' => 'ZMW']);

    $this->user = User::factory()->withSubscription()->create([
        'current_currency_code' => 'ZMW',
    ]);

    $this->user->companies()->attach($this->company->id, [
        'is_owner' => true,
        'status' => 'active',
    ]);
    $this->user->forceFill(['current_company_id' => $this->company->id])->save();

    Invoice::factory()->in('ZMW')->worth(1870)->create([
        'company_id' => $this->company->id,
        'client_id' => Client::factory()->create(['company_id' => $this->company->id])->id,
        'status' => 'paid',
    ]);
});

it('reports figures in the user\'s chosen currency, not the company\'s', function () {
    $this->user->forceFill(['current_currency_code' => 'USD'])->save();

    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('stats.currency_code', 'USD')
                ->where('stats.paid_revenue', fn ($v) => (float) $v === 100.0)
        );
});

it('changes the dashboard totals when the currency is switched', function () {
    $inKwacha = $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->viewData('page')['props']['stats']['paid_revenue'];

    $this->actingAs($this->user)
        ->post('/currencies/switch', ['currency_code' => 'USD'])
        ->assertSessionHasNoErrors();

    $inDollars = $this->actingAs($this->user->fresh())
        ->get(route('dashboard'))
        ->viewData('page')['props']['stats']['paid_revenue'];

    expect((float) $inKwacha)->toBe(1870.0)
        ->and((float) $inDollars)->toBe(100.0);
});

it('labels the figures with the currency they were converted into', function () {
    $this->user->forceFill(['current_currency_code' => 'USD'])->save();

    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('stats.currency_code', 'USD')
                ->where('fx.display_code', 'USD')
        );
});

it('falls back to the company currency when the user has picked none', function () {
    $this->user->forceFill(['current_currency_code' => null])->save();

    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertInertia(
            fn (Assert $page) => $page->where('stats.currency_code', 'ZMW')
        );
});

it('converts the companies page totals into the chosen currency too', function () {
    $this->user->forceFill(['current_currency_code' => 'USD'])->save();

    $this->actingAs($this->user)
        ->get('/companies')
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('fx.display_code', 'USD')
                ->where('companies.0.paid_revenue', fn ($v) => (float) $v === 100.0)
        );
});
