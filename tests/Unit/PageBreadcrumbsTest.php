<?php

test('app layout pages define breadcrumbs for primary app sections', function () {
    $pages = [
        __DIR__.'/../../resources/js/pages/Invoices/Index.tsx',
        __DIR__.'/../../resources/js/pages/Clients/Index.tsx',
        __DIR__.'/../../resources/js/pages/Clients/Create.tsx',
        __DIR__.'/../../resources/js/pages/Companies/Index.tsx',
        __DIR__.'/../../resources/js/pages/Quotations/Index.tsx',
        __DIR__.'/../../resources/js/pages/invoice-create.tsx',
    ];

    foreach ($pages as $page) {
        $contents = file_get_contents($page);

        expect($contents)
            ->toContain('const breadcrumbs: BreadcrumbItem[] = [')
            ->toContain('<AppLayout breadcrumbs={breadcrumbs}>');
    }
});
