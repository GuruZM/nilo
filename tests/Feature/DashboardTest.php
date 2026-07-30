<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $this->actingAs($user = User::factory()->withSubscription()->create());

    $this->get(route('dashboard'))->assertOk();
});

test('dashboard exposes a 12-month stat series for every KPI', function () {
    $user = User::factory()->withSubscription()->create();

    $company = Company::create([
        'owner_id' => $user->id,
        'name' => 'Acme Co',
        'slug' => 'acme-co',
    ]);

    $user->companies()->attach($company->id, [
        'is_owner' => true,
        'status' => 'active',
    ]);
    $user->forceFill(['current_company_id' => $company->id])->save();

    $client = Client::create([
        'company_id' => $company->id,
        'name' => 'Client One',
    ]);

    Invoice::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'issue_date' => Carbon::today(),
        'currency_code' => 'ZMW',
        'total' => 1000,
        'status' => 'paid',
    ]);

    Invoice::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'issue_date' => Carbon::today()->subMonths(2),
        'due_date' => Carbon::today()->subMonths(2)->startOfMonth(),
        'currency_code' => 'ZMW',
        'total' => 500,
        'status' => 'pending',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('charts.stat_series.paid', 12)
            ->has('charts.stat_series.pending', 12)
            ->has('charts.stat_series.overdue', 12)
            ->has('charts.stat_series.clients', 12)
            // One client created this month -> cumulative total ends at 1.
            ->where('charts.stat_series.clients.11', 1)
        );
});

test('dashboard no longer ships aging buckets or recent invoices', function () {
    $user = User::factory()->withSubscription()->create();

    $company = Company::create([
        'owner_id' => $user->id,
        'name' => 'Acme Co',
        'slug' => 'acme-co',
    ]);

    $user->companies()->attach($company->id, [
        'is_owner' => true,
        'status' => 'active',
    ]);
    $user->forceFill(['current_company_id' => $company->id])->save();

    $client = Client::create([
        'company_id' => $company->id,
        'name' => 'Client One',
    ]);

    Invoice::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'issue_date' => Carbon::today(),
        'due_date' => Carbon::today()->subDay(),
        'currency_code' => 'ZMW',
        'total' => 500,
        'status' => 'pending',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->missing('charts.aging')
            ->missing('recent_invoices')
            ->missing('stats.as_of')
            // The cards that stayed are still fed.
            ->has('charts.paid_vs_pending')
            ->has('charts.monthly_trend')
            ->has('calendar')
            ->has('top_clients')
            ->where('stats.overdue_revenue', 500)
        );
});

test('dashboard renders without a company and omits the removed props', function () {
    $user = User::factory()->withSubscription()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('company', null)
            ->missing('recent_invoices')
        );
});
