<?php

test('invoice show page includes a download pdf action next to preview and print', function () {
    $showPage = file_get_contents(__DIR__.'/../../resources/js/pages/Invoices/show.tsx');

    expect($showPage)
        ->toContain('const downloadPdf = () => {')
        ->toContain('`/invoices/${invoice.id}/print?download=1`')
        ->toContain('Download PDF');
});

test('invoice print controller forwards the download flag to the template', function () {
    $controller = file_get_contents(__DIR__.'/../../app/Http/Controllers/InvoiceController.php');

    expect($controller)
        ->toContain("'autoPrint' => \$request->boolean('download')");
});

test('invoice template exposes print and download controls for preview and print modes', function () {
    $template = file_get_contents(__DIR__.'/../../resources/views/invoices/templates/default.blade.php');

    expect($template)
        ->toContain('Download PDF')
        ->toContain('Use Print to send to a printer or choose "Save as PDF" to download it.')
        ->toContain('window.openPrintDialog = () => {')
        ->toContain('window.downloadPdf = () => {')
        ->toContain('@media print');
});
