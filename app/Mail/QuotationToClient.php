<?php

namespace App\Mail;

use App\Models\Quotation;
use App\Services\CompanyDocumentBranding;
use App\Services\QuotationDocumentRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Delivers a finished quotation to the client it was raised for, with the
 * rendered document attached as a PDF.
 */
class QuotationToClient extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Quotation $quotation) {}

    public function envelope(): Envelope
    {
        $this->quotation->loadMissing(['company.owner', 'client']);

        $company = $this->quotation->company;
        $companyName = app(CompanyDocumentBranding::class)->displayName($company);
        $companyEmail = trim((string) $company?->email);

        return new Envelope(
            /**
             * The client sees the company, but the platform address stays as the
             * envelope sender so SPF and DKIM keep aligning.
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
        $this->quotation->loadMissing(['company.owner', 'client']);

        $client = $this->quotation->client;

        return new Content(
            view: 'emails.invoice',
            with: [
                ...app(CompanyDocumentBranding::class)->forCompany($this->quotation->company),
                'documentLabel' => $this->documentLabel(),
                'documentNoun' => 'quotation',
                'amountLabel' => 'Quoted total',
                'secondDateLabel' => 'Valid until',
                'recipientName' => $client?->contact_person
                    ?: ($client?->name ?? 'there'),
                'totalFormatted' => $this->quotation->currency_code.' '
                    .number_format((float) $this->quotation->total, 2),
                'issueDate' => optional($this->quotation->issue_date)->format('j M Y')
                    ?? '—',
                'dueDate' => optional($this->quotation->valid_until)?->format('j M Y'),
                'notes' => $this->quotation->notes,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $renderer = app(QuotationDocumentRenderer::class);

        return [
            Attachment::fromData(
                fn (): string => $renderer->pdf($this->quotation),
                $renderer->filename($this->quotation),
            )->withMime('application/pdf'),
        ];
    }

    private function documentLabel(): string
    {
        return $this->quotation->number
            ? 'Quotation '.$this->quotation->number
            : 'Quotation #'.$this->quotation->id;
    }
}
