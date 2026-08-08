<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CompanyResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CompanyController extends Controller
{
    /**
     * The companies this user can act for.
     *
     * There is no switch endpoint to go with it: the API expresses the active
     * company per request through the `X-Company-Id` header, so switching is
     * something the client decides rather than something the server records.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return CompanyResource::collection(
            $request->user()->companies()->orderBy('name')->get()
        );
    }
}
