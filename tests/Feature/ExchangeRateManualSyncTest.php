<?php

use App\Models\Company;
use App\Models\ExchangeRate;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * The shape the keyless open endpoint returns.
 *
 * @param  array<string, float>  $rates
 */
function manualSyncResponse(array $rates = ['ZMW' => 26.85, 'EUR' => 0.92]): array
{
    return [
        'result' => 'success',
        'base_code' => 'USD',
        'time_last_update_unix' => 1785196800,
        'rates' => ['USD' => 1.0] + $rates,
    ];
}

function syncingUser(): User
{
    $user = User::factory()->withSubscription()->create();
    $company = Company::factory()->create();
    $user->companies()->attach($company->id);
    $user->forceFill(['current_company_id' => $company->id])->save();

    return $user;
}

it('refreshes the rates on demand', function () {
    Http::fake(['*' => Http::response(manualSyncResponse())]);

    $this->actingAs(syncingUser())
        ->post('/currencies/rates/sync')
        ->assertSessionHasNoErrors();

    expect(ExchangeRate::where('quote_code', 'ZMW')->first()->rate)->toBe(26.85);
});

it('refuses a second sync while the cooldown is still held', function () {
    Http::fake(['*' => Http::response(manualSyncResponse(['ZMW' => 26.85]))]);

    $user = syncingUser();

    $this->actingAs($user)->post('/currencies/rates/sync')->assertSessionHasNoErrors();

    Http::fake(['*' => Http::response(manualSyncResponse(['ZMW' => 99.0]))]);

    $this->actingAs($user)
        ->post('/currencies/rates/sync')
        ->assertSessionHas('error');

    expect(ExchangeRate::where('quote_code', 'ZMW')->first()->rate)->toBe(26.85);
});

it('leaves the stored rates alone when the provider fails', function () {
    $user = syncingUser();

    $failing = false;

    Http::fake(function () use (&$failing) {
        return $failing
            ? Http::response('nope', 500)
            : Http::response(manualSyncResponse(['ZMW' => 26.85]));
    });

    $this->actingAs($user)->post('/currencies/rates/sync')->assertSessionHas('success');

    /**
     * `Cache::clear()` does not drop a lock, so releasing it explicitly is the
     * only way to reach the provider-failure branch rather than the cooldown.
     */
    Cache::lock('exchange-rates:sync')->forceRelease();

    $failing = true;

    $this->actingAs($user)
        ->post('/currencies/rates/sync')
        ->assertSessionHas('error');

    expect(ExchangeRate::where('quote_code', 'ZMW')->first()->rate)->toBe(26.85);
});

it('does not let a guest trigger a sync', function () {
    Http::fake(['*' => Http::response(manualSyncResponse())]);

    $this->post('/currencies/rates/sync')->assertRedirect('/login');

    expect(ExchangeRate::count())->toBe(0);
});

it('does not burn the cooldown when the provider fails', function () {
    $user = syncingUser();

    /**
     * A closure stub rather than two `Http::fake` calls: the provider retries
     * internally, so the fake has to keep failing for the whole first attempt
     * and then keep succeeding for the whole second one.
     */
    $failing = true;

    Http::fake(function () use (&$failing) {
        return $failing
            ? Http::response('nope', 500)
            : Http::response(manualSyncResponse(['ZMW' => 26.85]));
    });

    $this->actingAs($user)->post('/currencies/rates/sync')->assertSessionHas('error');

    /** The retry must be allowed straight away, which is the point of the button. */
    $failing = false;

    $this->actingAs($user)->post('/currencies/rates/sync')->assertSessionHas('success');

    expect(ExchangeRate::where('quote_code', 'ZMW')->first()->rate)->toBe(26.85);
});
