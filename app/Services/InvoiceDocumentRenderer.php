<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\Invoice;
use App\Models\InvoiceTemplate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\View;

/**
 * Renders a saved invoice through its company template. The controller uses
 * this for the on-screen preview and print views; the client mailable uses it
 * for the attached PDF, so all three stay visually identical.
 */
class InvoiceDocumentRenderer
{
    private const TEMPLATE_VIEW = 'invoices.templates.default';

    /**
     * @return array<string, mixed>
     */
    public function templateDefaults(): array
    {
        return [
            'preset' => 'wave_premium',
            'brand' => [
                'primary' => '#111827',
                'accent' => '#F59E0B',
                'header' => '#111827',
                'font' => 'Inter',
            ],
            'layout' => [
                'header' => 'split',
                'table' => 'striped',
                'density' => 'normal',
            ],
            'visibility' => [
                'show_logo' => true,
                'show_client_email' => true,
                'show_contact_person' => true,
                'show_terms' => true,
                'show_notes' => true,
                'show_bank_details' => false,
                'show_signature' => false,
            ],
        ];
    }

    public function resolveTemplate(int $companyId, ?int $templateId = null): ?InvoiceTemplate
    {
        if (! empty($templateId)) {
            $template = InvoiceTemplate::query()
                ->where('company_id', $companyId)
                ->where('type', 'invoice')
                ->where('id', $templateId)
                ->first();

            if ($template) {
                return $template;
            }
        }

        return InvoiceTemplate::query()
            ->where('company_id', $companyId)
            ->where('type', 'invoice')
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function normalizedSettings(?InvoiceTemplate $template): array
    {
        $defaults = $this->templateDefaults();
        $saved = is_array($template?->settings) ? $template->settings : [];

        $settings = array_replace_recursive($defaults, $saved);
        $settings['visibility'] = array_merge(
            $defaults['visibility'],
            array_map(fn ($v) => (bool) $v, (array) ($settings['visibility'] ?? []))
        );

        return $settings;
    }

    /**
     * The payload the invoice template blade expects.
     *
     * @return array<string, mixed>
     */
    public function viewData(Invoice $invoice, string $mode): array
    {
        $invoice->loadMissing(['client', 'items', 'company', 'template']);

        $template = $this->resolveTemplate(
            (int) $invoice->company_id,
            (int) ($invoice->invoice_template_id ?? 0)
        );

        $settings = $this->normalizedSettings($template);

        if (! $template) {
            $template = new InvoiceTemplate(['settings' => $settings]);
        }

        return [
            'company' => $invoice->company,
            'client' => $invoice->client,
            'template' => $template,
            'currency' => Currency::query()
                ->where('code', $invoice->currency_code)
                ->first(),
            'invoice' => $invoice,
            'items' => $invoice->items,
            'settings' => $settings,
            'mode' => $mode,
        ];
    }

    public function html(Invoice $invoice, string $mode = 'preview'): string
    {
        return View::make(self::TEMPLATE_VIEW, $this->viewData($invoice, $mode))->render();
    }

    /**
     * Raw PDF bytes, rendered in `pdf` mode so the on-screen toolbar is omitted.
     */
    public function pdf(Invoice $invoice): string
    {
        return Pdf::loadHTML($this->html($invoice, 'pdf'))
            ->setPaper('a4')
            ->output();
    }

    /**
     * Filename for an attached PDF.
     */
    public function filename(Invoice $invoice): string
    {
        $reference = $invoice->number ?: 'invoice-'.$invoice->id;

        return preg_replace('/[^A-Za-z0-9_\-]+/', '-', $reference).'.pdf';
    }
}
