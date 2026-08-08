<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\GuardsDocumentCreation;
use App\Http\Controllers\Api\V1\Concerns\PaginatesApiResults;
use App\Http\Controllers\Api\V1\Concerns\ResolvesCompany;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Services\DocumentRenderer;
use App\Services\Documents\CreatePurchaseOrder;
use App\Services\SubscriptionLimitService;
use App\Support\DocumentRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class PurchaseOrderController extends Controller
{
    use GuardsDocumentCreation, PaginatesApiResults, ResolvesCompany;

    public function __construct(
        private CreatePurchaseOrder $creator,
        private DocumentRenderer $documents,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $orders = PurchaseOrder::query()
            ->where('company_id', $this->companyId($request))
            ->with(['supplier:id,company_id,name'])
            ->when($request->string('status')->value(), fn ($query, $status) => $query->where('status', $status))
            ->when($request->integer('supplier_id'), fn ($query, $supplierId) => $query->where('supplier_id', $supplierId))
            ->orderByDesc('created_at')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return PurchaseOrderResource::collection($orders);
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);
        $user = $request->user();

        $limiter = new SubscriptionLimitService($user);

        if ($refusal = $this->planLimitRefusal($user, $limiter->canCreatePurchaseOrder($companyId), 'purchase orders')) {
            return $refusal;
        }

        $data = $request->validate(DocumentRules::purchaseOrder($companyId));

        $order = $this->creator->handle($companyId, $user, $data);

        return PurchaseOrderResource::make($this->loadForShow($order))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        $this->guardCompany($request, $purchaseOrder);

        return PurchaseOrderResource::make($this->loadForShow($purchaseOrder));
    }

    public function updateStatus(Request $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        $this->guardCompany($request, $purchaseOrder);

        $data = $request->validate([
            'status' => ['required', Rule::in(PurchaseOrder::STATUSES)],
        ]);

        $purchaseOrder->update(['status' => $data['status']]);

        return PurchaseOrderResource::make($this->loadForShow($purchaseOrder->fresh()));
    }

    public function pdf(Request $request, PurchaseOrder $purchaseOrder): Response
    {
        $this->guardCompany($request, $purchaseOrder);

        return response($this->documents->pdf($purchaseOrder), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->documents->filename($purchaseOrder).'"',
        ]);
    }

    private function loadForShow(PurchaseOrder $order): PurchaseOrder
    {
        return $order->load([
            'supplier:id,company_id,name,email,contact_person,phone,address',
            'items',
        ]);
    }
}
