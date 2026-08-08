<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Establishes which company an API request is acting for.
 *
 * The web app keeps this in `users.current_company_id`, which works because a
 * browser session has exactly one active company at a time. A phone cannot
 * share that column — switching companies on the phone would move the
 * company under an open browser tab, and vice versa. So the API takes the
 * tenant from a header instead and treats the stored column as nothing more
 * than a starting suggestion.
 */
class ResolveApiCompany
{
    public const ATTRIBUTE = 'api_company_id';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $requested = (int) $request->header('X-Company-Id', 0);

        if ($requested && ! $user->isMemberOfCompany($requested)) {
            abort(403, 'You are not a member of that company.');
        }

        $companyId = $requested ?: $this->storedCompanyId($user);

        if (! $companyId) {
            abort(409, 'No company selected. Send an X-Company-Id header.');
        }

        /**
         * Set in memory and never saved. Services that read the active company
         * off the user — SubscriptionLimitService::usage() and
         * Company::defaultCurrencyCodeFor() among them — then report against
         * the header's company without any of them needing to know the API
         * exists, and the browser's own active company is left untouched.
         */
        $user->current_company_id = $companyId;

        $request->attributes->set(self::ATTRIBUTE, $companyId);

        return $next($request);
    }

    /**
     * The user's stored company, used only when the client did not name one.
     *
     * Falls through to their first company by name so a fresh install that has
     * never opened the web app still resolves to something.
     */
    private function storedCompanyId(\App\Models\User $user): int
    {
        $stored = (int) ($user->current_company_id ?? 0);

        if ($stored && $user->isMemberOfCompany($stored)) {
            return $stored;
        }

        return (int) ($user->companies()->orderBy('companies.name')->value('companies.id') ?? 0);
    }
}
