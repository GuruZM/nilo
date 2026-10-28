<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\Currency;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A client added outside the invoice builder — on the clients page, in another
 * tab, or before pressing the browser's back button — was missing from the
 * builder's picker. These pin both halves of the cause: the client controller
 * must file records under the same company the builders read from, and the
 * builders must re-read their picker props instead of trusting a cached page.
 */
beforeEach(function () {
    Currency::create([
        'code' => 'ZMW',
        'name' => 'Zambian Kwacha',
        'symbol' => 'K',
        'precision' => 2,
        'is_active' => true,
    ]);

    Mail::fake();
});

it('lists a client added on the clients page in the invoice picker', function () {
    [$user] = invoiceCreationContext(null);

    $this->actingAs($user)
        ->from('/clients')
        ->post('/clients', ['name' => 'Kabwe Hardware'])
        ->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->get('/invoices/create')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Invoices/Create')
            ->where('clients', fn ($clients) => collect($clients)->pluck('name')->contains('Kabwe Hardware'))
        );
});

/**
 * The documents fall back to the user's first company when the stored one is
 * not theirs; the client controller used to write into the stored one anyway,
 * so the new client sat in a company no picker ever showed.
 */
it('files a new client under the company the invoice picker reads from', function () {
    [$user] = invoiceCreationContext(null);
    $memberCompanyId = $user->current_company_id;

    $foreignCompany = Company::factory()->create();
    $user->forceFill(['current_company_id' => $foreignCompany->id])->save();

    $this->actingAs($user)
        ->from('/invoices/create')
        ->post('/clients', ['name' => 'Kabwe Hardware'])
        ->assertSessionHasNoErrors();

    expect(Client::where('name', 'Kabwe Hardware')->sole()->company_id)->toBe($memberCompanyId)
        ->and(Client::where('company_id', $foreignCompany->id)->exists())->toBeFalse();

    $this->actingAs($user)
        ->get('/invoices/create')
        ->assertInertia(fn (Assert $page) => $page
            ->where('clients', fn ($clients) => collect($clients)->pluck('name')->contains('Kabwe Hardware'))
        );
});

it('lists clients of the company the documents use on the clients page', function () {
    [$user, $client] = invoiceCreationContext(null);

    $foreignCompany = Company::factory()->create();
    Client::factory()->create(['company_id' => $foreignCompany->id, 'name' => 'Not Yours Ltd']);
    $user->forceFill(['current_company_id' => $foreignCompany->id])->save();

    $this->actingAs($user)
        ->get('/clients')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clients/Index')
            ->where('clients', fn ($clients) => collect($clients)->pluck('name')->all() === [$client->name])
        );
});

it('refuses to edit or delete a client of a company the user does not belong to', function () {
    [$user] = invoiceCreationContext(null);

    $foreignCompany = Company::factory()->create();
    $foreignClient = Client::factory()->create(['company_id' => $foreignCompany->id]);
    $user->forceFill(['current_company_id' => $foreignCompany->id])->save();

    $this->actingAs($user)
        ->put("/clients/{$foreignClient->id}", ['name' => 'Hijacked'])
        ->assertSessionHasErrors('client');

    $this->actingAs($user)
        ->delete("/clients/{$foreignClient->id}")
        ->assertSessionHasErrors('client');

    expect($foreignClient->fresh()->name)->not->toBe('Hijacked');
});

/**
 * Prefetched links, the back button and other tabs all hand a builder a page
 * captured before the record existed. Each builder re-reads its pickers on
 * mount and whenever the tab becomes visible again.
 */
it('refreshes the picker props on every document builder', function (string $page, array $props) {
    $source = file_get_contents(__DIR__."/../../resources/js/pages/{$page}");

    /** Prettier wraps a long list across lines, so compare without whitespace. */
    $compact = preg_replace('/\s+/', '', $source);
    $quoted = implode(',', array_map(fn (string $prop) => "'{$prop}'", $props));

    expect($compact)->toContain("useFreshProps([{$quoted}")
        ->and($source)->toContain("from '@/hooks/use-fresh-props'");
})->with([
    'invoice' => ['Invoices/Create.tsx', ['clients', 'templates', 'prerequisites', 'hasActiveCompany']],
    'quotation' => ['Quotations/Create.tsx', ['clients', 'templates', 'prerequisites', 'hasActiveCompany']],
    'receipt' => ['Receipts/Create.tsx', ['clients']],
    'credit note' => ['CreditNotes/Create.tsx', ['invoices']],
    'purchase order' => ['PurchaseOrders/Create.tsx', ['suppliers']],
]);

it('re-reads on mount and when the tab becomes visible', function () {
    $hook = file_get_contents(__DIR__.'/../../resources/js/hooks/use-fresh-props.ts');

    expect($hook)
        ->toContain('router.reload({ only')
        ->toContain("'visibilitychange'")
        ->toContain("document.visibilityState === 'visible'");
});
