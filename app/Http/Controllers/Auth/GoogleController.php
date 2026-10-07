<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Settings\ConfirmIdentityController;
use App\Services\Auth\GoogleAccountLinker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleController extends Controller
{
    public function __construct(private GoogleAccountLinker $accounts) {}

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

        if (! $this->hasVerifiedEmail($googleUser)) {
            return redirect()->route('login')
                ->with('error', 'Your Google account email is not verified. Verify it with Google, or sign in with your password.');
        }

        $existing = $this->accounts->find($googleUser->getId(), $googleUser->getEmail());

        if ($existing) {
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

        $user = $this->accounts->create(
            $googleUser->getId(),
            $googleUser->getEmail(),
            $googleUser->getName(),
        );

        Auth::login($user);

        return redirect()->route('dashboard')
            ->with('success', 'Welcome to '.config('app.name').'! Your account is ready.');
    }

    /**
     * Whether Google vouches for the email on this identity.
     *
     * Accounts are matched and auto-linked by email, so an unverified address
     * would let a Google account claim a password account it does not own.
     * Mirrors the `email_verified` check on the mobile ID-token exchange.
     */
    private function hasVerifiedEmail(SocialiteUser $googleUser): bool
    {
        $claims = $googleUser instanceof AbstractUser ? $googleUser->getRaw() : [];

        return filled($googleUser->getEmail())
            && filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }
}
