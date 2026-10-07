<?php

use App\Mail\DocumentToCounterparty;
use App\Mail\InvoiceToClient;
use App\Mail\QuotationToClient;
use App\Models\Client;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Currency;
use App\Models\DeliveryNote;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\PurchaseOrder;
use App\Models\Quotation;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
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

/**
 * A saved document of the given kind, addressed to the client — or, for a
 * purchase order, to a supplier sharing the client's email address.
 */
function emailableDocument(string $kind, Client $client): Model
{
    $owned = ['company_id' => $client->company_id];
    $billed = $owned + ['client_id' => $client->id];

    return match ($kind) {
        'invoice' => Invoice::factory()->create($billed + ['status' => 'pending']),
        'quotation' => Quotation::factory()->create($billed + ['status' => 'draft']),
        'receipt' => InvoicePayment::factory()->create($owned + [
            'invoice_id' => Invoice::factory()->create($billed)->id,
        ]),
        'credit note' => CreditNote::factory()->create($billed + [
            'invoice_id' => Invoice::factory()->create($billed)->id,
        ]),
        'delivery note' => DeliveryNote::factory()->create($billed + [
            'invoice_id' => Invoice::factory()->create($billed)->id,
        ]),
        'purchase order' => PurchaseOrder::factory()->create($owned + [
            'supplier_id' => Supplier::factory()->create($owned + ['email' => $client->email])->id,
        ]),
    };
}

function sendPath(string $kind, Model $document): string
{
    $prefix = match ($kind) {
        'invoice' => 'invoices',
        'quotation' => 'quotations',
        'receipt' => 'receipts',
        'credit note' => 'credit-notes',
        'delivery note' => 'delivery-notes',
        'purchase order' => 'purchase-orders',
    };

    return "/{$prefix}/{$document->getKey()}/send";
}

dataset('every document kind', ['invoice', 'quotation', 'receipt', 'credit note', 'delivery note', 'purchase order']);

dataset('documents on the shared mailable', ['receipt', 'credit note', 'delivery note', 'purchase order']);

it('emails a saved document to its recipient', function (string $kind, string $mailable) {
    [$user, $client] = invoiceCreationContext('client@example.com');
    $document = emailableDocument($kind, $client);

    $this->actingAs($user)
        ->from('/documents')
        ->post(sendPath($kind, $document))
        ->assertRedirect('/documents')
        ->assertSessionHas('success', ucfirst($kind).' queued to client@example.com.');

    Mail::assertQueued($mailable, fn (Mailable $mail) => $mail->hasTo('client@example.com'));
})->with([
    'invoice' => ['invoice', InvoiceToClient::class],
    'quotation' => ['quotation', QuotationToClient::class],
    'receipt' => ['receipt', DocumentToCounterparty::class],
    'credit note' => ['credit note', DocumentToCounterparty::class],
    'delivery note' => ['delivery note', DocumentToCounterparty::class],
    'purchase order' => ['purchase order', DocumentToCounterparty::class],
]);

it('attaches the rendered document as a PDF', function (string $kind) {
    [, $client] = invoiceCreationContext('client@example.com');
    $document = emailableDocument($kind, $client);

    $attachment = (new DocumentToCounterparty($document))->attachments()[0];
    $pdf = $attachment->attachWith(fn () => null, fn (Closure $data) => $data());

    expect($attachment->as)->toBe($document->number.'.pdf')
        ->and($attachment->mime)->toBe('application/pdf')
        ->and($pdf)->toStartWith('%PDF-');
})->with('documents on the shared mailable');

/**
 * `Mail::fake()` never serialises a queued mailable, so the round trip the
 * queue worker makes is exercised here.
 */
it('survives the trip through the queue', function (string $kind) {
    [, $client] = invoiceCreationContext('client@example.com');
    $document = emailableDocument($kind, $client);

    $restored = unserialize(serialize(new DocumentToCounterparty($document)));

    expect($restored->document->is($document))->toBeTrue();
    $restored->assertSeeInHtml($document->number);
})->with('documents on the shared mailable');

it('words the email for the document it carries', function (string $kind, string $amountLabel) {
    [, $client] = invoiceCreationContext('client@example.com');
    $document = emailableDocument($kind, $client);
    $label = ucfirst($kind).' '.$document->number;

    $mailable = new DocumentToCounterparty($document);

    $mailable->assertHasSubject($label.' from '.Company::find($client->company_id)->name);
    $mailable->assertSeeInHtml('Please find '.$label.' attached as a PDF.');
    $mailable->assertSeeInHtml($amountLabel);
    $mailable->assertSeeInHtml('ZMW '.number_format((float) $document->total, 2));
})->with([
    'receipt' => ['receipt', 'Amount received'],
    'credit note' => ['credit note', 'Credit total'],
    'purchase order' => ['purchase order', 'Order total'],
]);

it('keeps prices out of a delivery note email', function () {
    [, $client] = invoiceCreationContext('client@example.com');
    $document = emailableDocument('delivery note', $client);

    $mailable = new DocumentToCounterparty($document);

    $mailable->assertSeeInHtml('Delivery note '.$document->number);
    $mailable->assertDontSeeInHtml('ZMW');
});

it('moves a document that has gone out on from its draft status', function (string $kind) {
    [$user, $client] = invoiceCreationContext('client@example.com');
    $document = emailableDocument($kind, $client);

    $this->actingAs($user)->post(sendPath($kind, $document));

    expect($document->fresh()->status)->toBe('sent');
})->with(['invoice', 'quotation', 'purchase order']);

/**
 * Issuing a credit note applies it to the invoice, and a delivery note moves
 * with the goods, so emailing either must not change where it stands.
 */
it('leaves credit and delivery note statuses alone', function (string $kind) {
    [$user, $client] = invoiceCreationContext('client@example.com');
    $document = emailableDocument($kind, $client);

    $this->actingAs($user)->post(sendPath($kind, $document));

    expect($document->fresh()->status)->toBe('draft');
})->with(['credit note', 'delivery note']);

it('says so when the recipient has no email address', function (string $kind, string $recipient) {
    [$user, $client] = invoiceCreationContext(null);
    $document = emailableDocument($kind, $client);

    $this->actingAs($user)
        ->post(sendPath($kind, $document))
        ->assertRedirect()
        ->assertSessionHas('error', "This {$recipient} has no email address, so there is nowhere to send the {$kind}.");

    Mail::assertNothingQueued();
})->with([
    'invoice' => ['invoice', 'client'],
    'quotation' => ['quotation', 'client'],
    'receipt' => ['receipt', 'client'],
    'credit note' => ['credit note', 'client'],
    'delivery note' => ['delivery note', 'client'],
    'purchase order' => ['purchase order', 'supplier'],
]);

it("refuses to email another company's document", function (string $kind) {
    [$user] = invoiceCreationContext('client@example.com');
    [, $otherClient] = invoiceCreationContext('other@example.com');
    $document = emailableDocument($kind, $otherClient);

    $this->actingAs($user)
        ->post(sendPath($kind, $document))
        ->assertForbidden();

    Mail::assertNothingQueued();
})->with('every document kind');

it('limits how quickly one member can send', function () {
    [$user, $client] = invoiceCreationContext('client@example.com');
    $document = emailableDocument('purchase order', $client);

    foreach (range(1, 10) as $attempt) {
        $this->actingAs($user)->post(sendPath('purchase order', $document))->assertRedirect();
    }

    $this->actingAs($user)
        ->post(sendPath('purchase order', $document))
        ->assertTooManyRequests();
});
