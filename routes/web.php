
<?php

use App\Http\Controllers\ClientController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoiceTemplateController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');

    // Company management
    Route::get('companies', [\App\Http\Controllers\CompanyController::class, 'index'])->name('companies.index');
    Route::post('companies/switch', [\App\Http\Controllers\CompanyController::class, 'switch'])->name('companies.switch');
    Route::post('/companies', [CompanyController::class, 'store']);
    Route::match(['PUT', 'POST'], '/companies/{company}', [CompanyController::class, 'update'])
        ->whereNumber('company')
        ->name('companies.update');
    // Invoice management
    Route::prefix('invoices')->name('invoices.')->group(function () {
        Route::get('/', [InvoiceController::class, 'index'])->name('index');
        Route::get('/create', [InvoiceController::class, 'create'])->name('create');
        Route::post('/', [InvoiceController::class, 'store'])->name('store');

        // ✅ Preview BEFORE saving (HTML) — avoids the "preview" bigint bug
        // Your UI can: window.open(route('invoices.preview.new', qs), '_blank')
        Route::match(['GET', 'POST'], '/preview', [InvoiceController::class, 'previewNew'])
            ->name('preview.new');

        // ✅ Saved invoice routes
        Route::get('/{invoice}', [InvoiceController::class, 'show'])
            ->whereNumber('invoice')
            ->name('show');

        Route::get('/{invoice}/edit', [InvoiceController::class, 'edit'])
            ->whereNumber('invoice')
            ->name('edit');

        Route::put('/{invoice}', [InvoiceController::class, 'update'])
            ->whereNumber('invoice')
            ->name('update');

        Route::delete('/{invoice}', [InvoiceController::class, 'destroy'])
            ->whereNumber('invoice')
            ->name('destroy');

        // ✅ Actions on saved invoice
        Route::post('/{invoice}/status', [InvoiceController::class, 'updateStatus'])
            ->whereNumber('invoice')
            ->name('status');

        Route::get('/{invoice}/preview', [InvoiceController::class, 'preview'])
            ->whereNumber('invoice')
            ->name('preview');

        Route::get('/{invoice}/print', [InvoiceController::class, 'print'])
            ->whereNumber('invoice')
            ->name('print');
    });

    // Quotation management
    Route::prefix('quotations')->name('quotations.')->group(function () {
        Route::get('/', [\App\Http\Controllers\QuotationController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\QuotationController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\QuotationController::class, 'store'])->name('store');
    });

    // Client management
    Route::get('clients', [\App\Http\Controllers\ClientController::class, 'index'])->name('clients.index');
    Route::get('clients/create', [\App\Http\Controllers\ClientController::class, 'create'])->name('clients.create');
    Route::post('/clients', [ClientController::class, 'store']);
    Route::put('/clients/{client}', [ClientController::class, 'update']);
    Route::delete('/clients/{client}', [ClientController::class, 'destroy']);

    // currencies
    Route::get('/settings/currencies', [CurrencyController::class, 'index']);
    Route::post('/currencies/switch', [CurrencyController::class, 'switch']);
    Route::post('/currencies', [CurrencyController::class, 'store']);
    Route::put('/currencies/{currency}', [CurrencyController::class, 'update']);
    Route::delete('/currencies/{currency}', [CurrencyController::class, 'destroy']);

    // templates management
    Route::get('/settings/quotation-templates', [InvoiceTemplateController::class, 'quotationIndex']);
    Route::get('/settings/quotation-templates/create', [InvoiceTemplateController::class, 'quotationCreate']);
    Route::get('/settings/quotation-templates/{template}/edit', [InvoiceTemplateController::class, 'quotationEdit']);

    Route::post('/settings/quotation-templates', [InvoiceTemplateController::class, 'quotationStore']);
    Route::put('/settings/quotation-templates/{template}', [InvoiceTemplateController::class, 'quotationUpdate']);
    Route::post('/settings/quotation-templates/{template}/default', [InvoiceTemplateController::class, 'quotationMakeDefault']);

    Route::get('/settings/invoice-templates', [InvoiceTemplateController::class, 'index']);
    Route::get('/settings/invoice-templates/create', [InvoiceTemplateController::class, 'create']);
    Route::get('/settings/invoice-templates/{template}/edit', [InvoiceTemplateController::class, 'edit']);

    Route::post('/settings/invoice-templates', [InvoiceTemplateController::class, 'store']);
    Route::put('/settings/invoice-templates/{template}', [InvoiceTemplateController::class, 'update']);
    Route::post('/settings/invoice-templates/{template}/default', [InvoiceTemplateController::class, 'makeDefault']);
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
