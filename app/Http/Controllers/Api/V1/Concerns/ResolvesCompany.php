<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Http\Middleware\ResolveApiCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

trait ResolvesCompany
{
    /**
     * The company this request is acting for, established by
     * {@see ResolveApiCompany}. Routes carrying this trait always run behind
     * that middleware, so the value is guaranteed present.
     */
    protected function companyId(Request $request): int
    {
        return (int) $request->attributes->get(ResolveApiCompany::ATTRIBUTE);
    }

    /**
     * Route model binding resolves by id alone, so a row from another company
     * binds perfectly well. Every bound model has to be checked against the
     * active company before it is read or written.
     */
    protected function guardCompany(Request $request, Model $model): void
    {
        abort_unless((int) $model->company_id === $this->companyId($request), 403);
    }
}
