
<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoiceTemplateController;


Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');

    // Company management
    Route::get('companies', [\App\Http\Controllers\CompanyController::class, 'index'])->name('companies.index');
    Route::post('companies/switch', [\App\Http\Controllers\CompanyController::class, 'switch'])->name('companies.switch');
    Route::post('/companies', [CompanyController::class, 'store']);
    // Invoice management
    Route::get('invoices', [\App\Http\Controllers\InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('invoices/create', [\App\Http\Controllers\InvoiceController::class, 'create'])->name('invoices.create');
    Route::post('/invoices', [InvoiceController::class, 'store']);
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show']);
    Route::get('/invoices/{invoice}/edit', [InvoiceController::class, 'edit']);
    Route::put('/invoices/{invoice}', [InvoiceController::class, 'update']);
    Route::delete('/invoices/{invoice}', [InvoiceController::class, 'destroy']);
    // Quotation management
    Route::get('quotations', [\App\Http\Controllers\QuotationController::class, 'index'])->name('quotations.index');
    Route::get('quotations/create', [\App\Http\Controllers\QuotationController::class, 'create'])->name('quotations.create');

    // Client management
    Route::get('clients', [\App\Http\Controllers\ClientController::class, 'index'])->name('clients.index');
    Route::get('clients/create', [\App\Http\Controllers\ClientController::class, 'create'])->name('clients.create');
    Route::post('/clients', [ClientController::class, 'store']);
    Route::put('/clients/{client}', [ClientController::class, 'update']);
    Route::delete('/clients/{client}', [ClientController::class, 'destroy']);

    //currencies
     Route::get('/settings/currencies', [CurrencyController::class, 'index']);
        Route::post('/currencies/switch', [CurrencyController::class, 'switch']);
    Route::post('/currencies', [CurrencyController::class, 'store']);
    Route::put('/currencies/{currency}', [CurrencyController::class, 'update']);
    Route::delete('/currencies/{currency}', [CurrencyController::class, 'destroy']);

    //templates management
          Route::get('/settings/invoice-templates', [InvoiceTemplateController::class, 'index']);
    Route::get('/settings/invoice-templates/create', [InvoiceTemplateController::class, 'create']);
    Route::get('/settings/invoice-templates/{template}/edit', [InvoiceTemplateController::class, 'edit']);

    Route::post('/settings/invoice-templates', [InvoiceTemplateController::class, 'store']);
    Route::put('/settings/invoice-templates/{template}', [InvoiceTemplateController::class, 'update']);
    Route::post('/settings/invoice-templates/{template}/default', [InvoiceTemplateController::class, 'makeDefault']);
}); 



require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
