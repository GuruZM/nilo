<?php

namespace App\Services\Documents;

use App\Mail\InvoiceToClient;
use App\Models\Invoice;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Emails an invoice to the client it was raised for.
 *
 * Shared by the browser and the API so the two clients deliver the same
 * document and promote the same status. This was a private method on the web
 * controller until the mobile app needed it, which is why `send_to_client` was
 * accepted over the API and then silently ignored.
 *
 * Delivery never throws. By the time it runs the invoice is already saved, so a
 * mail failure has to be reported rather than allowed to lose the work — the
 * two entry points differ only in how loudly they say so.
 */
class SendInvoiceToClient
{
    /**
     * Send an invoice that already exists, because someone asked for it.
     *
     * Unlike the create flow, having nowhere to send it is a real failure here:
     * the request cannot be honoured at all.
     *
     * @return array{sent: bool, message: string}
     */
    public function handle(Invoice $invoice): array
    {
        $invoice->loadMissing(['client', 'company']);

        $email = $this->recipient($invoice);

        if ($email === null) {
            return [
                'sent' => false,
                'message' => 'This client has no email address, so there is nowhere to send the invoice.',
            ];
        }

        if (! $this->deliver($invoice, $email)) {
            return [
                'sent' => false,
                'message' => 'The email could not be sent. Please try again.',
            ];
        }

        return ['sent' => true, 'message' => 'Invoice queued to '.$email.'.'];
    }

    /**
     * The web app's create flow, where not sending is the ordinary case and the
     * result becomes a flash message on the redirect.
     *
     * @return array{level: string, message: string}
     */
    public function afterCreate(Invoice $invoice, bool $requested): array
    {
        if (! $requested) {
            return ['level' => 'success', 'message' => 'Invoice created.'];
        }

        $invoice->loadMissing(['client', 'company']);

        $email = $this->recipient($invoice);

        if ($email === null) {
            return [
                'level' => 'info',
                'message' => 'Invoice created, but it was not emailed because the client has no email address.',
            ];
        }

        if (! $this->deliver($invoice, $email)) {
            return [
                'level' => 'info',
                'message' => 'Invoice created, but the email could not be sent. You can send it again from the invoice.',
            ];
        }

        return ['level' => 'success', 'message' => 'Invoice created and queued to '.$email.'.'];
    }

    private function recipient(Invoice $invoice): ?string
    {
        $email = trim((string) $invoice->client?->email);

        return $email === '' ? null : $email;
    }

    private function deliver(Invoice $invoice, string $email): bool
    {
        try {
            Mail::to($email)->send(new InvoiceToClient($invoice));
        } catch (\Throwable $e) {
            Log::error('Invoice email failed', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        /** An emailed invoice is no longer merely pending. */
        if ($invoice->status === 'pending') {
            $invoice->update(['status' => 'sent']);
        }

        return true;
    }
}
