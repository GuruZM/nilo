<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class LinkedInController extends Controller
{
    /**
     * Redirect the user to LinkedIn's OAuth consent screen.
     */
    public function redirect(Request $request): RedirectResponse
    {
        abort_unless(config('services.linkedin-openid.enabled'), 404);

        $request->session()->put('oauth_intent', $request->query('intent') === 'register' ? 'register' : 'login');
        $request->session()->put('oauth_terms', $request->query('terms') === '1');

        return Socialite::driver('linkedin-openid')->redirect();
    }

    /**
     * Handle the callback from LinkedIn.
     */
    public function callback(Request $request): RedirectResponse
    {
        abort_unless(config('services.linkedin-openid.enabled'), 404);

        $intent = $request->session()->pull('oauth_intent', 'login');
        $termsAccepted = $request->session()->pull('oauth_terms', false);

        try {
            $linkedinUser = Socialite::driver('linkedin-openid')->user();
        } catch (Throwable) {
            return redirect()->route('login')
                ->with('error', 'Unable to sign in with LinkedIn. Please try again.');
        }

        $existing = User::where('linkedin_id', $linkedinUser->getId())
            ->orWhere('email', $linkedinUser->getEmail())
            ->first();

        if ($existing) {
            if (! $existing->linkedin_id) {
                $existing->update(['linkedin_id' => $linkedinUser->getId()]);
            }

            Auth::login($existing);

            return redirect()->intended(config('fortify.home'));
        }

        if ($intent !== 'register') {
            return redirect()->route('register')
                ->with('error', 'No account found for that LinkedIn account. Please sign up first.');
        }

        if (! $termsAccepted) {
            return redirect()->route('register')
                ->with('error', 'You must agree to the Terms and Conditions to create an account.');
        }

        $user = User::create([
            'name' => $linkedinUser->getName() ?: $linkedinUser->getEmail(),
            'email' => $linkedinUser->getEmail(),
            'linkedin_id' => $linkedinUser->getId(),
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        event(new Registered($user));

        $user->subscribeToFreePlan();

        Auth::login($user);

        return redirect()->route('dashboard')
            ->with('success', 'Welcome to '.config('app.name').'! Your account is ready.');
    }
}
