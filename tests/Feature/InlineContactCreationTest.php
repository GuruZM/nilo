<?php

use App\Models\Client;
use App\Models\Currency;
use App\Models\Supplier;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

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

/**
 * Realising mid-invoice that the client was never added used to mean leaving
 * for the clients page and losing the half-built document. These cover the
 * round trip that replaces it: post the client from where you are, land back on
 * the builder, and have the new client already picked.
 */
it('creates a client posted from a half-built invoice', function () {
    [$user] = invoiceCreationContext(null);

    $this->actingAs($user)
        ->from('/invoices/create')
        ->post('/clients', [
            'name' => 'Kabwe Hardware',
            'contact_person' => 'Amara Phiri',
            'email' => 'accounts@kabwehardware.test',
            'phone' => '+260 900 000 000',
        ])
        ->assertRedirect('/invoices/create')
        ->assertSessionHasNoErrors();

    $client = Client::where('name', 'Kabwe Hardware')->sole();

    expect($client->company_id)->toBe($user->current_company_id)
        ->and($client->contact_person)->toBe('Amara Phiri')
        ->and($client->email)->toBe('accounts@kabwehardware.test')
        ->and($client->phone)->toBe('+260 900 000 000');
});

/**
 * The picker cannot tell which of the refreshed clients is the new one without
 * this — the id is what auto-selects it on the document being built.
 */
it('flashes the new client id so the picker can select it', function () {
    [$user] = invoiceCreationContext(null);

    $this->actingAs($user)
        ->from('/invoices/create')
        ->post('/clients', ['name' => 'Kabwe Hardware'])
        ->assertSessionHas(
            'created_client_id',
            Client::where('name', 'Kabwe Hardware')->value('id')
        );
});

it('exposes the new client id on the inertia flash prop', function () {
    [$user] = invoiceCreationContext(null);

    $created = Client::create([
        'company_id' => $user->current_company_id,
        'name' => 'Kabwe Hardware',
    ]);

    $this->actingAs($user)
        ->withSession(['created_client_id' => $created->id])
        ->get('/invoices/create')
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Invoices/Create')
                ->where('flash.created_client_id', $created->id)
        );
});

it('refuses a client name that already exists in the company', function () {
    [$user, $client] = invoiceCreationContext(null);

    $this->actingAs($user)
        ->from('/invoices/create')
        ->post('/clients', ['name' => $client->name])
        ->assertSessionHasErrors('name');

    expect(Client::where('company_id', $user->current_company_id)->count())->toBe(1);
});

it('refuses a client with no name', function () {
    [$user] = invoiceCreationContext(null);

    $this->actingAs($user)
        ->from('/invoices/create')
        ->post('/clients', ['name' => ''])
        ->assertSessionHasErrors('name');
});

it('creates a supplier posted from a half-built purchase order', function () {
    [$user] = invoiceCreationContext(null);

    $this->actingAs($user)
        ->from('/purchase-orders/create')
        ->post('/suppliers', [
            'name' => 'Copperbelt Steel',
            'contact_person' => 'Mwansa Banda',
            'email' => 'sales@copperbeltsteel.test',
            'phone' => '+260 955 111 222',
        ])
        ->assertRedirect('/purchase-orders/create')
        ->assertSessionHasNoErrors()
        ->assertSessionHas(
            'created_supplier_id',
            Supplier::where('name', 'Copperbelt Steel')->value('id')
        );

    expect(Supplier::where('name', 'Copperbelt Steel')->sole()->company_id)
        ->toBe($user->current_company_id);
});

/**
 * A client created from one company's invoice must never surface in another's
 * picker, so the endpoint has to keep scoping even when it is called from a
 * document builder rather than the clients page.
 */
it('scopes an inline client to the active company only', function () {
    [$user] = invoiceCreationContext(null);
    [$otherUser] = invoiceCreationContext(null);

    $this->actingAs($user)
        ->from('/invoices/create')
        ->post('/clients', ['name' => 'Kabwe Hardware']);

    $this->actingAs($otherUser)
        ->get('/invoices/create')
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Invoices/Create')
                ->where('clients', fn ($clients) => collect($clients)
                    ->pluck('name')
                    ->doesntContain('Kabwe Hardware'))
        );
});

it('rejects an inline client when no company is active', function () {
    $user = App\Models\User::factory()->withSubscription()->create();

    $this->actingAs($user)
        ->from('/invoices/create')
        ->post('/clients', ['name' => 'Kabwe Hardware'])
        ->assertSessionHasErrors('company_id');

    expect(Client::where('name', 'Kabwe Hardware')->exists())->toBeFalse();
});

/**
 * The picker must stay openable with an empty list, or the row that adds the
 * first client is unreachable and the user is stuck exactly where they started.
 */
it('leaves the pickers enabled so the new-contact row stays reachable', function () {
    $pages = [
        'Invoices/Create.tsx' => 'client',
        'Quotations/Create.tsx' => 'client',
        'PurchaseOrders/Create.tsx' => 'supplier',
    ];

    foreach ($pages as $page => $kind) {
        $source = file_get_contents(__DIR__."/../../resources/js/pages/{$page}");

        expect($source)
            ->toContain("kind=\"{$kind}\"")
            ->toContain('<NewContactOption')
            ->not->toContain('disabled={!has'.ucfirst($kind).'s}');
    }
});

/**
 * Radix traps focus inside the listbox and only arrow-navigates `SelectItem`s,
 * so a plain button in the dropdown would be mouse-only. The row is a real item
 * reporting a sentinel value, which every picker peels off before storing it —
 * miss that and picking "New …" writes a junk id onto the document.
 */
it('makes the new-contact row a keyboard-reachable select item', function () {
    $dialog = file_get_contents(__DIR__.'/../../resources/js/components/contact-dialog.tsx');

    expect($dialog)
        ->toContain('<SelectItem value={NEW_CONTACT_VALUE}>')
        ->not->toContain('onMouseDown');

    foreach (['Invoices', 'Quotations', 'PurchaseOrders'] as $page) {
        $source = file_get_contents(__DIR__."/../../resources/js/pages/{$page}/Create.tsx");

        expect($source)->toContain('v ===')
            ->toContain('NEW_CONTACT_VALUE');
    }
});

/**
 * Receipts uses a native select, which cannot carry an extra row, and replaces
 * the whole form when there are no clients — so the button is its only
 * affordance, and the empty state needs one of its own.
 */
it('offers inline client creation on the receipt builder and its empty state', function () {
    $source = file_get_contents(__DIR__.'/../../resources/js/pages/Receipts/Create.tsx');

    expect($source)
        ->toContain('<ContactDialog')
        ->toContain('<NewContactButton')
        ->toContain('onAddClient={newClient.openDialog}');
});

/**
 * The dropdown row alone is not enough: it is only visible once the picker is
 * open. Every builder also carries a button on the details header, flexed
 * opposite the section title rather than crowding the field label.
 */
it('offers the shortcut on the details header of every document', function () {
    $headers = [
        'Invoices/Create.tsx' => 'Invoice details',
        'Quotations/Create.tsx' => 'Quotation details',
        'PurchaseOrders/Create.tsx' => 'Purchase order details',
        'Receipts/Create.tsx' => 'Receipt details',
    ];

    foreach ($headers as $page => $title) {
        $source = file_get_contents(__DIR__."/../../resources/js/pages/{$page}");

        /** The button has to hang off the header, not some other element. */
        expect($source)->toMatch(
            '/title="'.preg_quote($title, '/').'"\s*\n\s*action=\{\s*\n\s*<NewContactButton/'
        );
    }
});

/**
 * Create mode takes a phone number; edit mode must not, because the documents'
 * `clients` prop carries no phone and would post a blank over a real one.
 */
it('keeps the phone field out of the edit form', function () {
    $dialog = file_get_contents(__DIR__.'/../../resources/js/components/contact-dialog.tsx');

    expect($dialog)
        ->toContain('{isCreate && (')
        ->toContain('form.transform((data) =>');
});
