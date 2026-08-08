<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\GuardsDocumentCreation;
use App\Http\Controllers\Api\V1\Concerns\PaginatesApiResults;
use App\Http\Controllers\Api\V1\Concerns\ResolvesCompany;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\QuotationResource;
use App\Models\Quotation;
use App\Services\DocumentPrerequisites;
use App\Services\Documents\CreateQuotation;
use App\Services\QuotationDocumentRenderer;
use App\Services\SubscriptionLimitService;
use App\Support\DocumentRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class QuotationController extends Controller
{
    use GuardsDocumentCreation, PaginatesApiResults, ResolvesCompany;

    public function __construct(
        private CreateQuotation $creator,
        private QuotationDocumentRenderer $documents,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $quotations = Quotation::query()
            ->where('company_id', $this->companyId($request))
            ->with(['client:id,company_id,name'])
            ->when($request->string('status')->value(), fn ($query, $status) => $query->where('status', $status))
            ->when($request->integer('client_id'), fn ($query, $clientId) => $query->where('client_id', $clientId))
            ->orderByDesc('created_at')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return QuotationResource::collection($quotations);
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);
        $user = $request->user();

        if ($refusal = $this->prerequisiteRefusal(DocumentPrerequisites::forQuotations($companyId))) {
            return $refusal;
        }

        $limiter = new SubscriptionLimitService($user);

        if ($refusal = $this->planLimitRefusal($user, $limiter->canCreateQuotation($companyId), 'quotations')) {
            return $refusal;
        }

        $data = $request->validate(DocumentRules::quotation($companyId));

        $quotation = $this->creator->handle($companyId, $user, $data);

        return QuotationResource::make($this->loadForShow($quotation))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Quotation $quotation): QuotationResource
    {
        $this->guardCompany($request, $quotation);

        return QuotationResource::make($this->loadForShow($quotation));
    }

    public function updateStatus(Request $request, Quotation $quotation): QuotationResource
    {
        $this->guardCompany($request, $quotation);

        $data = $request->validate([
            'status' => ['required', Rule::in(['draft', 'sent', 'accepted', 'expired'])],
        ]);

        $quotation->update(['status' => $data['status']]);

        return QuotationResource::make($this->loadForShow($quotation->fresh()));
    }

    public function pdf(Request $request, Quotation $quotation): Response
    {
        $this->guardCompany($request, $quotation);

        return response($this->documents->pdf($quotation), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->documents->filename($quotation).'"',
        ]);
    }

    private function loadForShow(Quotation $quotation): Quotation
    {
        return $quotation->load([
            'client:id,company_id,name,email,contact_person,phone,address',
            'items',
        ]);
    }
}
