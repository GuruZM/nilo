<?php

/**
 * Advancing a wizard step with a mandatory field empty points the hand at that
 * field rather than only raising a toast.
 */
function invoiceCreatePage(): string
{
    return file_get_contents(__DIR__.'/../../resources/js/pages/Invoices/Create.tsx');
}

function quotationCreatePage(): string
{
    return file_get_contents(__DIR__.'/../../resources/js/pages/Quotations/Create.tsx');
}

it('ships the illustration the hand renders', function () {
    expect(file_exists(__DIR__.'/../../public/pointer-hand.svg'))->toBeTrue();
});

it('renders the pointing hand from the shared asset', function () {
    $hand = file_get_contents(__DIR__.'/../../resources/js/components/required-hand.tsx');

    expect($hand)
        ->toContain('export default function RequiredHand')
        ->toContain('src="/pointer-hand.svg"')
        ->toContain('role="alert"');
});

/** Both document wizards point the same hand at the field that blocked them. */
it('uses the shared hand in both document wizards', function () {
    foreach ([invoiceCreatePage(), quotationCreatePage()] as $page) {
        expect($page)
            ->toContain("import RequiredHand from '@/components/required-hand'")
            ->toContain('<RequiredHand');
    }
});

it('names the field behind every blocked step instead of only toasting', function () {
    $page = invoiceCreatePage();

    expect($page)
        ->toContain('const stepIssue = (s: StepKey): StepIssue | null')
        ->toContain('setBlockedField(issue?.field ?? null)');

    foreach ([
        'client_id',
        'issue_date',
        'currency_code',
        'item_description',
        'invoice_discount',
        'recurrence_frequency',
        'recurrence_interval',
        'send_to_client',
    ] as $field) {
        expect($page)->toContain("field: '{$field}'");
    }
});

it('gives every blockable field a focus target', function () {
    $page = invoiceCreatePage();

    // Scrolls and focuses whatever the issue named.
    expect($page)->toContain('document.getElementById(`field-${issue.field}`)');

    foreach ([
        'client_id',
        'issue_date',
        'currency_code',
        'invoice_discount',
        'recurrence_frequency',
        'recurrence_interval',
        'send_to_client',
    ] as $field) {
        expect($page)->toContain("id=\"field-{$field}\"");
    }

    /** The first line item carries the id; later rows do not. */
    expect($page)->toContain("'field-item_description'");
});

it('names and focuses every blockable field on the quotation wizard', function () {
    $page = quotationCreatePage();

    expect($page)
        ->toContain('const stepIssue = (s: StepKey): StepIssue | null')
        ->toContain('setBlockedField(issue?.field ?? null)')
        ->toContain('document.getElementById(`field-${issue.field}`)');

    foreach ([
        'client_id',
        'quotation_template_id',
        'issue_date',
        'valid_until',
        'currency_code',
        'quotation_discount',
        'send_to_client',
    ] as $field) {
        expect($page)
            ->toContain("field: '{$field}'")
            ->toContain("id=\"field-{$field}\"");
    }

    /** The first line item carries the id; later rows do not. */
    expect($page)
        ->toContain("field: 'item_description'")
        ->toContain("'field-item_description'");
});

it('clears the pointer when the step changes', function () {
    expect(invoiceCreatePage())
        ->toContain('React.useEffect(() => setBlockedField(null), [step])');
});

/** Otherwise the hand lingered until the user clicked Next a second time. */
it('clears the pointer as soon as the named field is fixed', function () {
    expect(invoiceCreatePage())
        ->toContain('if (stepIssue(step)?.field !== blockedField) {')
        ->toContain('}, [form.data, clientEmail, blockedField, step]);');
});
