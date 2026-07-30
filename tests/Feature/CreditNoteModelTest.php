<?php

use App\Enums\DocumentType;
use App\Models\Client;
use App\Models\CreditNote;
use App\Models\Invoice;

it('belongs to the invoice it credits', function () {
    $note = CreditNote::factory()->create();

    expect($note->invoice)->toBeInstanceOf(Invoice::class)
        ->and($note->client)->toBeInstanceOf(Client::class);
});

it('renders as a credit note', function () {
    expect(CreditNote::factory()->create()->documentType())->toBe(DocumentType::CreditNote);
});

it('addresses itself to the client', function () {
    $note = CreditNote::factory()->create();

    expect($note->counterparty()->id)->toBe($note->client_id);
});

it('only counts toward the invoice once it is issued', function () {
    expect(CreditNote::APPLIED_STATUSES)->toBe(['issued']);
});
