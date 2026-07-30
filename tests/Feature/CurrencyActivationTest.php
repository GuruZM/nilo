<?php

use App\Models\Company;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\User;

beforeEach(function () {
    collect([
        ['ZMW', 'Zambian Kwacha', true],
        ['USD', 'US Dollar', true],
        ['EUR', 'Euro', false],
        ['GBP', 'British Pound', false],
        ['JPY', 'Japanese Yen', false],
    ])->each(fn (array $row) => Currency::create([
        'code' => $row[0],
        'name' => $row[1],
        'symbol' => null,
        'precision' => 2,
        'is_active' => $row[2],
    ]));
});

/**
 * A user whose active currency is ZMW and who owns nothing else.
 */
function currencyUser(): User
{
    return User::factory()->withSubscription()->create([
        'current_currency_code' => 'ZMW',
    ]);
}

it('activates the submitted currencies', function () {
    $this->actingAs(currencyUser())
        ->post('/currencies/active', ['codes' => ['ZMW', 'EUR', 'JPY']])
        ->assertSessionHasNoErrors();

    expect(Currency::where('is_active', true)->pluck('code')->sort()->values()->all())
        ->toBe(['EUR', 'JPY', 'ZMW']);
});

it('deactivates currencies left out of the submission', function () {
    $this->actingAs(currencyUser())
        ->post('/currencies/active', ['codes' => ['ZMW']])
        ->assertSessionHasNoErrors();

    expect(Currency::where('code', 'USD')->first()->is_active)->toBeFalse();
});

it('accepts lowercase codes', function () {
    $this->actingAs(currencyUser())
        ->post('/currencies/active', ['codes' => ['zmw', 'eur']])
        ->assertSessionHasNoErrors();

    expect(Currency::where('code', 'EUR')->first()->is_active)->toBeTrue();
});

it('refuses to deactivate the current user\'s active currency', function () {
    $this->actingAs(currencyUser())
        ->post('/currencies/active', ['codes' => ['USD']])
        ->assertSessionHasErrors('codes');

    expect(Currency::where('code', 'ZMW')->first()->is_active)->toBeTrue();
});

it('refuses to deactivate a currency a company bills in', function () {
    $user = currencyUser();

    Company::factory()->create(['currency_code' => 'USD']);

    $this->actingAs($user)
        ->post('/currencies/active', ['codes' => ['ZMW']])
        ->assertSessionHasErrors('codes');

    expect(Currency::where('code', 'USD')->first()->is_active)->toBeTrue();
});

it('refuses to deactivate a currency an invoice was issued in', function () {
    $user = currencyUser();

    $company = Company::factory()->create(['currency_code' => 'ZMW']);

    Invoice::factory()->create([
        'company_id' => $company->id,
        'currency_code' => 'USD',
    ]);

    $this->actingAs($user)
        ->post('/currencies/active', ['codes' => ['ZMW']])
        ->assertSessionHasErrors('codes');

    expect(Currency::where('code', 'USD')->first()->is_active)->toBeTrue();
});

it('writes nothing at all when one currency is blocked', function () {
    $this->actingAs(currencyUser())
        ->post('/currencies/active', ['codes' => ['EUR']])
        ->assertSessionHasErrors('codes');

    expect(Currency::where('code', 'EUR')->first()->is_active)->toBeFalse()
        ->and(Currency::where('code', 'USD')->first()->is_active)->toBeTrue();
});

it('allows deactivating an unused currency', function () {
    $this->actingAs(currencyUser())
        ->post('/currencies/active', ['codes' => ['ZMW', 'USD']])
        ->assertSessionHasNoErrors();

    expect(Currency::where('code', 'EUR')->first()->is_active)->toBeFalse();
});

it('requires the codes field', function () {
    $this->actingAs(currencyUser())
        ->post('/currencies/active', [])
        ->assertSessionHasErrors('codes');
});

it('rejects guests', function () {
    $this->post('/currencies/active', ['codes' => ['ZMW']])
        ->assertRedirect('/login');
});
