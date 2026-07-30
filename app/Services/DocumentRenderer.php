<?php

namespace App\Services;

use App\Contracts\RenderableDocument;
use App\Models\Currency;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\View;

/**
 * Renders any document implementing {@see RenderableDocument} through the
 * shared sheet, so the preview, the print view and the attached PDF cannot
 * drift apart.
 */
class DocumentRenderer
{
    private const TEMPLATE_VIEW = 'invoices.templates.default';

    public function __construct(
        private TemplateProvisioner $templates,
        private InvoiceDocumentRenderer $settings,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function viewData(RenderableDocument&Model $document, string $mode): array
    {
        $type = $document->documentType();

        $template = $this->templates->forCompany(
            (int) $document->company_id,
            $type,
            $document->chosenTemplateId(),
        );

        return [
            'company' => $document->company,
            'client' => $document->counterparty(),
            'template' => $template,
            'currency' => Currency::query()
                ->where('code', $document->currency_code)
                ->first(),
            'invoice' => $document,
            'items' => $document->printableItems(),
            'settings' => $this->settings->normalizedSettings($template),
            'documentType' => $type,
            'mode' => $mode,
        ];
    }

    public function html(RenderableDocument&Model $document, string $mode = 'preview'): string
    {
        return View::make(self::TEMPLATE_VIEW, $this->viewData($document, $mode))->render();
    }

    /**
     * Raw PDF bytes, rendered in `pdf` mode so the on-screen toolbar is omitted.
     */
    public function pdf(RenderableDocument&Model $document): string
    {
        return Pdf::loadHTML($this->html($document, 'pdf'))
            ->setPaper('a4')
            ->output();
    }

    public function filename(RenderableDocument&Model $document): string
    {
        $reference = $document->number
            ?: $document->documentType()->label().'-'.$document->getKey();

        return preg_replace('/[^A-Za-z0-9_\-]+/', '-', $reference).'.pdf';
    }
}
