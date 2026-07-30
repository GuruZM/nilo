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

it('reads the sequence from a column other than number', function () {
    [, $invoice] = payableInvoiceContext();

    App\Models\InvoicePayment::factory()
        ->forInvoice($invoice, 100)
        ->create(['receipt_number' => 'RCP-000001']);

    expect(DocumentNumber::nextFor(
        App\Models\InvoicePayment::class,
        (int) $invoice->company_id,
        DocumentType::Receipt,
        'receipt_number',
    ))->toBe('RCP-000002');
});

/**
 * The bug that moved this off a row count. Counting looks equivalent right up
 * until rows are deleted, and receipts are the first document type here that
 * can be deleted.
 */
it('never reissues a number that still exists after deletions', function () {
    [, $invoice] = payableInvoiceContext();

    foreach (['RCP-000001', 'RCP-000002', 'RCP-000003'] as $number) {
        App\Models\InvoicePayment::factory()
            ->forInvoice($invoice, 100)
            ->create(['receipt_number' => $number]);
    }

    /** Delete the first two; RCP-000003 survives. A row count would now say 1. */
    App\Models\InvoicePayment::query()
        ->whereIn('receipt_number', ['RCP-000001', 'RCP-000002'])
        ->delete();

    expect(DocumentNumber::nextFor(
        App\Models\InvoicePayment::class,
        (int) $invoice->company_id,
        DocumentType::Receipt,
        'receipt_number',
    ))->toBe('RCP-000004');
});

/**
 * The scan is scoped by prefix, so rows numbered under a different one cannot
 * inflate the sequence. Counting could not make that distinction — it saw every
 * row in the table regardless of what it was called.
 */
it('ignores numbers issued under a different prefix', function () {
    $company = Company::factory()->create();

    Quotation::query()->create([
        'company_id' => $company->id,
        'client_id' => App\Models\Client::factory()->create(['company_id' => $company->id])->id,
        'number' => 'QUO-000007',
        'issue_date' => '2026-07-01',
        'currency_code' => 'ZMW',
        'status' => 'draft',
    ]);

    expect(DocumentNumber::nextFor(Quotation::class, $company->id, DocumentType::PurchaseOrder))
        ->toBe('PO-000001');
});
