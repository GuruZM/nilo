<?php

use App\Enums\DocumentType;
use App\Models\Company;
use App\Models\InvoiceTemplate;
use App\Services\TemplateProvisioner;

it('creates a default template the first time a type is used', function () {
    $company = Company::factory()->create();

    $template = app(TemplateProvisioner::class)->forCompany($company->id, DocumentType::Receipt);

    expect($template->exists)->toBeTrue()
        ->and($template->company_id)->toBe($company->id)
        ->and($template->type)->toBe('receipt')
        ->and($template->is_default)->toBeTrue()
        ->and($template->name)->toBe('Default receipt')
        /** The renderer merges this over the house defaults, so it must be an array. */
        ->and($template->settings)->toBe([]);
});

it('provisions rather than failing when the chosen template does not exist', function (?int $templateId) {
    $company = Company::factory()->create();

    $resolved = app(TemplateProvisioner::class)
        ->forCompany($company->id, DocumentType::Receipt, $templateId);

    expect($resolved->exists)->toBeTrue()
        ->and($resolved->company_id)->toBe($company->id)
        ->and($resolved->type)->toBe('receipt');
})->with([
    'zero' => 0,
    'null' => null,
    'never existed' => 987654,
]);

it('reuses the provisioned template on the next call', function () {
    $company = Company::factory()->create();
    $provisioner = app(TemplateProvisioner::class);

    $first = $provisioner->forCompany($company->id, DocumentType::Receipt);
    $second = $provisioner->forCompany($company->id, DocumentType::Receipt);

    expect($second->id)->toBe($first->id)
        ->and(InvoiceTemplate::query()->where('company_id', $company->id)->count())->toBe(1);
});

it('prefers a template the company already marked default', function () {
    $company = Company::factory()->create();

    InvoiceTemplate::create([
        'company_id' => $company->id,
        'name' => 'Plain',
        'type' => 'credit_note',
        'is_default' => false,
        'settings' => [],
    ]);

    $chosen = InvoiceTemplate::create([
        'company_id' => $company->id,
        'name' => 'Branded',
        'type' => 'credit_note',
        'is_default' => true,
        'settings' => [],
    ]);

    expect(app(TemplateProvisioner::class)->forCompany($company->id, DocumentType::CreditNote)->id)
        ->toBe($chosen->id);
});

it('honours an explicitly chosen template of the right type', function () {
    $company = Company::factory()->create();

    InvoiceTemplate::create([
        'company_id' => $company->id,
        'name' => 'Default',
        'type' => 'purchase_order',
        'is_default' => true,
        'settings' => [],
    ]);

    $picked = InvoiceTemplate::create([
        'company_id' => $company->id,
        'name' => 'Alternate',
        'type' => 'purchase_order',
        'is_default' => false,
        'settings' => [],
    ]);

    expect(app(TemplateProvisioner::class)
        ->forCompany($company->id, DocumentType::PurchaseOrder, $picked->id)->id)
        ->toBe($picked->id);
});

it('ignores a chosen template belonging to another company', function () {
    $company = Company::factory()->create();
    $other = Company::factory()->create();

    $foreign = InvoiceTemplate::create([
        'company_id' => $other->id,
        'name' => 'Theirs',
        'type' => 'purchase_order',
        'is_default' => true,
        'settings' => [],
    ]);

    $resolved = app(TemplateProvisioner::class)
        ->forCompany($company->id, DocumentType::PurchaseOrder, $foreign->id);

    expect($resolved->company_id)->toBe($company->id)
        ->and($resolved->id)->not->toBe($foreign->id);
});

it('ignores a chosen template of a different document type', function () {
    $company = Company::factory()->create();

    $invoiceTemplate = InvoiceTemplate::create([
        'company_id' => $company->id,
        'name' => 'Invoice one',
        'type' => 'invoice',
        'is_default' => true,
        'settings' => [],
    ]);

    $resolved = app(TemplateProvisioner::class)
        ->forCompany($company->id, DocumentType::DeliveryNote, $invoiceTemplate->id);

    expect($resolved->type)->toBe('delivery_note')
        ->and($resolved->id)->not->toBe($invoiceTemplate->id);
});
