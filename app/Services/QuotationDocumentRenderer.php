<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\InvoiceTemplate;
use App\Models\Quotation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\View;

/**
 * Renders a saved quotation through its company template. Quotations share the
 * invoice sheet — only the wording and the second date differ, which the blade
 * takes as `documentType` — so the preview, print view and attached PDF all
 * stay visually identical to their invoice counterparts.
 */
class QuotationDocumentRenderer
{
    private const TEMPLATE_VIEW = 'invoices.templates.default';

    public function __construct(private InvoiceDocumentRenderer $invoices) {}

    /**
     * @return array<string, mixed>
     */
    public function templateDefaults(): array
    {
        return $this->invoices->templateDefaults();
    }

    public function resolveTemplate(int $companyId, ?int $templateId = null): ?InvoiceTemplate
    {
        if (! empty($templateId)) {
            $template = InvoiceTemplate::query()
                ->where('company_id', $companyId)
                ->where('type', 'quotation')
                ->where('id', $templateId)
                ->first();

            if ($template) {
                return $template;
            }
        }

        return InvoiceTemplate::query()
            ->where('company_id', $companyId)
            ->where('type', 'quotation')
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function normalizedSettings(?InvoiceTemplate $template): array
    {
        return $this->invoices->normalizedSettings($template);
    }

    /**
     * The payload the shared document blade expects. `invoice` is the blade's
     * name for the document being drawn, whatever type it is.
     *
     * @return array<string, mixed>
     */
    public function viewData(Quotation $quotation, string $mode): array
    {
        $quotation->loadMissing(['client', 'items', 'company', 'template']);

        $template = $this->resolveTemplate(
            (int) $quotation->company_id,
            (int) ($quotation->quotation_template_id ?? 0)
        );

        $settings = $this->normalizedSettings($template);

        if (! $template) {
            $template = new InvoiceTemplate(['settings' => $settings]);
        }

        return [
            'company' => $quotation->company,
            'client' => $quotation->client,
            'template' => $template,
            'currency' => Currency::query()
                ->where('code', $quotation->currency_code)
                ->first(),
            'invoice' => $quotation,
            'items' => $quotation->items,
            'settings' => $settings,
            'documentType' => 'quotation',
            'mode' => $mode,
        ];
    }

    public function html(Quotation $quotation, string $mode = 'preview'): string
    {
        return View::make(self::TEMPLATE_VIEW, $this->viewData($quotation, $mode))->render();
    }

    /**
     * Raw PDF bytes, rendered in `pdf` mode so the on-screen toolbar is omitted.
     */
    public function pdf(Quotation $quotation): string
    {
        return Pdf::loadHTML($this->html($quotation, 'pdf'))
            ->setPaper('a4')
            ->output();
    }

    /**
     * Filename for an attached PDF.
     */
    public function filename(Quotation $quotation): string
    {
        $reference = $quotation->number ?: 'quotation-'.$quotation->id;

        return preg_replace('/[^A-Za-z0-9_\-]+/', '-', $reference).'.pdf';
    }
}
