<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesCompany;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\InvoicePaymentResource;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Services\DocumentRenderer;
use App\Services\Documents\RecordInvoicePayment;
use App\Services\InvoiceSettlement;
use App\Support\DocumentRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Money received against an invoice, and the receipt it prints as.
 *
 * The invoice status is never written here — {@see InvoiceSettlement} derives
 * it from the ledger.
 */
class InvoicePaymentController extends Controller
{
    use ResolvesCompany;

    public function __construct(
        private RecordInvoicePayment $recorder,
        private InvoiceSettlement $settlement,
        private DocumentRenderer $documents,
    ) {}

    public function index(Request $request, Invoice $invoice): AnonymousResourceCollection
    {
        $this->guardCompany($request, $invoice);

        return InvoicePaymentResource::collection(
            $invoice->payments()->with('recorder:id,name')->get()
        );
    }

    public function store(Request $request, Invoice $invoice): JsonResponse
    {
        $companyId = $this->companyId($request);

        $this->guardCompany($request, $invoice);

        $balance = $this->settlement->balanceDue($invoice);

        $data = $request->validate(
            DocumentRules::invoicePayment($balance),
            DocumentRules::invoicePaymentMessages($invoice, $balance),
        );

        $payment = $this->recorder->handle($companyId, $request->user(), $invoice, $data);

        return InvoicePaymentResource::make($payment->load('recorder:id,name'))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Both halves are checked: the invoice must be in the active company, and
     * the payment must belong to that invoice — otherwise one company's receipt
     * could be reached through another company's invoice.
     */
    public function destroy(Request $request, Invoice $invoice, InvoicePayment $payment): JsonResponse
    {
        $this->guardPayment($request, $invoice, $payment);

        $this->recorder->remove($invoice, $payment);

        return response()->json(['message' => 'Payment removed.']);
    }

    public function pdf(Request $request, Invoice $invoice, InvoicePayment $payment): Response
    {
        $this->guardPayment($request, $invoice, $payment);

        return response($this->documents->pdf($payment), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->documents->filename($payment).'"',
        ]);
    }

    private function guardPayment(Request $request, Invoice $invoice, InvoicePayment $payment): void
    {
        $this->guardCompany($request, $invoice);

        abort_unless((int) $payment->invoice_id === (int) $invoice->id, 403);
    }
}
