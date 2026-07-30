<?php

use App\Models\Currency;
use App\Models\Invoice;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The dashboard lagged behind document CRUD by up to 30 seconds. The server was
 * never at fault — it recomputes every figure per request — so these tests pin
 * that down as an invariant, and then guard the client-side cause: Inertia's
 * prefetch cache served the pre-mutation snapshot back to the user.
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

it('reflects a freshly created invoice on the very next dashboard request', function () {
    [$user, $client, $template] = invoiceCreationContext('client@example.com');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.total_invoices', 0)
            ->where('stats.pending_revenue', 0)
        );

    $this->actingAs($user)
        ->post('/invoices', invoicePayload($client, $template, false))
        ->assertRedirect();

    /** 2 × 500, tax and discount free. */
    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.total_invoices', 1)
            ->where('stats.pending_count', 1)
            ->where('stats.pending_revenue', 1000)
            ->where('stats.paid_revenue', 0)
        );
});

it('moves money from pending to paid as soon as the status changes', function () {
    [$user, $client, $template] = invoiceCreationContext('client@example.com');

    $this->actingAs($user)->post('/invoices', invoicePayload($client, $template, false));
    $invoice = Invoice::query()->latest('id')->first();

    $this->actingAs($user)
        ->post("/invoices/{$invoice->id}/status", ['status' => 'paid'])
        ->assertRedirect();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.paid_count', 1)
            ->where('stats.paid_revenue', 1000)
            ->where('stats.pending_count', 0)
            ->where('stats.pending_revenue', 0)
        );
});

it('drops a deleted client from the dashboard count immediately', function () {
    [$user, $client] = invoiceCreationContext('client@example.com');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('stats.client_count', 1));

    $this->actingAs($user)
        ->delete("/clients/{$client->id}")
        ->assertRedirect();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('stats.client_count', 0));
});

/**
 * The actual root cause. Every sidebar link is `prefetch`, so Inertia holds
 * those pages for 30s and `router.visit` serves a cached hit without asking the
 * server. Inertia only evicts entries whose `cacheTags` intersect a visit's
 * `invalidateCacheTags`, and nothing in this app sets either, so a mutation
 * evicted nothing.
 */
it('flushes the prefetch cache after any mutating visit', function () {
    $app = file_get_contents(__DIR__.'/../../resources/js/app.tsx');

    expect($app)
        ->toContain("router.on('finish'")
        ->toContain("event.detail.visit.method !== 'get'")
        ->toContain('router.flushAll();');
});

/**
 * Prefetch is what makes the cache worth flushing; if the nav ever drops it the
 * flush above becomes dead code rather than a silent regression.
 */
it('still prefetches the nav links it invalidates', function () {
    expect(file_get_contents(__DIR__.'/../../resources/js/components/nav-main.tsx'))
        ->toContain('prefetch');
});
