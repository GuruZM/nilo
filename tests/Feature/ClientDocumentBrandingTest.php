<?php

use App\Mail\InvoiceToClient;
use App\Mail\QuotationToClient;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\InvoiceTemplate;
use App\Models\Quotation;
use App\Services\CompanyDocumentBranding;
use App\Services\InvoiceDocumentRenderer;
use App\Services\QuotationDocumentRenderer;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Mime\Email;

/**
 * Documents that leave the platform belong to the company that raised them.
 * Nothing in them may reveal that Nilo or Resonantt exists — that branding is
 * reserved for mail Nilo sends to its own users.
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

/**
 * A saved invoice belonging to a company with full contact details.
 */
function brandedInvoice(array $companyAttributes = []): Invoice
{
    [$user, $client, $template] = invoiceCreationContext('client@example.com');

    $user->currentCompany->forceFill($companyAttributes + [
        'name' => 'Acme Traders',
        'email' => 'billing@acme.test',
        'phone' => '+260 970 000 000',
        'address' => 'Plot 12, Lusaka',
    ])->save();

    test()->actingAs($user)->post('/invoices', invoicePayload($client, $template, false));

    return Invoice::query()->latest('id')->first();
}

/**
 * The quotation equivalent of {@see brandedInvoice()}.
 */
function brandedQuotation(array $companyAttributes = []): Quotation
{
    [$user, $client, $template] = quotationCreationContext('client@example.com');

    $user->currentCompany->forceFill($companyAttributes + [
        'name' => 'Acme Traders',
        'email' => 'billing@acme.test',
        'phone' => '+260 970 000 000',
        'address' => 'Plot 12, Lusaka',
    ])->save();

    test()->actingAs($user)->post('/quotations', quotationPayload($client, $template, false));

    return Quotation::query()->latest('id')->first();
}

it('keeps platform branding out of the invoice email', function () {
    $mailable = new InvoiceToClient(brandedInvoice());

    $html = $mailable->render();

    expect($html)
        ->not->toContain('Nilo')
        ->not->toContain('Resonantt')
        ->not->toContain('logo-email.png')
        ->and($html)->toContain('Acme Traders');
});

it('keeps platform branding out of the quotation email', function () {
    $mailable = new QuotationToClient(brandedQuotation());

    $html = $mailable->render();

    expect($html)
        ->not->toContain('Nilo')
        ->not->toContain('Resonantt')
        ->not->toContain('logo-email.png')
        ->and($html)->toContain('Acme Traders');
});

it('signs the invoice email off with the company contact details', function () {
    $html = (new InvoiceToClient(brandedInvoice()))->render();

    expect($html)
        ->toContain('billing@acme.test')
        ->toContain('+260 970 000 000')
        ->toContain('Plot 12, Lusaka');
});

it('omits contact details the company has not filled in', function () {
    $invoice = brandedInvoice(['phone' => null, 'address' => null]);

    $html = (new InvoiceToClient($invoice))->render();

    expect($html)
        ->toContain('billing@acme.test')
        ->not->toContain('Plot 12, Lusaka');
});

it('sends the invoice as the company over the platform address', function () {
    $mailable = new InvoiceToClient(brandedInvoice());

    $mailable->assertFrom(config('mail.from.address'), 'Acme Traders');
    $mailable->assertHasReplyTo('billing@acme.test', 'Acme Traders');
});

it('sends the quotation as the company over the platform address', function () {
    $mailable = new QuotationToClient(brandedQuotation());

    $mailable->assertFrom(config('mail.from.address'), 'Acme Traders');
    $mailable->assertHasReplyTo('billing@acme.test', 'Acme Traders');
});

it('resolves a stored company logo to a file the mailer can embed', function () {
    Storage::fake('public');
    Storage::disk('public')->put('logos/acme.png', 'fake-png');

    $invoice = brandedInvoice(['logo_path' => 'logos/acme.png']);
    $branding = app(CompanyDocumentBranding::class)->forCompany($invoice->company);

    expect($branding['companyLogoEmbedPath'])->toBeFile()
        ->and(file_get_contents($branding['companyLogoEmbedPath']))->toBe('fake-png')
        ->and($branding['companyLogoUrl'])->toContain('/storage/logos/acme.png');
});

/**
 * `Mailable::render()` inlines embedded images as base64 for previewing, so the
 * masthead is rendered against a real message here to prove that an actual
 * delivery attaches the logo and points the tag at it.
 */
it('embeds the company logo inline when the message can carry it', function () {
    Storage::fake('public');
    Storage::disk('public')->put('logos/acme.png', 'fake-png');

    $invoice = brandedInvoice(['logo_path' => 'logos/acme.png']);

    $html = view('emails.partials.company-header', [
        ...app(CompanyDocumentBranding::class)->forCompany($invoice->company),
        'message' => new Message(new Email),
    ])->render();

    expect($html)->toContain('<img')->toContain('cid:');
});

it('links a stored company logo when there is no message to embed it into', function () {
    Storage::fake('public');
    Storage::disk('public')->put('logos/acme.png', 'fake-png');

    $invoice = brandedInvoice(['logo_path' => 'logos/acme.png']);

    $html = view(
        'emails.partials.company-header',
        app(CompanyDocumentBranding::class)->forCompany($invoice->company),
    )->render();

    expect($html)->toContain('/storage/logos/acme.png');
});

it('links a remotely hosted company logo', function () {
    $invoice = brandedInvoice(['logo_path' => 'https://cdn.acme.test/mark.png']);

    expect((new InvoiceToClient($invoice))->render())
        ->toContain('src="https://cdn.acme.test/mark.png"');
});

it('falls back to a company wordmark when the stored logo file is missing', function () {
    Storage::fake('public');

    $invoice = brandedInvoice(['logo_path' => 'logos/deleted.png']);

    expect((new InvoiceToClient($invoice))->render())->not->toContain('<img');
});

it('falls back to a company wordmark when no logo is set', function () {
    $html = (new InvoiceToClient(brandedInvoice()))->render();

    expect($html)
        ->not->toContain('<img')
        ->and($html)->toContain('Acme Traders');
});

it('falls back to a company wordmark for svg logos mail clients cannot render', function () {
    Storage::fake('public');
    Storage::disk('public')->put('logos/acme.svg', '<svg></svg>');

    $invoice = brandedInvoice(['logo_path' => 'logos/acme.svg']);

    expect((new InvoiceToClient($invoice))->render())
        ->not->toContain('<img');
});

it('drops the from clause instead of naming the platform when the company is gone', function () {
    $invoice = brandedInvoice();
    $invoice->setRelation('company', null);

    $mailable = new InvoiceToClient($invoice);

    $mailable->assertHasSubject('Invoice '.$invoice->number);
    expect($mailable->render())->not->toContain('Nilo');
});

/**
 * The owner name is pinned rather than left to faker, and deliberately carries
 * an apostrophe. Blade escapes it, so asserting the raw string passes or fails
 * on whether the generated name happened to contain a special character — which
 * is exactly how this test used to fail about one run in ten.
 */
it('names the owner when the company record has no name', function () {
    $invoice = brandedInvoice();
    $invoice->company->forceFill(['name' => ''])->save();
    $invoice->company->owner->forceFill(['name' => "Fiona O'Brien"])->save();
    $invoice->unsetRelation('company');

    $owner = $invoice->company->owner;

    expect((new InvoiceToClient($invoice))->render())->toContain(e($owner->name));
});

it('leaves no platform footer on the rendered invoice document', function () {
    $invoice = brandedInvoice();

    expect(app(InvoiceDocumentRenderer::class)->html($invoice, 'pdf'))
        ->not->toContain('Powered by');
});

it('leaves no platform footer on the rendered quotation document', function () {
    $quotation = brandedQuotation();

    expect(app(QuotationDocumentRenderer::class)->html($quotation, 'pdf'))
        ->not->toContain('Powered by');
});

/**
 * The separation has to hold in both directions: stripping the platform brand
 * from client documents must not strip it from mail Nilo sends its own users.
 */
it('keeps platform branding on the welcome email', function () {
    $user = App\Models\User::factory()->create();

    $mail = (new App\Notifications\WelcomeEmail)->toMail($user);

    expect((string) $mail->render())->toContain('Nilo');
});

it('still prints a footer the company wrote itself', function () {
    $invoice = brandedInvoice();

    InvoiceTemplate::query()
        ->whereKey($invoice->invoice_template_id)
        ->update(['footer_html' => 'Acme Traders · Thank you']);

    expect(app(InvoiceDocumentRenderer::class)->html($invoice->fresh(), 'pdf'))
        ->toContain('Acme Traders · Thank you');
});
