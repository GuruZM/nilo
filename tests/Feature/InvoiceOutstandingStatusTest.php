<?php

use App\Models\Invoice;

it('counts a partially paid invoice as outstanding', function () {
    expect(Invoice::OUTSTANDING_STATUSES)->toContain('partially_paid');
});

it('treats every unsettled status as money still owed', function () {
    $invoice = new Invoice(['status' => 'partially_paid']);

    expect($invoice->isOutstanding())->toBeTrue();
});

it('does not treat a paid or void invoice as outstanding', function () {
    expect((new Invoice(['status' => 'paid']))->isOutstanding())->toBeFalse()
        ->and((new Invoice(['status' => 'void']))->isOutstanding())->toBeFalse();
});
