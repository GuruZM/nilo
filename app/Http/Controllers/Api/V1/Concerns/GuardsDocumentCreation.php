<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Models\User;
use App\Services\DocumentPrerequisites;
use App\Services\SubscriptionLimitService;
use Illuminate\Http\JsonResponse;

/**
 * The two refusals that stop a document being created.
 *
 * The web app answers both with a flash the page renders as a dialog, which a
 * stateless client would silently discard. These return real statuses instead,
 * carrying the same payload that dialog is built from so the app can show the
 * same words and the same call to action.
 *
 * Both return a response rather than throwing, because the payload is the
 * useful part and an exception would flatten it to a message.
 */
trait GuardsDocumentCreation
{
    /**
     * 402 rather than 403: the refusal is about what the account has paid for,
     * not about who they are, and 403 would suggest upgrading could not help.
     *
     * `action_href` inside the notice is a web URL. Clients should route on
     * their own screens and use the notice only for its wording.
     */
    protected function planLimitRefusal(User $user, bool $allowed, string $documents): ?JsonResponse
    {
        if ($allowed) {
            return null;
        }

        $notice = (new SubscriptionLimitService($user))->limitNotice($documents);

        return response()->json([
            'message' => $notice['message'],
            'error' => 'plan_limit_reached',
            'limit_notice' => $notice,
        ], 402);
    }

    /**
     * 422, because the account is entitled to create this — something in the
     * company's setup is simply missing. Clients should branch on each
     * blocker's `key`, not its href.
     */
    protected function prerequisiteRefusal(DocumentPrerequisites $prerequisites): ?JsonResponse
    {
        if ($prerequisites->canCreate()) {
            return null;
        }

        $blockers = $prerequisites->blockers();

        return response()->json([
            'message' => $blockers[0]['description'],
            'error' => 'prerequisites_unmet',
            'blockers' => $blockers,
        ], 422);
    }
}
