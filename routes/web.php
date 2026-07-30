<?php

use App\Http\Controllers\ClientController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\EnterpriseInquiryController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoiceTemplateController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\SubscriptionController;
use App\Models\Plan;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome', [
        'plans' => Plan::query()->publiclyAvailable()->get(),
    ]);
})->name('home');

Route::get('/terms', fn () => Inertia::render('terms'))->name('terms');
Route::get('/privacy', fn () => Inertia::render('privacy'))->name('privacy');

// Subscription routes (auth required, but NO subscription required)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/subscription/select', [SubscriptionController::class, 'select'])->name('subscription.select');
    Route::post('/subscription/subscribe', [SubscriptionController::class, 'subscribe'])->name('subscription.subscribe');
    Route::get('/subscription', [SubscriptionController::class, 'current'])->name('subscription.current');
    Route::get('/subscription/payment/{plan}', [PaymentController::class, 'create'])->name('subscription.payment');
    Route::post('/subscription/payment', [PaymentController::class, 'store'])->name('subscription.payment.store');
    Route::post('/subscription/redeem', [PaymentController::class, 'redeem'])->name('subscription.redeem');
    Route::get('/subscription/payment-status', [PaymentController::class, 'status'])->name('subscription.payment.status');
    Route::get('/subscription/enterprise', [EnterpriseInquiryController::class, 'create'])->name('subscription.enterprise');
    Route::post('/subscription/enterprise', [EnterpriseInquiryController::class, 'store'])->name('subscription.enterprise.store');
});

// Admin routes (auth + admin role, no subscription required)
Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/users', [\App\Http\Controllers\Admin\UserController::class, 'index'])->name('users.index');
    Route::get('/users/{user}', [\App\Http\Controllers\Admin\UserController::class, 'show'])->name('users.show');
    Route::post('/users/{user}/subscription', [\App\Http\Controllers\Admin\UserController::class, 'updateSubscription'])->name('users.subscription.update');
    Route::get('/plans', [\App\Http\Controllers\Admin\PlanController::class, 'index'])->name('plans.index');
    Route::get('/plans/create', [\App\Http\Controllers\Admin\PlanController::class, 'create'])->name('plans.create');
    Route::post('/plans', [\App\Http\Controllers\Admin\PlanController::class, 'store'])->name('plans.store');
    Route::get('/plans/{plan}/edit', [\App\Http\Controllers\Admin\PlanController::class, 'edit'])->name('plans.edit');
    Route::put('/plans/{plan}', [\App\Http\Controllers\Admin\PlanController::class, 'update'])->name('plans.update');
    Route::delete('/plans/{plan}', [\App\Http\Controllers\Admin\PlanController::class, 'destroy'])->name('plans.destroy');
    Route::get('/coupons', [\App\Http\Controllers\Admin\CouponController::class, 'index'])->name('coupons.index');
    Route::get('/coupons/create', [\App\Http\Controllers\Admin\CouponController::class, 'create'])->name('coupons.create');
    Route::post('/coupons', [\App\Http\Controllers\Admin\CouponController::class, 'store'])->name('coupons.store');
    Route::get('/coupons/{coupon}/edit', [\App\Http\Controllers\Admin\CouponController::class, 'edit'])->name('coupons.edit');
    Route::put('/coupons/{coupon}', [\App\Http\Controllers\Admin\CouponController::class, 'update'])->name('coupons.update');
    Route::delete('/coupons/{coupon}', [\App\Http\Controllers\Admin\CouponController::class, 'destroy'])->name('coupons.destroy');
    Route::get('/payments', [\App\Http\Controllers\Admin\PaymentController::class, 'index'])->name('payments.index');
    Route::get('/payments/{payment}', [\App\Http\Controllers\Admin\PaymentController::class, 'show'])->name('payments.show');
    Route::post('/payments/{payment}/confirm', [\App\Http\Controllers\Admin\PaymentController::class, 'confirm'])->name('payments.confirm');
    Route::post('/payments/{payment}/reject', [\App\Http\Controllers\Admin\PaymentController::class, 'reject'])->name('payments.reject');
    Route::get('/inquiries', [\App\Http\Controllers\Admin\InquiryController::class, 'index'])->name('inquiries.index');
    Route::post('/inquiries/{inquiry}/handle', [\App\Http\Controllers\Admin\InquiryController::class, 'handle'])->name('inquiries.handle');
});

// Main app routes (auth + verified + subscription required)
Route::middleware(['auth', 'verified', 'subscribed'])->group(function () {
    Route::get('dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');

    // Company management
    Route::get('companies', [\App\Http\Controllers\CompanyController::class, 'index'])->name('companies.index');
    Route::post('companies/switch', [\App\Http\Controllers\CompanyController::class, 'switch'])->name('companies.switch');
    Route::post('/companies', [CompanyController::class, 'store']);
    Route::match(['PUT', 'POST'], '/companies/{company}', [CompanyController::class, 'update'])
        ->whereNumber('company')
        ->name('companies.update');

    // Compliance documents
    Route::prefix('companies/{company}/documents')
        ->name('companies.documents.')
        ->whereNumber('company')
        ->scopeBindings()
        ->group(function () {
            Route::post('/', [\App\Http\Controllers\CompanyDocumentController::class, 'store'])->name('store');
            Route::get('/{document}/download', [\App\Http\Controllers\CompanyDocumentController::class, 'download'])->name('download');
            Route::delete('/{document}', [\App\Http\Controllers\CompanyDocumentController::class, 'destroy'])->name('destroy');
        });
    // Invoice management
    Route::prefix('invoices')->name('invoices.')->group(function () {
        Route::get('/', [InvoiceController::class, 'index'])->name('index');
        Route::get('/create', [InvoiceController::class, 'create'])->name('create');
        Route::post('/', [InvoiceController::class, 'store'])->name('store');

        Route::match(['GET', 'POST'], '/preview', [InvoiceController::class, 'previewNew'])
            ->name('preview.new');

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

        Route::post('/{invoice}/status', [InvoiceController::class, 'updateStatus'])
            ->whereNumber('invoice')
            ->name('status');

        Route::get('/{invoice}/preview', [InvoiceController::class, 'preview'])
            ->whereNumber('invoice')
            ->name('preview');

        Route::get('/{invoice}/print', [InvoiceController::class, 'print'])
            ->whereNumber('invoice')
            ->name('print');

        // Money received against an invoice, and the receipt it prints as
        Route::post('/{invoice}/payments', [\App\Http\Controllers\InvoicePaymentController::class, 'store'])
            ->whereNumber('invoice')
            ->name('payments.store');

        Route::delete('/{invoice}/payments/{payment}', [\App\Http\Controllers\InvoicePaymentController::class, 'destroy'])
            ->whereNumber(['invoice', 'payment'])
            ->name('payments.destroy');

        Route::get('/{invoice}/payments/{payment}/print', [\App\Http\Controllers\InvoicePaymentController::class, 'print'])
            ->whereNumber(['invoice', 'payment'])
            ->name('payments.print');

        // Goods dispatched against this invoice
        Route::post('/{invoice}/delivery-note', [\App\Http\Controllers\DeliveryNoteController::class, 'storeForInvoice'])
            ->whereNumber('invoice')
            ->name('delivery-note.store');
    });

    // Quotation management
    Route::prefix('quotations')->name('quotations.')->group(function () {
        Route::get('/', [\App\Http\Controllers\QuotationController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\QuotationController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\QuotationController::class, 'store'])->name('store');

        Route::match(['GET', 'POST'], '/preview', [\App\Http\Controllers\QuotationController::class, 'previewNew'])
            ->name('preview.new');

        Route::get('/{quotation}', [\App\Http\Controllers\QuotationController::class, 'show'])
            ->whereNumber('quotation')
            ->name('show');

        Route::post('/{quotation}/status', [\App\Http\Controllers\QuotationController::class, 'updateStatus'])
            ->whereNumber('quotation')
            ->name('status');

        Route::get('/{quotation}/preview', [\App\Http\Controllers\QuotationController::class, 'preview'])
            ->whereNumber('quotation')
            ->name('preview');

        Route::get('/{quotation}/print', [\App\Http\Controllers\QuotationController::class, 'print'])
            ->whereNumber('quotation')
            ->name('print');
    });

    // Credit notes
    Route::prefix('credit-notes')->name('credit-notes.')->group(function () {
        Route::get('/', [\App\Http\Controllers\CreditNoteController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\CreditNoteController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\CreditNoteController::class, 'store'])->name('store');

        Route::get('/{creditNote}', [\App\Http\Controllers\CreditNoteController::class, 'show'])
            ->whereNumber('creditNote')
            ->name('show');

        Route::post('/{creditNote}/status', [\App\Http\Controllers\CreditNoteController::class, 'updateStatus'])
            ->whereNumber('creditNote')
            ->name('status');

        Route::get('/{creditNote}/preview', [\App\Http\Controllers\CreditNoteController::class, 'preview'])
            ->whereNumber('creditNote')
            ->name('preview');

        Route::get('/{creditNote}/print', [\App\Http\Controllers\CreditNoteController::class, 'print'])
            ->whereNumber('creditNote')
            ->name('print');
    });

    // Delivery notes
    Route::prefix('delivery-notes')->name('delivery-notes.')->group(function () {
        Route::get('/', [\App\Http\Controllers\DeliveryNoteController::class, 'index'])->name('index');

        Route::get('/{deliveryNote}', [\App\Http\Controllers\DeliveryNoteController::class, 'show'])
            ->whereNumber('deliveryNote')
            ->name('show');

        Route::put('/{deliveryNote}', [\App\Http\Controllers\DeliveryNoteController::class, 'update'])
            ->whereNumber('deliveryNote')
            ->name('update');

        Route::get('/{deliveryNote}/preview', [\App\Http\Controllers\DeliveryNoteController::class, 'preview'])
            ->whereNumber('deliveryNote')
            ->name('preview');

        Route::get('/{deliveryNote}/print', [\App\Http\Controllers\DeliveryNoteController::class, 'print'])
            ->whereNumber('deliveryNote')
            ->name('print');
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
    Route::post('/currencies/active', [CurrencyController::class, 'updateActive'])->name('currencies.active');
    Route::post('/currencies/rates/sync', [CurrencyController::class, 'syncRates'])->name('currencies.rates.sync');
    Route::post('/currencies/{code}/rate', [CurrencyController::class, 'storeRate'])
        ->where('code', '[A-Za-z]{3}')
        ->name('currencies.rate.store');
    Route::delete('/currencies/{code}/rate', [CurrencyController::class, 'destroyRate'])
        ->where('code', '[A-Za-z]{3}')
        ->name('currencies.rate.destroy');
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
