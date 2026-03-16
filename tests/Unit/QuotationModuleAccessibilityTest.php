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

test('quotation index page mirrors the invoices dashboard structure', function () {
    $indexPage = file_get_contents(__DIR__.'/../../resources/js/pages/Quotations/Index.tsx');

    expect($indexPage)
        ->toContain('Track drafts, sent quotations, and accepted')
        ->toContain('Filter by status')
        ->toContain('No quotations found')
        ->toContain('/quotations/create');
});

test('quotation create page uses a guided multi-step builder and posts to quotations store', function () {
    $createPage = file_get_contents(__DIR__.'/../../resources/js/pages/Quotations/Create.tsx');

    expect($createPage)
        ->toContain('const canCreateQuotation = hasActiveCompany && hasClients && hasCurrencies;')
        ->toContain('Build a client-ready quotation with the same guided')
        ->toContain("form.post('/quotations'")
        ->toContain('Create quotation');
});
