<?php

test('invoice show does not load the removed template view path column', function () {
    $controller = file_get_contents(__DIR__.'/../../app/Http/Controllers/InvoiceController.php');

    expect($controller)
        ->toContain("'client:id,company_id,name,email,contact_person'")
        ->toContain("'items'")
        ->not->toContain('view_path');
});

test('invoice routes fall back cleanly when no active company is selected', function () {
    $controller = file_get_contents(__DIR__.'/../../app/Http/Controllers/InvoiceController.php');

    expect($controller)
        ->toContain('private function resolveCompanyId(Request $request): ?int')
        ->toContain("'hasActiveCompany' => false")
        ->toContain("'current_company_id' => \$fallbackCompanyId");
});

test('invoice create page shows setup guidance when required data is missing', function () {
    $createPage = file_get_contents(__DIR__.'/../../resources/js/pages/Invoices/Create.tsx');

    expect($createPage)
        ->toContain('const canCreateInvoice = hasActiveCompany && hasClients && hasCurrencies;')
        ->toContain('Finish setup before creating invoices')
        ->toContain('Manage companies')
        ->toContain('/settings/currencies');
});
