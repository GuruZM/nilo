<?php

test('template controller exposes quotation template actions with the same flow as invoice templates', function () {
    $controller = file_get_contents(__DIR__.'/../../app/Http/Controllers/InvoiceTemplateController.php');

    expect($controller)
        ->toContain('public function quotationIndex(Request $request)')
        ->toContain("return \$this->showIndex(\$request, 'quotation');")
        ->toContain('public function quotationCreate(Request $request)')
        ->toContain('public function quotationEdit(Request $request, InvoiceTemplate $template)')
        ->toContain('public function quotationStore(Request $request)')
        ->toContain('public function quotationUpdate(Request $request, InvoiceTemplate $template)')
        ->toContain('public function quotationMakeDefault(Request $request, InvoiceTemplate $template)')
        ->toContain("'type' => 'quotation'")
        ->toContain("'basePath' => \$basePath");
});

test('template pages are shared by invoice and quotation template modules', function () {
    $indexPage = file_get_contents(__DIR__.'/../../resources/js/pages/settings/invoicetemplates/index.tsx');
    $builderPage = file_get_contents(__DIR__.'/../../resources/js/pages/settings/invoicetemplates/builder.tsx');

    expect($indexPage)
        ->toContain('module: TemplateModule;')
        ->toContain('module.pluralTitle')
        ->toContain('module.basePath')
        ->toContain('module.createPath');

    expect($builderPage)
        ->toContain('module: TemplateModule;')
        ->toContain('router.post(module.basePath, payload, {')
        ->toContain('router.put(`${module.basePath}/${template?.id}`, payload, {')
        ->toContain("module.type === 'quotation' ? 'QUOTATION' : 'INVOICE'")
        ->toContain('documentType={module.type}');
});

test('invoice template model persists the template type', function () {
    $model = file_get_contents(__DIR__.'/../../app/Models/InvoiceTemplate.php');

    expect($model)
        ->toContain("'type'");
});
