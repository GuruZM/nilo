<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesCompany;
use App\Http\Controllers\Controller;
use App\Models\InvoiceTemplate;
use App\Services\DocumentPrerequisites;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * What the app needs before it can offer a create form: the templates to pick
 * from, and whether creating is possible at all.
 */
class DocumentSetupController extends Controller
{
    use ResolvesCompany;

    /**
     * Templates for a document type, so the create form has something to bind
     * to. The `type` column serves every document type, so it is always the
     * filter — an invoice must never be offered a quotation's template.
     */
    public function templates(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['required', 'in:invoice,quotation,credit_note,delivery_note,purchase_order'],
        ]);

        $templates = InvoiceTemplate::query()
            ->where('company_id', $this->companyId($request))
            ->where('type', $request->string('type')->value())
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'is_default']);

        return response()->json(['data' => $templates]);
    }

    /**
     * Whether an invoice or quotation can be created yet, and what is missing
     * if not.
     *
     * `action_href` in each blocker is a web path. Mobile clients should branch
     * on the blocker's `key` — `company`, `template`, `client`, `currency` —
     * and route to their own screens.
     */
    public function prerequisites(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['required', 'in:invoice,quotation'],
        ]);

        $companyId = $this->companyId($request);

        $prerequisites = $request->string('type')->value() === 'quotation'
            ? DocumentPrerequisites::forQuotations($companyId)
            : DocumentPrerequisites::forInvoices($companyId);

        return response()->json($prerequisites->toArray());
    }
}
