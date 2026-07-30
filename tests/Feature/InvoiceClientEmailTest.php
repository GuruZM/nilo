<?php

use App\Mail\InvoiceToClient;
use App\Models\Currency;
use App\Models\Invoice;
use Illuminate\Support\Facades\Mail;

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

it('emails the invoice to the client when asked to', function () {
    [$user, $client, $template] = invoiceCreationContext('client@example.com');

    $this->actingAs($user)
        ->post('/invoices', invoicePayload($client, $template, true))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    Mail::assertQueued(
        InvoiceToClient::class,
        fn (InvoiceToClient $mail) => $mail->hasTo('client@example.com')
    );
});

it('promotes an emailed invoice from pending to sent', function () {
    [$user, $client, $template] = invoiceCreationContext('client@example.com');

    $this->actingAs($user)
        ->post('/invoices', invoicePayload($client, $template, true));

    expect(Invoice::query()->latest('id')->first()->status)->toBe('sent');
});

it('does not email the client when the toggle is off', function () {
    [$user, $client, $template] = invoiceCreationContext('client@example.com');

    $this->actingAs($user)
        ->post('/invoices', invoicePayload($client, $template, false))
        ->assertSessionHasNoErrors();

    Mail::assertNothingQueued();
    expect(Invoice::query()->latest('id')->first()->status)->toBe('pending');
});

it('still creates the invoice when the client has no email address', function () {
    [$user, $client, $template] = invoiceCreationContext(null);

    $this->actingAs($user)
        ->post('/invoices', invoicePayload($client, $template, true))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    Mail::assertNothingQueued();

    $invoice = Invoice::query()->latest('id')->first();

    expect($invoice)->not->toBeNull()
        ->and($invoice->status)->toBe('pending');
});

it('leaves a paid invoice paid even when it is emailed', function () {
    [$user, $client, $template] = invoiceCreationContext('client@example.com');

    $payload = invoicePayload($client, $template, true);
    $payload['status'] = 'paid';

    $this->actingAs($user)->post('/invoices', $payload);

    expect(Invoice::query()->latest('id')->first()->status)->toBe('paid');
});

/**
 * `Mail::fake()` never renders the message, so the body and the attached PDF
 * are exercised directly here — otherwise a template error would only surface
 * in the queue worker.
 */
it('renders the email body and a real PDF attachment', function () {
    [$user, $client, $template] = invoiceCreationContext('client@example.com');

    $this->actingAs($user)->post('/invoices', invoicePayload($client, $template, false));

    $invoice = Invoice::query()->latest('id')->first();
    $mailable = new InvoiceToClient($invoice);

    $mailable->assertSeeInHtml($client->name);
    $mailable->assertSeeInHtml('ZMW 1,000.00');

    /** Invoices are still created without a number, so the id is the fallback. */
    $mailable->assertHasSubject('Invoice #'.$invoice->id.' from '.$invoice->company->name);

    $pdf = app(App\Services\InvoiceDocumentRenderer::class)->pdf($invoice);

    expect($pdf)->toStartWith('%PDF-');
});

it('omits the on-screen toolbar from the PDF but keeps it in the print view', function () {
    [$user, $client, $template] = invoiceCreationContext('client@example.com');

    $this->actingAs($user)->post('/invoices', invoicePayload($client, $template, false));

    $invoice = Invoice::query()->latest('id')->first();
    $renderer = app(App\Services\InvoiceDocumentRenderer::class);

    /** The class survives in the stylesheet; it is the markup that must go. */
    expect($renderer->html($invoice, 'pdf'))
        ->not->toContain('<div class="screen-toolbar">')
        ->and($renderer->html($invoice, 'print'))
        ->toContain('<div class="screen-toolbar">');
});
