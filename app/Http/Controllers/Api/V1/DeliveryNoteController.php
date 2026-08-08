<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\PaginatesApiResults;
use App\Http\Controllers\Api\V1\Concerns\ResolvesCompany;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\DeliveryNoteResource;
use App\Models\DeliveryNote;
use App\Models\Invoice;
use App\Services\DocumentRenderer;
use App\Services\Documents\CreateDeliveryNoteFromInvoice;
use App\Support\DocumentRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Delivery notes are raised from an invoice, never on their own — the same rule
 * the web app follows, which is why there is no plain `store` here.
 */
class DeliveryNoteController extends Controller
{
    use PaginatesApiResults, ResolvesCompany;

    public function __construct(
        private CreateDeliveryNoteFromInvoice $creator,
        private DocumentRenderer $documents,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $notes = DeliveryNote::query()
            ->where('company_id', $this->companyId($request))
            ->with(['client:id,company_id,name', 'invoice:id,number'])
            ->when($request->string('status')->value(), fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return DeliveryNoteResource::collection($notes);
    }

    public function storeForInvoice(Request $request, Invoice $invoice): JsonResponse
    {
        $this->guardCompany($request, $invoice);

        $note = $this->creator->handle($this->companyId($request), $request->user(), $invoice);

        return DeliveryNoteResource::make($this->loadForShow($note))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, DeliveryNote $deliveryNote): DeliveryNoteResource
    {
        $this->guardCompany($request, $deliveryNote);

        return DeliveryNoteResource::make($this->loadForShow($deliveryNote));
    }

    /**
     * Sign-off: who took the goods, when, and how far along the dispatch is.
     */
    public function update(Request $request, DeliveryNote $deliveryNote): DeliveryNoteResource
    {
        $this->guardCompany($request, $deliveryNote);

        $deliveryNote->update($request->validate(DocumentRules::deliveryNoteUpdate()));

        return DeliveryNoteResource::make($this->loadForShow($deliveryNote->fresh()));
    }

    public function pdf(Request $request, DeliveryNote $deliveryNote): Response
    {
        $this->guardCompany($request, $deliveryNote);

        return response($this->documents->pdf($deliveryNote), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->documents->filename($deliveryNote).'"',
        ]);
    }

    private function loadForShow(DeliveryNote $note): DeliveryNote
    {
        return $note->load([
            'client:id,company_id,name,email,address,contact_person',
            'invoice:id,number',
            'items',
        ]);
    }
}
