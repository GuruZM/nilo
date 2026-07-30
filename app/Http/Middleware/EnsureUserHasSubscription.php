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
