<?php

use App\Models\Company;
use App\Models\CompanyExchangeRate;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    collect([['USD', true], ['ZMW', true], ['EUR', true], ['JPY', false]])
        ->each(fn (array $row) => Currency::create([
            'code' => $row[0],
            'name' => $row[0],
            'symbol' => null,
            'precision' => 2,
            'is_active' => $row[1],
        ]));

    $this->company = Company::factory()->create(['currency_code' => 'ZMW']);

    $this->user = User::factory()->withSubscription()->create([
        'current_currency_code' => 'ZMW',
    ]);

    $this->user->companies()->attach($this->company->id);
    $this->user->forceFill(['current_company_id' => $this->company->id])->save();

    $this->synced = ExchangeRate::create([
        'base_code' => 'USD',
        'quote_code' => 'ZMW',
        'rate' => 26.85,
        'fetched_at' => Carbon::parse('2026-07-30 00:00:00'),
    ]);

    $this->synced->forceFill(['updated_at' => Carbon::parse('2026-07-30 01:00:00')])->saveQuietly();
});

it('stores a manual rate for the active company', function () {
    $this->actingAs($this->user)
        ->post('/currencies/ZMW/rate', ['rate' => 27.5])
        ->assertSessionHasNoErrors();

    $override = CompanyExchangeRate::where('company_id', $this->company->id)->first();

    expect($override->quote_code)->toBe('ZMW')
        ->and($override->rate)->toBe(27.5)
        ->and($override->set_by)->toBe($this->user->id);
});

it('replaces an existing manual rate rather than adding a second row', function () {
    $this->actingAs($this->user)->post('/currencies/ZMW/rate', ['rate' => 27.5]);
    $this->actingAs($this->user)->post('/currencies/ZMW/rate', ['rate' => 28.0]);

    expect(CompanyExchangeRate::where('company_id', $this->company->id)->count())->toBe(1)
        ->and(CompanyExchangeRate::where('company_id', $this->company->id)->first()->rate)->toBe(28.0);
});

it('puts a superseded override back in force when it is saved again', function () {
    CompanyExchangeRate::create([
        'company_id' => $this->company->id,
        'base_code' => 'USD',
        'quote_code' => 'ZMW',
        'rate' => 27.5,
        'set_at' => Carbon::parse('2026-07-30 00:30:00'),
    ]);

    /** Superseded by the 01:00 sync, so the invoice would freeze the synced rate. */
    expect(Invoice::factory()->for($this->company)->in('ZMW')->create()->exchange_rate_to_base)
        ->toBe(26.85);

    $this->actingAs($this->user)->post('/currencies/ZMW/rate', ['rate' => 27.5]);

    expect(Invoice::factory()->for($this->company)->in('ZMW')->create()->exchange_rate_to_base)
        ->toBe(27.5);
});

it('clears a manual rate', function () {
    $this->actingAs($this->user)->post('/currencies/ZMW/rate', ['rate' => 27.5]);

    $this->actingAs($this->user)
        ->delete('/currencies/ZMW/rate')
        ->assertSessionHasNoErrors();

    expect(CompanyExchangeRate::where('company_id', $this->company->id)->count())->toBe(0);
});

it('does not clear another company manual rate', function () {
    $other = Company::factory()->create();

    CompanyExchangeRate::create([
        'company_id' => $other->id,
        'base_code' => 'USD',
        'quote_code' => 'ZMW',
        'rate' => 30.0,
        'set_at' => now(),
    ]);

    $this->actingAs($this->user)->delete('/currencies/ZMW/rate');

    expect(CompanyExchangeRate::where('company_id', $other->id)->count())->toBe(1);
});

it('rejects a rate of zero or below', function (float $rate) {
    $this->actingAs($this->user)
        ->post('/currencies/ZMW/rate', ['rate' => $rate])
        ->assertSessionHasErrors('rate');

    expect(CompanyExchangeRate::count())->toBe(0);
})->with([0.0, -1.0]);

it('rejects a non-numeric rate', function () {
    $this->actingAs($this->user)
        ->post('/currencies/ZMW/rate', ['rate' => 'many'])
        ->assertSessionHasErrors('rate');

    expect(CompanyExchangeRate::count())->toBe(0);
});

it('rejects a currency that is not active', function () {
    $this->actingAs($this->user)
        ->post('/currencies/JPY/rate', ['rate' => 150.0])
        ->assertSessionHasErrors('code');

    expect(CompanyExchangeRate::count())->toBe(0);
});

it('does not let a guest store a manual rate', function () {
    $this->post('/currencies/ZMW/rate', ['rate' => 27.5])->assertRedirect('/login');

    expect(CompanyExchangeRate::count())->toBe(0);
});

it('shows the rate board with the override in force', function () {
    $this->actingAs($this->user)->post('/currencies/ZMW/rate', ['rate' => 27.5]);

    $this->actingAs($this->user)
        ->get('/settings/currencies')
        ->assertInertia(fn ($page) => $page
            ->where('fx.base', 'USD')
            ->where('fx.rows.1.code', 'ZMW')
            ->where('fx.rows.1.synced_rate', 26.85)
            ->where('fx.rows.1.override_rate', 27.5)
            ->where('fx.rows.1.in_force', true));
});

it('shows an override as not in force once a sync has superseded it', function () {
    CompanyExchangeRate::create([
        'company_id' => $this->company->id,
        'base_code' => 'USD',
        'quote_code' => 'ZMW',
        'rate' => 27.5,
        'set_at' => Carbon::parse('2026-07-30 00:30:00'),
    ]);

    $this->actingAs($this->user)
        ->get('/settings/currencies')
        ->assertInertia(fn ($page) => $page
            ->where('fx.rows.1.override_rate', 27.5)
            ->where('fx.rows.1.in_force', false));
});

it('does not show another company override on the rate board', function () {
    $other = Company::factory()->create();

    CompanyExchangeRate::create([
        'company_id' => $other->id,
        'base_code' => 'USD',
        'quote_code' => 'ZMW',
        'rate' => 27.5,
        'set_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->get('/settings/currencies')
        ->assertInertia(fn ($page) => $page->where('fx.rows.1.override_rate', null));
});
