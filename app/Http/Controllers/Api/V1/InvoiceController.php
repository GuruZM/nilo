<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\GuardsDocumentCreation;
use App\Http\Controllers\Api\V1\Concerns\PaginatesApiResults;
use App\Http\Controllers\Api\V1\Concerns\ResolvesCompany;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\InvoiceListResource;
use App\Http\Resources\Api\V1\InvoiceResource;
use App\Models\Invoice;
use App\Services\DocumentPrerequisites;
use App\Services\Documents\CreateInvoice;
use App\Services\InvoiceDocumentRenderer;
use App\Services\SubscriptionLimitService;
use App\Support\DocumentRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class InvoiceController extends Controller
{
    use GuardsDocumentCreation, PaginatesApiResults, ResolvesCompany;

    public function __construct(
        private CreateInvoice $creator,
        private InvoiceDocumentRenderer $documents,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'status' => ['nullable', 'string'],
            'client_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:190'],
            'outstanding' => ['nullable', 'boolean'],
        ]);

        $invoices = Invoice::query()
            ->where('company_id', $this->companyId($request))
            ->with(['client:id,company_id,name'])
            ->when($request->string('status')->value(), fn ($query, $status) => $query->where('status', $status))
            ->when($request->integer('client_id'), fn ($query, $clientId) => $query->where('client_id', $clientId))
            ->when($request->boolean('outstanding'), fn ($query) => $query->outstanding())
            ->when($request->string('search')->value(), fn ($query, $search) => $query
                ->where(fn ($scoped) => $scoped
                    ->where('number', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")))
            ->orderByDesc('created_at')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return InvoiceListResource::collection($invoices);
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);
        $user = $request->user();

        if ($refusal = $this->prerequisiteRefusal(DocumentPrerequisites::forInvoices($companyId))) {
            return $refusal;
        }

        $limiter = new SubscriptionLimitService($user);

        if ($refusal = $this->planLimitRefusal($user, $limiter->canCreateInvoice($companyId), 'invoices')) {
            return $refusal;
        }

        $data = $request->validate(DocumentRules::invoice($companyId));

        $invoice = $this->creator->handle($companyId, $user, $data);

        return InvoiceResource::make($this->loadForShow($invoice))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Invoice $invoice): InvoiceResource
    {
        $this->guardCompany($request, $invoice);

        return InvoiceResource::make($this->loadForShow($invoice));
    }

    /**
     * The statuses a person may set by hand. `paid` and `partially_paid` are
     * derived from the ledger by {@see \App\Services\InvoiceSettlement}, but the
     * web app still offers them here, so the API matches rather than inventing
     * a stricter rule the two clients would disagree about.
     */
    public function updateStatus(Request $request, Invoice $invoice): InvoiceResource
    {
        $this->guardCompany($request, $invoice);

        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(['pending', 'paid', 'partially_paid'])],
        ]);

        $invoice->update(['status' => $data['status']]);

        return InvoiceResource::make($this->loadForShow($invoice->fresh()));
    }

    /**
     * The branded document, as a file the phone can open or share.
     */
    public function pdf(Request $request, Invoice $invoice): Response
    {
        $this->guardCompany($request, $invoice);

        return response($this->documents->pdf($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->documents->filename($invoice).'"',
        ]);
    }

    private function loadForShow(Invoice $invoice): Invoice
    {
        return $invoice->load([
            'client:id,company_id,name,email,contact_person,phone,address',
            'items',
            'payments.recorder:id,name',
        ]);
    }
}
