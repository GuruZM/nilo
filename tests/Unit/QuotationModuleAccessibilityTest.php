<?php

test('quotation controller provides an invoice-like flow with safe missing-table handling', function () {
    $controller = file_get_contents(__DIR__.'/../../app/Http/Controllers/QuotationController.php');

    expect($controller)
        ->toContain('private function resolveCompanyId(Request $request): ?int')
        ->toContain('public function store(Request $request): RedirectResponse')
        ->toContain("if ((string) \$exception->getCode() !== '42P01') {")
        ->toContain('Quotation storage is not set up yet. Run the quotations migrations first.');
});

test('quotation migrations exist for quotations and quotation items', function () {
    expect(file_exists(__DIR__.'/../../database/migrations/2026_03_02_120000_create_quotations_table.php'))
        ->toBeTrue()
        ->and(file_exists(__DIR__.'/../../database/migrations/2026_03_02_120100_create_quotation_items_table.php'))
        ->toBeTrue();
});

/**
 * The quotation list is the invoice list with a different noun: the same
 * borderless panels, chip filters, sortable table and prerequisite gate rather
 * than the bordered cards and dropdown filters it started out with.
 */
test('quotation index page mirrors the invoices dashboard structure', function () {
    $indexPage = file_get_contents(__DIR__.'/../../resources/js/pages/Quotations/Index.tsx');

    expect($indexPage)
        // Shared surfaces, not shadcn Cards.
        ->toContain("from '@/components/dashboard/primitives'")
        ->toContain('<Panel>')
        ->toContain('<StatTile')
        // Chip filters and a sortable table, matching Invoices/Index.
        ->toContain('const STATUS_FILTERS = [')
        ->toContain('<SortableTh')
        ->toContain('<StatusPill status={quotation.status} />')
        // The same gate the invoice list uses when setup is unfinished.
        ->toContain('<PrerequisiteGate')
        ->toContain('/quotations/create')
        ->toContain('No quotations yet')
        // The page title lives in the breadcrumb, not in the page body.
        ->not->toContain('<h1')
        ->not->toContain('DropdownMenu');
});

test('quotation create page uses a guided multi-step builder and posts to quotations store', function () {
    $createPage = file_get_contents(__DIR__.'/../../resources/js/pages/Quotations/Create.tsx');

    expect($createPage)
        ->toContain('const canCreateQuotation =')
        ->toContain("type StepKey = 'details' | 'items' | 'review';")
        ->toContain("form.post('/quotations'")
        ->toContain('Create quotation')
        // The page title lives in the breadcrumb, not in the page body.
        ->not->toContain('<h1');
});

/**
 * Everything the invoice wizard grew that the quotation builder was missing:
 * a template to render through, the pointing hand on a blocked field, the
 * server-rendered A4 preview, and emailing the finished document.
 */
test('quotation create page carries the full invoice creation workflow', function () {
    $createPage = file_get_contents(__DIR__.'/../../resources/js/pages/Quotations/Create.tsx');

    expect($createPage)
        ->toContain('quotation_template_id')
        ->toContain("import RequiredHand from '@/components/required-hand'")
        ->toContain("await fetch('/quotations/preview'")
        ->toContain('send_to_client')
        // Tax-inclusive pricing, mirroring QuotationController::computeTotals.
        ->toContain('const DEFAULT_TAX_PERCENT = 16;')
        ->toContain('Total (incl. tax)');
});
