<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class PasswordResetLinkController extends Controller
{
    /**
     * Send a password reset link.
     *
     * The reply is the same whether or not the address is on file — matching
     * the web controller, which says so deliberately rather than confirming
     * which emails have accounts. The link itself lands the user in the browser;
     * there is no in-app reset screen.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        Password::sendResetLink($request->only('email'));

        return response()->json([
            'message' => __('A reset link will be sent if the account exists.'),
        ]);
    }
}
