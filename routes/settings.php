<?php

use App\Http\Controllers\Settings\ConfirmIdentityController;
use App\Http\Controllers\Settings\PasskeyController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\TwoFactorAuthenticationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');

    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('password.update');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/appearance');
    })->name('appearance.edit');

    Route::get('settings/two-factor', [TwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.show');

    Route::get('settings/passkeys', [PasskeyController::class, 'show'])
        ->name('passkeys.show');

    Route::post('settings/confirm-identity/password', [ConfirmIdentityController::class, 'password'])
        ->middleware('throttle:6,1')
        ->name('confirm-identity.password');

    /**
     * There is no matching callback route: providers return to the sign-in
     * callback they already have registered, which hands the return leg to
     * ConfirmIdentityController when a confirmation is in flight.
     */
    Route::get('settings/confirm-identity/{provider}', [ConfirmIdentityController::class, 'redirectToProvider'])
        ->whereIn('provider', ConfirmIdentityController::supportedProviders())
        ->name('confirm-identity.redirect');
});
