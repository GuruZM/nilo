<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Services\CompanyDocumentBranding;
use App\Services\InvoiceDocumentRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Delivers a finished invoice to the client it was raised for, with the
 * rendered document attached as a PDF.
 */
class InvoiceToClient extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Invoice $invoice) {}

    public function envelope(): Envelope
    {
        $this->invoice->loadMissing(['company.owner', 'client']);

        $company = $this->invoice->company;
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
        $this->invoice->loadMissing(['company.owner', 'client']);

        $client = $this->invoice->client;

        return new Content(
            view: 'emails.invoice',
            with: [
                ...app(CompanyDocumentBranding::class)->forCompany($this->invoice->company),
                'documentLabel' => $this->documentLabel(),
                'recipientName' => $client?->contact_person
                    ?: ($client?->name ?? 'there'),
                'totalFormatted' => $this->invoice->currency_code.' '
                    .number_format((float) $this->invoice->total, 2),
                'issueDate' => optional($this->invoice->issue_date)->format('j M Y')
                    ?? '—',
                'dueDate' => optional($this->invoice->due_date)?->format('j M Y'),
                'notes' => $this->invoice->notes,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $renderer = app(InvoiceDocumentRenderer::class);

        return [
            Attachment::fromData(
                fn (): string => $renderer->pdf($this->invoice),
                $renderer->filename($this->invoice),
            )->withMime('application/pdf'),
        ];
    }

    private function documentLabel(): string
    {
        return $this->invoice->number
            ? 'Invoice '.$this->invoice->number
            : 'Invoice #'.$this->invoice->id;
    }
}
