<?php

use App\Models\Client;
use App\Models\Currency;
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
 * The inline editor sends only name, contact person and email. Anything else on
 * the client must survive untouched, or quick-editing from the invoice page
 * would quietly destroy data the user cannot see from there.
 */
it('leaves untouched client fields alone when only the email is edited', function () {
    [$user, $client] = invoiceCreationContext(null);

    $client->update([
        'phone' => '+260 900 000 000',
        'tpin' => '1001234567',
        'address' => '12 Cairo Road',
        'city' => 'Lusaka',
        'country' => 'Zambia',
        'notes' => 'Pays on time.',
    ]);

    $this->actingAs($user)
        ->put("/clients/{$client->id}", [
            'name' => $client->name,
            'contact_person' => 'Amara Phiri',
            'email' => 'accounts@example.com',
        ])
        ->assertSessionHasNoErrors();

    $client->refresh();

    expect($client->email)->toBe('accounts@example.com')
        ->and($client->contact_person)->toBe('Amara Phiri')
        ->and($client->phone)->toBe('+260 900 000 000')
        ->and($client->tpin)->toBe('1001234567')
        ->and($client->address)->toBe('12 Cairo Road')
        ->and($client->city)->toBe('Lusaka')
        ->and($client->country)->toBe('Zambia')
        ->and($client->notes)->toBe('Pays on time.');
});

it('rejects an invalid email without changing the client', function () {
    [$user, $client] = invoiceCreationContext('good@example.com');

    $this->actingAs($user)
        ->put("/clients/{$client->id}", [
            'name' => $client->name,
            'email' => 'not-an-email',
        ])
        ->assertSessionHasErrors('email');

    expect($client->refresh()->email)->toBe('good@example.com');
});

it('serves the freshly added email to the invoice create page', function () {
    [$user, $client] = invoiceCreationContext(null);

    $this->actingAs($user)->put("/clients/{$client->id}", [
        'name' => $client->name,
        'email' => 'accounts@example.com',
    ]);

    /** This prop is what re-enables the send switch. */
    $this->actingAs($user)
        ->get('/invoices/create')
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Invoices/Create')
                ->where('clients.0.email', 'accounts@example.com')
        );
});

it('edits the client in place without discarding the invoice in progress', function () {
    $dialog = file_get_contents(__DIR__.'/../../resources/js/components/edit-client-dialog.tsx');
    $create = file_get_contents(__DIR__.'/../../resources/js/pages/Invoices/Create.tsx');

    expect($dialog)
        ->toContain('export default function EditClientDialog')
        ->toContain('form.put(`/clients/${client.id}`')
        // Keeps the caller mounted, so the half-built invoice survives.
        ->toContain('preserveState: true');

    expect($create)->toContain('<EditClientDialog');
});

/** The quotation builder shares the dialog, so it gets the same behaviour. */
it('offers the same in-place client edit while building a quotation', function () {
    $create = file_get_contents(__DIR__.'/../../resources/js/pages/Quotations/Create.tsx');

    expect($create)
        ->toContain("import EditClientDialog from '@/components/edit-client-dialog'")
        ->toContain('<EditClientDialog');
});

/**
 * Radix portals the edit dialog out of the document form in the DOM, but React
 * still propagates events up the component tree. Without both guards below,
 * saving the client also submitted the invoice.
 */
it('saves the client without also submitting the invoice', function () {
    $dialog = file_get_contents(__DIR__.'/../../resources/js/components/edit-client-dialog.tsx');
    $create = file_get_contents(__DIR__.'/../../resources/js/pages/Invoices/Create.tsx');
    $quotation = file_get_contents(__DIR__.'/../../resources/js/pages/Quotations/Create.tsx');

    // The dialog stops its submit escaping to the document form.
    expect($dialog)->toContain('e.stopPropagation();');

    // And each document form ignores submits that are not its own.
    expect($create)->toContain('if (e.target !== e.currentTarget) {');
    expect($quotation)->toContain('if (e.target !== e.currentTarget) {');
});
