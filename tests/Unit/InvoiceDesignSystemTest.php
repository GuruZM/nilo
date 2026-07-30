<?php

/**
 * The invoice pages share the dashboard design system with Companies and
 * Clients. These assertions catch a drift back to bordered shadcn `Card`
 * surfaces and locally reimplemented primitives.
 */
function invoicePageSource(string $name): string
{
    return file_get_contents(__DIR__.'/../../resources/js/pages/Invoices/'.$name);
}

it('builds every invoice page on the shared dashboard primitives', function (string $name) {
    expect(invoicePageSource($name))
        ->toContain("from '@/components/dashboard/primitives'")
        ->not->toContain("from '@/components/ui/card'");
})->with(['Index.tsx', 'show.tsx', 'Create.tsx']);

it('gives the invoice list the same shell and surfaces as the clients list', function () {
    expect(invoicePageSource('Index.tsx'))
        ->toContain('mx-auto w-full py-3')
        ->toContain('<Panel>')
        ->toContain('<SearchField')
        ->toContain('<StatTile')
        ->toContain('<Chip')
        ->toContain('<SortableTh')
        ->toContain('border-separate border-spacing-y-1.5');
});

it('keeps the invoice builder free of locally reimplemented primitives', function () {
    expect(invoicePageSource('Create.tsx'))
        ->toContain('<PillButton')
        ->toContain('fieldInputClass')
        ->toContain('<FormField')
        ->not->toContain('<Button')
        ->not->toContain("from '@/components/ui/button'");
});

it('renders every invoice status through the shared status pill', function () {
    $primitives = file_get_contents(
        __DIR__.'/../../resources/js/components/dashboard/primitives.tsx'
    );

    expect($primitives)
        ->toContain('export function TotalRow')
        ->toContain('STATUS_TONES');

    foreach (['paid', 'sent', 'overdue', 'void', 'draft'] as $status) {
        expect($primitives)->toContain($status.': {');
    }

    expect(invoicePageSource('show.tsx'))
        ->toContain('<StatusPill status={invoice.status} />');

    expect(invoicePageSource('Index.tsx'))
        ->toContain('<StatusPill status={invoice.status} />');
});
