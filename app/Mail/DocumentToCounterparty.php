<?php

namespace App\Mail;

use App\Contracts\RenderableDocument;
use App\Enums\DocumentType;
use App\Services\CompanyDocumentBranding;
use App\Services\DocumentRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Delivers a receipt, credit note, delivery note or purchase order to the
 * client or supplier it is addressed to, with the rendered document attached
 * as a PDF. Invoices and quotations keep their own mailables.
 */
class DocumentToCounterparty extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public RenderableDocument&Model $document) {}

    public function envelope(): Envelope
    {
        $this->document->loadMissing('company.owner');

        $company = $this->document->company;
        $companyName = app(CompanyDocumentBranding::class)->displayName($company);
        $companyEmail = trim((string) $company?->email);

        return new Envelope(
            /**
             * The recipient sees the company, but the platform address stays as
             * the envelope sender so SPF and DKIM keep aligning.
             */
            from: new Address(
                config('mail.from.address'),
                $companyName ?? $this->documentLabel(),
            ),
            subject: $companyName
                ? $this->documentLabel().' from '.$companyName
                : $this->documentLabel(),
            replyTo: $companyEmail !== ''
                ? [new Address($companyEmail, $companyName ?? $companyEmail)]
                : [],
        );
    }

    public function content(): Content
    {
        $this->document->loadMissing('company.owner');

        $type = $this->document->documentType();
        $recipient = $this->document->counterparty();
        $secondDateField = $type->secondDateField();

        return new Content(
            view: 'emails.invoice',
            with: [
                ...app(CompanyDocumentBranding::class)->forCompany($this->document->company),
                'documentLabel' => $this->documentLabel(),
                'documentNoun' => $type->label(),
                'amountLabel' => $this->amountLabel($type),
                'secondDateLabel' => $type->secondDateLabel(),
                'recipientName' => $recipient?->contact_person
                    ?: ($recipient?->name ?? 'there'),
                'totalFormatted' => $type->showsPrices()
                    ? $this->document->currency_code.' '.number_format((float) $this->document->total, 2)
                    : null,
                'issueDate' => $this->formatDate($this->document->issue_date) ?? '—',
                'dueDate' => $secondDateField === null
                    ? null
                    : $this->formatDate($this->document->{$secondDateField}),
                'notes' => $this->document->notes,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $renderer = app(DocumentRenderer::class);

        return [
            Attachment::fromData(
                fn (): string => $renderer->pdf($this->document),
                $renderer->filename($this->document),
            )->withMime('application/pdf'),
        ];
    }

    private function documentLabel(): string
    {
        $label = ucfirst($this->document->documentType()->label());

        return $this->document->number
            ? $label.' '.$this->document->number
            : $label.' #'.$this->document->getKey();
    }

    private function amountLabel(DocumentType $type): string
    {
        return match ($type) {
            DocumentType::Receipt => 'Amount received',
            DocumentType::CreditNote => 'Credit total',
            DocumentType::PurchaseOrder => 'Order total',
            default => 'Total',
        };
    }

    private function formatDate(mixed $value): ?string
    {
        return blank($value) ? null : Carbon::make($value)?->format('j M Y');
    }
}
