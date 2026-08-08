<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\InvoicePayment;

beforeEach(function () {
    Currency::query()->updateOrCreate(['code' => 'ZMW'], [
        'name' => 'Zambian Kwacha', 'symbol' => 'K', 'precision' => 2, 'is_active' => true,
    ]);
});

it('reports counts and money for the active company', function () {
    [$user, $client] = apiContext();

    Invoice::factory()->paid()->worth(3000)->create([
        'company_id' => $user->current_company_id, 'client_id' => $client->id, 'currency_code' => 'ZMW',
    ]);
    Invoice::factory()->worth(5000)->create([
        'company_id' => $user->current_company_id, 'client_id' => $client->id,
        'currency_code' => 'ZMW', 'status' => 'sent', 'due_date' => now()->addMonth(),
    ]);

    $response = $this->withHeaders(apiHeaders($user))->getJson(route('api.v1.dashboard'));

    $response->assertSuccessful()
        ->assertJsonStructure([
            'currency' => ['code', 'precision', 'rates_as_of'],
            'counts' => ['invoices', 'paid', 'outstanding', 'overdue', 'clients'],
            'money' => ['paid', 'outstanding', 'overdue', 'due_in_7_days'],
            'recent_invoices',
        ])
        ->assertJsonPath('counts.invoices', 2)
        ->assertJsonPath('counts.paid', 1)
        ->assertJsonPath('counts.outstanding', 1)
        ->assertJsonPath('counts.clients', 1);

    expect((float) $response->json('money.paid'))->toBe(3000.00)
        ->and((float) $response->json('money.outstanding'))->toBe(5000.00);
});

/**
 * An invoice's total is what it was billed for, not what is left on it. Part
 * payments have to come off before anything is summed, or the dashboard would
 * claim money that has already arrived is still owed.
 */
it('nets part payments off the outstanding figure', function () {
    [$user, $client] = apiContext();

    $invoice = Invoice::factory()->worth(5000)->create([
        'company_id' => $user->current_company_id, 'client_id' => $client->id,
        'currency_code' => 'ZMW', 'status' => 'sent',
    ]);

    InvoicePayment::factory()->create([
        'company_id' => $user->current_company_id,
        'invoice_id' => $invoice->id,
        'amount' => 2000,
        'currency_code' => 'ZMW',
    ]);

    $response = $this->withHeaders(apiHeaders($user))->getJson(route('api.v1.dashboard'));

    expect((float) $response->json('money.outstanding'))->toBe(3000.00);
});

it('counts an invoice past its due date as overdue', function () {
    [$user, $client] = apiContext();

    Invoice::factory()->worth(1000)->create([
        'company_id' => $user->current_company_id, 'client_id' => $client->id,
        'currency_code' => 'ZMW', 'status' => 'sent', 'due_date' => now()->subWeek(),
    ]);

    $response = $this->withHeaders(apiHeaders($user))->getJson(route('api.v1.dashboard'));

    $response->assertJsonPath('counts.overdue', 1);

    expect((float) $response->json('money.overdue'))->toBe(1000.00);
});

it('separates what falls due this week from what is already late', function () {
    [$user, $client] = apiContext();

    Invoice::factory()->worth(1000)->create([
        'company_id' => $user->current_company_id, 'client_id' => $client->id,
        'currency_code' => 'ZMW', 'status' => 'sent', 'due_date' => now()->addDays(3),
    ]);
    Invoice::factory()->worth(400)->create([
        'company_id' => $user->current_company_id, 'client_id' => $client->id,
        'currency_code' => 'ZMW', 'status' => 'sent', 'due_date' => now()->addMonths(2),
    ]);

    $response = $this->withHeaders(apiHeaders($user))->getJson(route('api.v1.dashboard'));

    expect((float) $response->json('money.due_in_7_days'))->toBe(1000.00)
        ->and((float) $response->json('money.overdue'))->toBe(0.00)
        ->and((float) $response->json('money.outstanding'))->toBe(1400.00);
});

it('reports on the company named in the header, not the stored one', function () {
    [$user, $client] = apiContext();

    Invoice::factory()->count(3)->create([
        'company_id' => $user->current_company_id, 'client_id' => $client->id, 'currency_code' => 'ZMW',
    ]);

    $second = Company::factory()->create(['currency_code' => 'ZMW']);
    $user->companies()->attach($second->id, ['is_owner' => true, 'status' => 'active']);

    Invoice::factory()->create([
        'company_id' => $second->id,
        'client_id' => Client::factory()->create(['company_id' => $second->id])->id,
        'currency_code' => 'ZMW',
    ]);

    $this->withHeaders(apiHeaders($user, $second->id))
        ->getJson(route('api.v1.dashboard'))
        ->assertSuccessful()
        ->assertJsonPath('counts.invoices', 1);
});

it('returns zeroes rather than failing for a company with nothing on it', function () {
    [$user] = apiContext();

    $response = $this->withHeaders(apiHeaders($user))->getJson(route('api.v1.dashboard'));

    $response->assertSuccessful()->assertJsonPath('counts.invoices', 0);

    expect((float) $response->json('money.outstanding'))->toBe(0.00);
});
