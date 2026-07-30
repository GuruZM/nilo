<?php

use App\Enums\DocumentType;
use App\Models\Company;
use App\Models\Quotation;
use App\Support\DocumentNumber;

it('starts each company at one', function () {
    $company = Company::factory()->create();

    expect(DocumentNumber::nextFor(Quotation::class, $company->id, DocumentType::Quotation))
        ->toBe('QUO-000001');
});

it('numbers sequentially within a company', function () {
    $company = Company::factory()->create();

    Quotation::query()->create([
        'company_id' => $company->id,
        'client_id' => App\Models\Client::factory()->create(['company_id' => $company->id])->id,
        'number' => 'QUO-000001',
        'issue_date' => '2026-07-01',
        'currency_code' => 'ZMW',
        'status' => 'draft',
    ]);

    expect(DocumentNumber::nextFor(Quotation::class, $company->id, DocumentType::Quotation))
        ->toBe('QUO-000002');
});

it('keeps sequences independent per company', function () {
    $first = Company::factory()->create();
    $second = Company::factory()->create();

    Quotation::query()->create([
        'company_id' => $first->id,
        'client_id' => App\Models\Client::factory()->create(['company_id' => $first->id])->id,
        'number' => 'QUO-000001',
        'issue_date' => '2026-07-01',
        'currency_code' => 'ZMW',
        'status' => 'draft',
    ]);

    expect(DocumentNumber::nextFor(Quotation::class, $second->id, DocumentType::Quotation))
        ->toBe('QUO-000001');
});

it('uses the prefix of the type it is given', function () {
    $company = Company::factory()->create();

    expect(DocumentNumber::nextFor(Quotation::class, $company->id, DocumentType::PurchaseOrder))
        ->toBe('PO-000001');
});
