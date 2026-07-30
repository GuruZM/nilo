<?php

use App\Models\Client;
use App\Models\Supplier;

it('creates a supplier in the active company', function () {
    [$user] = invoiceCreationContext('client@example.com');

    $this->actingAs($user)
        ->post('/suppliers', [
            'name' => 'Zambezi Steel',
            'email' => 'sales@zambezisteel.example',
            'phone' => '+260 971 000 000',
            'contact_person' => 'M. Phiri',
        ])
        ->assertSessionHasNoErrors();

    $supplier = Supplier::query()->latest('id')->first();

    expect($supplier->name)->toBe('Zambezi Steel')
        ->and($supplier->company_id)->toBe($user->current_company_id);
});

it('requires a name', function () {
    [$user] = invoiceCreationContext('client@example.com');

    $this->actingAs($user)
        ->post('/suppliers', ['name' => ''])
        ->assertSessionHasErrors('name');
});

it('lists only the active company suppliers', function () {
    [$user] = invoiceCreationContext('client@example.com');
    [$otherUser] = invoiceCreationContext('client@example.com');

    $this->actingAs($user)->post('/suppliers', ['name' => 'Ours']);
    $this->actingAs($otherUser)->post('/suppliers', ['name' => 'Theirs']);

    $this->actingAs($user)
        ->get('/suppliers')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            // Drop the `false` once Task 4.2 adds resources/js/pages/Suppliers/Index.tsx;
            // config/inertia.php has ensure_pages_exist enabled.
            ->component('Suppliers/Index', false)
            ->has('suppliers', 1)
            ->where('suppliers.0.name', 'Ours')
        );
});

it('updates a supplier', function () {
    [$user] = invoiceCreationContext('client@example.com');

    $this->actingAs($user)->post('/suppliers', ['name' => 'Old name']);
    $supplier = Supplier::query()->latest('id')->first();

    $this->actingAs($user)
        ->put("/suppliers/{$supplier->id}", ['name' => 'New name'])
        ->assertSessionHasNoErrors();

    expect($supplier->fresh()->name)->toBe('New name');
});

it('will not touch a supplier in another company', function () {
    [$user] = invoiceCreationContext('client@example.com');
    [$otherUser] = invoiceCreationContext('client@example.com');

    $this->actingAs($otherUser)->post('/suppliers', ['name' => 'Theirs']);
    $foreign = Supplier::query()->latest('id')->first();

    $this->actingAs($user)
        ->put("/suppliers/{$foreign->id}", ['name' => 'Hijacked'])
        ->assertForbidden();

    expect($foreign->fresh()->name)->toBe('Theirs');
});

it('deletes a supplier', function () {
    [$user] = invoiceCreationContext('client@example.com');

    $this->actingAs($user)->post('/suppliers', ['name' => 'Temporary']);
    $supplier = Supplier::query()->latest('id')->first();

    $this->actingAs($user)
        ->delete("/suppliers/{$supplier->id}")
        ->assertSessionHasNoErrors();

    expect(Supplier::query()->count())->toBe(0);
});

it('keeps suppliers out of the client list', function () {
    [$user, $client] = invoiceCreationContext('client@example.com');

    $this->actingAs($user)
        ->post('/suppliers', ['name' => 'Zambezi Steel'])
        ->assertSessionHasNoErrors();

    // Without these two the assertion below would pass vacuously — a supplier
    // that was never stored, or an empty client list, proves nothing.
    expect(Supplier::query()->where('name', 'Zambezi Steel')->count())->toBe(1)
        ->and(Client::query()->where('company_id', $user->current_company_id)->count())->toBe(1);

    $this->actingAs($user)
        ->get('/clients')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('clients', 1)
            ->where('clients.0.name', $client->name)
            ->where('clients', fn ($clients) => collect($clients)
                ->pluck('name')
                ->doesntContain('Zambezi Steel'))
        );
});
