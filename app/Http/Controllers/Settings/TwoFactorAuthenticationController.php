<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class TwoFactorAuthenticationController extends Controller
{
    /**
     * Show the user's two-factor authentication settings page.
     *
     * The page itself is deliberately ungated. Fortify already requires a
     * confirmed session on every route that changes two-factor state, so
     * gating this page too only produced a full-page redirect that looked
     * like a logout. Confirmation now happens in a modal on the action.
     */
    public function show(TwoFactorAuthenticationRequest $request): Response
    {
        $request->ensureStateIsValid();

        $user = $request->user();

        return Inertia::render('settings/two-factor', [
            'twoFactorEnabled' => $user->hasEnabledTwoFactorAuthentication(),
            'requiresConfirmation' => Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm'),
            'identityConfirmed' => ConfirmIdentityController::isConfirmed($request),
            'hasPassword' => $user->hasPassword(),
            'reauthProviders' => array_values(array_intersect(
                $user->linkedProviders(),
                ConfirmIdentityController::supportedProviders(),
            )),
            'resumeAction' => $request->session()->get('resumeAction'),
        ]);
    }
}
