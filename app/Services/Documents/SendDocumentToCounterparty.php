<?php

namespace App\Services\Documents;

use App\Contracts\RenderableDocument;
use App\Mail\DocumentToCounterparty;
use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Emails a receipt, credit note, delivery note or purchase order to the client
 * or supplier it is addressed to, with the PDF attached.
 *
 * Like {@see SendInvoiceToClient}, delivery never throws: the document is
 * already saved, so a mail failure is reported rather than raised.
 */
class SendDocumentToCounterparty
{
    /**
     * @return array{sent: bool, message: string}
     */
    public function handle(RenderableDocument&Model $document): array
    {
        $type = $document->documentType();
        $recipient = $document->counterparty();
        $email = trim((string) $recipient?->email);

        if ($email === '') {
            return [
                'sent' => false,
                'message' => 'This '.($type->usesSupplier() ? 'supplier' : 'client')
                    .' has no email address, so there is nowhere to send the '.$type->label().'.',
            ];
        }

        try {
            Mail::to($email)->send(new DocumentToCounterparty($document));
        } catch (\Throwable $e) {
            Log::error('Document email failed', [
                'type' => $type->value,
                'document_id' => $document->getKey(),
                'error' => $e->getMessage(),
            ]);

            return [
                'sent' => false,
                'message' => 'The email could not be sent. Please try again.',
            ];
        }

        /**
         * An emailed order has been placed with the supplier. Credit notes and
         * delivery notes keep their status: issuing a credit applies it to the
         * invoice, and a delivery note moves with the goods, not the email.
         */
        if ($document instanceof PurchaseOrder && $document->status === 'draft') {
            $document->update(['status' => 'sent']);
        }

        return ['sent' => true, 'message' => ucfirst($type->label()).' queued to '.$email.'.'];
    }
}
