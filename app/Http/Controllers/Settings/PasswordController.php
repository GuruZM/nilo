<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class PasswordController extends Controller
{
    /**
     * Show the user's password settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/password', [
            'hasPassword' => $request->user()->hasPassword(),
        ]);
    }

    /**
     * Update the user's password.
     *
     * Accounts created through a social provider have no password to confirm,
     * so requiring one would leave them unable to ever set a first password.
     */
    public function update(Request $request): RedirectResponse
    {
        $rules = [
            'password' => ['required', Password::defaults(), 'confirmed'],
        ];

        if ($request->user()->hasPassword()) {
            $rules['current_password'] = ['required', 'current_password'];
        }

        $validated = $request->validate($rules);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back();
    }
}
