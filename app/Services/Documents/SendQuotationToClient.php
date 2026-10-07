<?php

namespace App\Services\Documents;

use App\Mail\QuotationToClient;
use App\Models\Quotation;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Emails a quotation to the client it was raised for — the quotation
 * counterpart of {@see SendInvoiceToClient}, shared by the create flow and the
 * send action on a saved quotation.
 *
 * Delivery never throws. By the time it runs the quotation is already saved, so
 * a mail failure has to be reported rather than allowed to lose the work.
 */
class SendQuotationToClient
{
    /**
     * Send a quotation that already exists, because someone asked for it.
     *
     * @return array{sent: bool, message: string}
     */
    public function handle(Quotation $quotation): array
    {
        $quotation->loadMissing(['client', 'company']);

        $email = $this->recipient($quotation);

        if ($email === null) {
            return [
                'sent' => false,
                'message' => 'This client has no email address, so there is nowhere to send the quotation.',
            ];
        }

        if (! $this->deliver($quotation, $email)) {
            return [
                'sent' => false,
                'message' => 'The email could not be sent. Please try again.',
            ];
        }

        return ['sent' => true, 'message' => 'Quotation queued to '.$email.'.'];
    }

    /**
     * The create flow, where not sending is the ordinary case and the result
     * becomes a flash message on the redirect.
     *
     * @return array{level: string, message: string}
     */
    public function afterCreate(Quotation $quotation, bool $requested): array
    {
        if (! $requested) {
            return ['level' => 'success', 'message' => 'Quotation created.'];
        }

        $quotation->loadMissing(['client', 'company']);

        $email = $this->recipient($quotation);

        if ($email === null) {
            return [
                'level' => 'info',
                'message' => 'Quotation created, but it was not emailed because the client has no email address.',
            ];
        }

        if (! $this->deliver($quotation, $email)) {
            return [
                'level' => 'info',
                'message' => 'Quotation created, but the email could not be sent. You can send it again from the quotation.',
            ];
        }

        return ['level' => 'success', 'message' => 'Quotation created and queued to '.$email.'.'];
    }

    private function recipient(Quotation $quotation): ?string
    {
        $email = trim((string) $quotation->client?->email);

        return $email === '' ? null : $email;
    }

    private function deliver(Quotation $quotation, string $email): bool
    {
        try {
            Mail::to($email)->send(new QuotationToClient($quotation));
        } catch (\Throwable $e) {
            Log::error('Quotation email failed', [
                'quotation_id' => $quotation->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        /** A quotation that has gone out is no longer a draft. */
        if ($quotation->status === 'draft') {
            $quotation->update(['status' => 'sent']);
        }

        return true;
    }
}
