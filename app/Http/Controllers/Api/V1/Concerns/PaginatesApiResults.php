<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use Illuminate\Http\Request;

trait PaginatesApiResults
{
    /**
     * How many rows a list endpoint returns.
     *
     * The web app reads its lists unbounded because it renders them in one go;
     * a phone on a slow connection cannot, so every API list is paged. The cap
     * means a client cannot ask for the whole table by naming a large number.
     */
    protected function perPage(Request $request, int $default = 25): int
    {
        return min(max((int) $request->integer('per_page', $default), 1), 100);
    }
}
