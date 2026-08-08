<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Services\DocumentRenderer;
use App\Services\Documents\RecordInvoicePayment;
use App\Services\InvoiceSettlement;
use App\Support\DocumentRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Money received against an invoice: recorded, removed, and printed as the
 * client's receipt.
 *
 * The invoice status is never written here — {@see InvoiceSettlement} derives
 * it from the ledger, so recording and removing cannot disagree about whether
 * an invoice is settled.
 */
class InvoicePaymentController extends Controller
{
    public function __construct(
        private InvoiceSettlement $settlement,
        private DocumentRenderer $documents,
        private RecordInvoicePayment $recorder,
    ) {}

    private function resolveCompanyId(Request $request): ?int
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        $companyId = (int) ($user->current_company_id ?? 0);

        if ($companyId && $user->isMemberOfCompany($companyId)) {
            return $companyId;
        }

        $fallbackCompanyId = (int) ($user->companies()
            ->orderBy('companies.name')
            ->value('companies.id') ?? 0);

        if ($fallbackCompanyId) {
            $user->forceFill(['current_company_id' => $fallbackCompanyId])->save();
        }

        return $fallbackCompanyId ?: null;
    }

    private function companyId(Request $request): int
    {
        $companyId = $this->resolveCompanyId($request);

        if (! $companyId) {
            throw ValidationException::withMessages([
                'company_id' => 'No active company selected.',
            ]);
        }

        return $companyId;
    }

    /**
     * Route model binding resolves a payment by id alone, so a row from another
     * company binds perfectly well. Both halves are checked here: the invoice
     * must be in the active company, and the payment must belong to that
     * invoice — otherwise one company's receipt could be printed through
     * another company's invoice.
     */
    private function guardPayment(Request $request, Invoice $invoice, InvoicePayment $payment): void
    {
        abort_unless((int) $invoice->company_id === $this->companyId($request), 403);
        abort_unless((int) $payment->invoice_id === (int) $invoice->id, 403);
    }

    public function store(Request $request, Invoice $invoice): RedirectResponse
    {
        $companyId = $this->companyId($request);

        abort_unless((int) $invoice->company_id === $companyId, 403);

        $balance = $this->settlement->balanceDue($invoice);

        $data = $request->validate(
            DocumentRules::invoicePayment($balance),
            DocumentRules::invoicePaymentMessages($invoice, $balance),
        );

        $this->recorder->handle($companyId, $request->user(), $invoice, $data);

        return back()->with('success', 'Payment recorded.');
    }

    public function destroy(Request $request, Invoice $invoice, InvoicePayment $payment): RedirectResponse
    {
        $this->guardPayment($request, $invoice, $payment);

        $this->recorder->remove($invoice, $payment);

        return back()->with('success', 'Payment removed.');
    }

    public function print(Request $request, Invoice $invoice, InvoicePayment $payment)
    {
        $this->guardPayment($request, $invoice, $payment);

        return response()->view(
            'invoices.templates.default',
            $this->documents->viewData($payment, 'print') + [
                'autoPrint' => $request->boolean('download'),
            ]
        );
    }
}
