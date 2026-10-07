<?php

/**
 * Customers paying by bank transfer copy these straight off the payment page,
 * so a stale digit means money landing in the wrong account.
 */
it('shows the Resonant Technologies FNB account on the bank transfer route', function (string $label, string $value) {
    expect(file_get_contents(__DIR__.'/../../resources/js/pages/subscription/payment.tsx'))
        ->toContain("{ label: '{$label}', value: '{$value}' }");
})->with([
    'bank' => ['Bank', 'First National Bank (FNB)'],
    'account name' => ['Account name', 'Resonant Technologies'],
    'account number' => ['Account number', '63108067744'],
    'branch name' => ['Branch name', 'Acacia Park - Commercial Suite'],
    'branch code' => ['Branch code', '260026'],
    'swift code' => ['SWIFT code', 'FIRNZMLX'],
]);

it('shows only the provider and number on the Airtel Money route', function () {
    preg_match('/const MOBILE_MONEY_DETAILS = \[(.*?)\];/s', file_get_contents(
        __DIR__.'/../../resources/js/pages/subscription/payment.tsx'
    ), $matches);

    expect($matches[1] ?? '')
        ->toContain("{ label: 'Provider', value: 'Airtel Money' }")
        ->toContain("{ label: 'Number', value: '+260770785275' }")
        ->not->toContain('Account name');
});
