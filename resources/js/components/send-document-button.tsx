import { router } from '@inertiajs/react';
import { Mail } from 'lucide-react';
import * as React from 'react';

import { PillButton, SoftTile } from '@/components/dashboard/primitives';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

/**
 * Emails a saved document to its client or supplier with the PDF attached.
 *
 * Sending is outward-facing and cannot be taken back, so the button only opens
 * a confirmation naming the address; the page's flash toast reports the result.
 */
export default function SendDocumentButton({
    url,
    documentLabel,
    recipientName,
    recipientEmail,
    recipientKind = 'client',
}: {
    url: string;
    documentLabel: string;
    recipientName?: string | null;
    recipientEmail?: string | null;
    recipientKind?: 'client' | 'supplier';
}) {
    const [confirming, setConfirming] = React.useState(false);
    const [sending, setSending] = React.useState(false);

    const email = recipientEmail?.trim() || null;

    const send = () => {
        router.post(
            url,
            {},
            {
                preserveScroll: true,
                onStart: () => setSending(true),
                onFinish: () => {
                    setSending(false);
                    setConfirming(false);
                },
            },
        );
    };

    if (!email) {
        return (
            <span
                title={`Add an email address to this ${recipientKind} to send it.`}
            >
                <PillButton variant="ghost" size="sm" disabled>
                    <Mail className="h-4 w-4" />
                    Send to {recipientKind}
                </PillButton>
            </span>
        );
    }

    return (
        <>
            <PillButton
                variant="ghost"
                size="sm"
                onClick={() => setConfirming(true)}
                disabled={sending}
            >
                <Mail className="h-4 w-4" />
                {sending ? 'Sending…' : `Send to ${recipientKind}`}
            </PillButton>

            <Dialog
                open={confirming}
                onOpenChange={(open) => !sending && setConfirming(open)}
            >
                <DialogContent className="rounded-2xl sm:max-w-md">
                    <DialogHeader>
                        <span className="mb-2 grid h-11 w-11 place-items-center rounded-2xl bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">
                            <Mail className="h-6 w-6" />
                        </span>

                        <DialogTitle>Send {documentLabel}</DialogTitle>
                        <DialogDescription>
                            Emails {documentLabel} with the PDF attached.
                        </DialogDescription>
                    </DialogHeader>

                    <SoftTile className="min-w-0">
                        <div className="text-sm font-semibold">
                            {recipientName ?? `The ${recipientKind}`}
                        </div>
                        <div className="truncate text-xs text-muted-foreground">
                            {email}
                        </div>
                    </SoftTile>

                    <div className="flex flex-col-reverse gap-2 pt-1 sm:flex-row sm:justify-end">
                        <PillButton
                            variant="ghost"
                            size="sm"
                            onClick={() => setConfirming(false)}
                            disabled={sending}
                        >
                            Cancel
                        </PillButton>

                        <PillButton
                            variant="solid"
                            size="sm"
                            onClick={send}
                            disabled={sending}
                        >
                            <Mail className="h-4 w-4" />
                            {sending ? 'Sending…' : 'Send email'}
                        </PillButton>
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
