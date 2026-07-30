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

class FacebookController extends Controller
{
    /**
     * Redirect the user to Facebook's OAuth consent screen.
     */
    public function redirect(Request $request): RedirectResponse
    {
        abort_unless(config('services.facebook.enabled'), 404);

        $request->session()->put('oauth_intent', $request->query('intent') === 'register' ? 'register' : 'login');
        $request->session()->put('oauth_terms', $request->query('terms') === '1');

        return Socialite::driver('facebook')->redirect();
    }

    /**
     * Handle the callback from Facebook.
     *
     * Doubles as the return leg for identity confirmation, as Google's does.
     */
    public function callback(Request $request): RedirectResponse
    {
        abort_unless(config('services.facebook.enabled'), 404);

        if ($request->user()) {
            return ConfirmIdentityController::isPending($request, 'facebook')
                ? app(ConfirmIdentityController::class)->callback($request, 'facebook')
                : redirect(config('fortify.home'));
        }

        $intent = $request->session()->pull('oauth_intent', 'login');
        $termsAccepted = $request->session()->pull('oauth_terms', false);

        try {
            $facebookUser = Socialite::driver('facebook')->user();
        } catch (Throwable) {
            return redirect()->route('login')
                ->with('error', 'Unable to sign in with Facebook. Please try again.');
        }

        $existing = User::where('facebook_id', $facebookUser->getId())
            ->orWhere('email', $facebookUser->getEmail())
            ->first();

        if ($existing) {
            if (! $existing->facebook_id) {
                $existing->update(['facebook_id' => $facebookUser->getId()]);
            }

            Auth::login($existing);

            return redirect()->intended(config('fortify.home'));
        }

        if ($intent !== 'register') {
            return redirect()->route('register')
                ->with('error', 'No account found for that Facebook account. Please sign up first.');
        }

        if (! $termsAccepted) {
            return redirect()->route('register')
                ->with('error', 'You must agree to the Terms and Conditions to create an account.');
        }

        $user = User::create([
            'name' => $facebookUser->getName() ?: $facebookUser->getEmail(),
            'email' => $facebookUser->getEmail(),
            'facebook_id' => $facebookUser->getId(),
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        event(new Registered($user));

        $user->subscribeToFreePlan();

        Auth::login($user);

        return redirect()->route('dashboard')
            ->with('success', 'Welcome to '.config('app.name').'! Your account is ready.');
    }
}
