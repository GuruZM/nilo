<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PasskeyController extends Controller
{
    /**
     * Show the user's passkeys.
     *
     * Like the two-factor page, this page is ungated: Fortify's own routes
     * carry `password.confirm`, so the confirmation happens in a modal on the
     * action rather than as a full-page redirect that reads like a logout.
     */
    public function show(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/passkeys', [
            'passkeys' => $user->passkeys()->latest()->get()
                ->map(fn ($passkey): array => [
                    'id' => $passkey->id,
                    'name' => $passkey->name,
                    'created_at' => $passkey->created_at?->toIso8601String(),
                    'last_used_at' => $passkey->last_used_at?->toIso8601String(),
                ])
                ->all(),
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
