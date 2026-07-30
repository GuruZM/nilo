<?php

use App\Models\Company;
use App\Models\Currency;
use App\Models\Plan;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Currency::create([
        'code' => 'ZMW',
        'name' => 'Zambian Kwacha',
        'symbol' => 'K',
        'precision' => 2,
        'is_active' => true,
    ]);
});

/**
 * A user whose plan allows exactly one company, with that allowance already
 * spent — the state in which creating another silently did nothing.
 */
function userAtCompanyLimit(): User
{
    $user = User::factory()->withSubscription('starter')->create();

    Plan::query()->where('slug', 'starter')->update(['max_companies' => 1]);

    test()->actingAs($user)
        ->post('/companies', [
            'name' => 'First Co',
            'type' => 'services',
            'currency_code' => 'ZMW',
        ])
        ->assertSessionHasNoErrors();

    return $user;
}

it('creates a company when the plan still has room', function () {
    $user = User::factory()->withSubscription()->create();

    $this->actingAs($user)
        ->post('/companies', [
            'name' => 'Roomy Co',
            'type' => 'services',
            'currency_code' => 'ZMW',
        ])
        ->assertSessionHasNoErrors();

    expect(Company::where('name', 'Roomy Co')->exists())->toBeTrue();
});

it('does not create a second company past the plan limit', function () {
    $user = userAtCompanyLimit();

    $this->actingAs($user)
        ->post('/companies', [
            'name' => 'Second Co',
            'type' => 'services',
            'currency_code' => 'ZMW',
        ]);

    expect(Company::where('name', 'Second Co')->exists())->toBeFalse()
        ->and(Company::count())->toBe(1);
});

/**
 * The refusal is a redirect with no validation errors, so Inertia hands it to
 * the client's success path. Without something on the page it can read, the
 * dialog closed announcing a company that was never written.
 */
it('flashes a limit notice the companies page can render', function () {
    $user = userAtCompanyLimit();

    $this->actingAs($user)
        ->post('/companies', [
            'name' => 'Second Co',
            'type' => 'services',
            'currency_code' => 'ZMW',
        ])
        ->assertSessionHas('limit_notice');
});

it('hands the companies page a notice it can render as a dialog', function () {
    $user = userAtCompanyLimit();

    $this->actingAs($user)
        ->post('/companies', [
            'name' => 'Second Co',
            'type' => 'services',
            'currency_code' => 'ZMW',
        ]);

    $this->actingAs($user)
        ->get('/companies')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Companies/Index')
            ->has('limitNotice', fn (Assert $notice) => $notice
                ->where('title', 'Plan limit reached')
                ->has('message')
                ->has('action_label')
                ->has('action_href')
            )
        );
});

it('leaves the notice absent on an ordinary visit', function () {
    $user = User::factory()->withSubscription()->create();

    $this->actingAs($user)
        ->get('/companies')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Companies/Index')
            ->where('limitNotice', null)
        );
});

/**
 * `Log` was never imported here, so the rescue path threw "Class
 * App\Http\Controllers\Log not found" and turned any recoverable failure into
 * a 500 with no message.
 */
it('can reach its own failure logging without fataling', function () {
    $controller = file_get_contents(__DIR__.'/../../app/Http/Controllers/CompanyController.php');

    expect($controller)
        ->toContain('use Illuminate\Support\Facades\Log;')
        ->toContain("Log::error('Company creation failed'");
});

it('does not claim success when the create was refused', function () {
    $page = file_get_contents(__DIR__.'/../../resources/js/pages/Companies/Index.tsx');

    expect($page)
        ->toContain('<LimitNoticeDialog notice={limitNotice} />')
        // The success path checks what came back before announcing anything.
        ->toContain('onSuccess: (page) => {')
        ->toContain('.limitNotice');
});
