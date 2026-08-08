<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->hasActiveSubscription()) {
            // A redirect is useless to a stateless client, so say what is wrong
            // in a status it can branch on. Checked first because the admin
            // redirect below is equally meaningless over the API.
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Your account has no active plan.',
                    'error' => 'subscription_required',
                ], 402);
            }

            // Nilo staff run the admin area rather than the tenant app, so send
            // them to their own dashboard instead of asking them to buy a plan.
            if ($user->hasRole('super-admin')) {
                return redirect()->route('admin.dashboard');
            }

            return redirect()->route('subscription.select');
        }

        return $next($request);
    }
}
