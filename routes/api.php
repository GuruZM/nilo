<?php

use App\Http\Controllers\Api\V1\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Api\V1\Auth\GoogleTokenController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetLinkController;
use App\Http\Controllers\Api\V1\Auth\RegisteredUserController;
use App\Http\Controllers\Api\V1\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\CompanyController;
use App\Http\Controllers\Api\V1\CreditNoteController;
use App\Http\Controllers\Api\V1\CurrencyController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DeliveryNoteController;
use App\Http\Controllers\Api\V1\DocumentSetupController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\InvoicePaymentController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\PurchaseOrderController;
use App\Http\Controllers\Api\V1\QuotationController;
use App\Http\Controllers\Api\V1\SupplierController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * The mobile API.
 *
 * Stateless throughout: Sanctum bearer tokens carry identity, and the
 * `X-Company-Id` header carries the tenant. Nothing here reads or writes the
 * session, so a phone and an open browser tab never fight over the same
 * active company.
 */
Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('/register', [RegisteredUserController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('register');

        Route::post('/login', [AuthenticatedSessionController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('login');

        Route::post('/two-factor', [TwoFactorChallengeController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('two-factor');

        Route::post('/google', [GoogleTokenController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('google');

        Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
            ->middleware('throttle:6,1')
            ->name('forgot-password');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
            Route::delete('/tokens', [AuthenticatedSessionController::class, 'destroyAll'])->name('tokens.destroy');

            Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
                ->middleware('throttle:6,1')
                ->name('verification.send');
        });
    });

    /**
     * Bootstrapping: deliberately outside the company and subscription gates.
     * The app has to be able to read its context before it knows which company
     * to name, and while it still has no plan.
     */
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/ping', fn (Request $request) => response()->json([
            'ok' => true,
            'user_id' => $request->user()->id,
        ]))->name('ping');

        Route::get('/me', [MeController::class, 'show'])->name('me');
        Route::get('/companies', [CompanyController::class, 'index'])->name('companies.index');
        Route::get('/currencies', [CurrencyController::class, 'index'])->name('currencies.index');
    });

    /** The tenant app. Everything here acts for one company. */
    Route::middleware(['auth:sanctum', 'verified', 'subscribed', 'api.company'])->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::apiResource('clients', ClientController::class);
        Route::apiResource('suppliers', SupplierController::class);

        Route::get('/document-templates', [DocumentSetupController::class, 'templates'])
            ->name('document-templates.index');
        Route::get('/document-prerequisites', [DocumentSetupController::class, 'prerequisites'])
            ->name('document-prerequisites');

        Route::prefix('invoices')->name('invoices.')->group(function () {
            Route::get('/', [InvoiceController::class, 'index'])->name('index');
            Route::post('/', [InvoiceController::class, 'store'])->name('store');

            Route::whereNumber('invoice')->group(function () {
                Route::get('/{invoice}', [InvoiceController::class, 'show'])->name('show');
                Route::post('/{invoice}/status', [InvoiceController::class, 'updateStatus'])->name('status');
                Route::get('/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('pdf');

                /** Money received, and the receipt it prints as. */
                Route::get('/{invoice}/payments', [InvoicePaymentController::class, 'index'])->name('payments.index');
                Route::post('/{invoice}/payments', [InvoicePaymentController::class, 'store'])->name('payments.store');
                Route::delete('/{invoice}/payments/{payment}', [InvoicePaymentController::class, 'destroy'])
                    ->whereNumber('payment')->name('payments.destroy');
                Route::get('/{invoice}/payments/{payment}/pdf', [InvoicePaymentController::class, 'pdf'])
                    ->whereNumber('payment')->name('payments.pdf');

                /** Goods dispatched against this invoice. */
                Route::post('/{invoice}/delivery-note', [DeliveryNoteController::class, 'storeForInvoice'])
                    ->name('delivery-note.store');
            });
        });

        Route::prefix('quotations')->name('quotations.')->group(function () {
            Route::get('/', [QuotationController::class, 'index'])->name('index');
            Route::post('/', [QuotationController::class, 'store'])->name('store');

            Route::whereNumber('quotation')->group(function () {
                Route::get('/{quotation}', [QuotationController::class, 'show'])->name('show');
                Route::post('/{quotation}/status', [QuotationController::class, 'updateStatus'])->name('status');
                Route::get('/{quotation}/pdf', [QuotationController::class, 'pdf'])->name('pdf');
            });
        });

        Route::prefix('credit-notes')->name('credit-notes.')->group(function () {
            Route::get('/', [CreditNoteController::class, 'index'])->name('index');
            Route::post('/', [CreditNoteController::class, 'store'])->name('store');

            Route::whereNumber('creditNote')->group(function () {
                Route::get('/{creditNote}', [CreditNoteController::class, 'show'])->name('show');
                Route::post('/{creditNote}/status', [CreditNoteController::class, 'updateStatus'])->name('status');
                Route::get('/{creditNote}/pdf', [CreditNoteController::class, 'pdf'])->name('pdf');
            });
        });

        Route::prefix('purchase-orders')->name('purchase-orders.')->group(function () {
            Route::get('/', [PurchaseOrderController::class, 'index'])->name('index');
            Route::post('/', [PurchaseOrderController::class, 'store'])->name('store');

            Route::whereNumber('purchaseOrder')->group(function () {
                Route::get('/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->name('show');
                Route::post('/{purchaseOrder}/status', [PurchaseOrderController::class, 'updateStatus'])->name('status');
                Route::get('/{purchaseOrder}/pdf', [PurchaseOrderController::class, 'pdf'])->name('pdf');
            });
        });

        Route::prefix('delivery-notes')->name('delivery-notes.')->whereNumber('deliveryNote')->group(function () {
            Route::get('/', [DeliveryNoteController::class, 'index'])->name('index');
            Route::get('/{deliveryNote}', [DeliveryNoteController::class, 'show'])->name('show');
            Route::patch('/{deliveryNote}', [DeliveryNoteController::class, 'update'])->name('update');
            Route::get('/{deliveryNote}/pdf', [DeliveryNoteController::class, 'pdf'])->name('pdf');
        });
    });
});
