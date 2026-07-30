<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Settings\ConfirmIdentityController;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleController extends Controller
{
    /**
     * Redirect the user to Google's OAuth consent screen.
     */
    public function redirect(Request $request): RedirectResponse
    {
        $request->session()->put('oauth_intent', $request->query('intent') === 'register' ? 'register' : 'login');
        $request->session()->put('oauth_terms', $request->query('terms') === '1');

        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle the callback from Google.
     *
     * This is the only redirect URI registered with Google, so it serves two
     * journeys: signing in, and re-authenticating to confirm identity before a
     * security change. The session says which one the user is on.
     */
    public function callback(Request $request): RedirectResponse
    {
        if ($request->user()) {
            return ConfirmIdentityController::isPending($request, 'google')
                ? app(ConfirmIdentityController::class)->callback($request, 'google')
                : redirect(config('fortify.home'));
        }

        $intent = $request->session()->pull('oauth_intent', 'login');
        $termsAccepted = $request->session()->pull('oauth_terms', false);

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable) {
            return redirect()->route('login')
                ->with('error', 'Unable to sign in with Google. Please try again.');
        }

        $existing = User::where('google_id', $googleUser->getId())
            ->orWhere('email', $googleUser->getEmail())
            ->first();

        if ($existing) {
            if (! $existing->google_id) {
                $existing->update(['google_id' => $googleUser->getId()]);
            }

            Auth::login($existing);

            return redirect()->intended(config('fortify.home'));
        }

        if ($intent !== 'register') {
            return redirect()->route('register')
                ->with('error', 'No account found for that Google account. Please sign up first.');
        }

        if (! $termsAccepted) {
            return redirect()->route('register')
                ->with('error', 'You must agree to the Terms and Conditions to create an account.');
        }

        $user = User::create([
            'name' => $googleUser->getName() ?: $googleUser->getEmail(),
            'email' => $googleUser->getEmail(),
            'google_id' => $googleUser->getId(),
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        event(new Registered($user));

        $user->subscribeToFreePlan();

        Auth::login($user);

        return redirect()->route('dashboard')
            ->with('success', 'Welcome to '.config('app.name').'! Your account is ready.');
    }
}
