<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Emailing an invoice moves it from `pending` to `sent`, so the dashboard must
 * treat both as money still owed. Counting only `pending` made an invoice's
 * amount disappear from every outstanding figure the moment it was sent.
 */
function dashboardInvoiceContext(): array
{
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

    return [$user, $company, $client];
}

test('sent invoices still count as outstanding revenue', function () {
    [$user, $company, $client] = dashboardInvoiceContext();

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
        'issue_date' => Carbon::today(),
        'due_date' => Carbon::today()->subDay(),
        'currency_code' => 'ZMW',
        'total' => 500,
        'status' => 'pending',
    ]);

    // Same money as the invoice above, only it has been emailed to the client.
    Invoice::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'issue_date' => Carbon::today(),
        'due_date' => Carbon::today()->subDay(),
        'currency_code' => 'ZMW',
        'total' => 700,
        'status' => 'sent',
    ]);

    Invoice::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'issue_date' => Carbon::today(),
        'due_date' => Carbon::today()->addDays(3),
        'currency_code' => 'ZMW',
        'total' => 300,
        'status' => 'sent',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('stats.total_invoices', 4)
            ->where('stats.paid_revenue', 1000)
            ->where('stats.paid_count', 1)
            // 500 pending + 700 sent + 300 sent
            ->where('stats.pending_revenue', 1500)
            ->where('stats.pending_count', 3)
            // 500 pending + 700 sent, both past due
            ->where('stats.overdue_revenue', 1200)
            ->where('stats.due_in_7_revenue', 300)
        );
});

test('the outstanding chart series and top clients include sent invoices', function () {
    [$user, $company, $client] = dashboardInvoiceContext();

    Invoice::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'issue_date' => Carbon::today(),
        'due_date' => Carbon::today()->addDays(3),
        'currency_code' => 'ZMW',
        'total' => 800,
        'status' => 'sent',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('charts.paid_vs_pending.counts', [0, 1])
            ->where('charts.paid_vs_pending.revenue', [0, 800])
            // The current month is the last bucket of the 12-month window.
            ->where('charts.stat_series.pending.11', 800)
            ->where('top_clients.0.pending_total', 800)
            ->where('top_clients.0.name', 'Client One')
        );
});

test('the companies list counts sent invoices as outstanding revenue', function () {
    [$user, $company, $client] = dashboardInvoiceContext();

    Invoice::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'issue_date' => Carbon::today(),
        'currency_code' => 'ZMW',
        'total' => 400,
        'status' => 'pending',
    ]);

    Invoice::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'issue_date' => Carbon::today(),
        'currency_code' => 'ZMW',
        'total' => 600,
        'status' => 'sent',
    ]);

    $this->actingAs($user)
        ->get(route('companies.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Companies/Index')
            ->where('companies.0.pending_revenue', 1000)
        );
});

test('void invoices are excluded from outstanding revenue', function () {
    [$user, $company, $client] = dashboardInvoiceContext();

    Invoice::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'issue_date' => Carbon::today(),
        'due_date' => Carbon::today()->subDay(),
        'currency_code' => 'ZMW',
        'total' => 900,
        'status' => 'void',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('stats.pending_revenue', 0)
            ->where('stats.pending_count', 0)
            ->where('stats.overdue_revenue', 0)
        );
});
