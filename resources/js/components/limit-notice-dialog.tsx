import { pillButtonClass } from '@/components/dashboard/primitives';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Link } from '@inertiajs/react';
import { Info } from 'lucide-react';
import * as React from 'react';

export interface LimitNotice {
    title: string;
    message: string;
    action_label: string;
    action_href: string;
}

/**
 * Explains a plan limit that blocked an action.
 *
 * These refusals arrive as a flash rather than validation errors, and a toast
 * was too quiet for something that stops the user's work outright — it was
 * possible to be bounced back with no visible reason at all.
 */
export default function LimitNoticeDialog({
    notice,
}: {
    notice?: LimitNotice | null;
}) {
    const [open, setOpen] = React.useState(false);

    /** Opens on arrival, and again if a later attempt flashes a new notice. */
    React.useEffect(() => {
        if (notice) {
            setOpen(true);
        }
    }, [notice]);

    if (!notice) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogContent className="rounded-2xl sm:max-w-md">
                <DialogHeader>
                    <span className="mb-2 grid h-11 w-11 place-items-center rounded-2xl bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">
                        <Info className="h-6 w-6" />
                    </span>

                    <DialogTitle>{notice.title}</DialogTitle>
                    <DialogDescription>{notice.message}</DialogDescription>
                </DialogHeader>

                <div className="flex flex-col-reverse gap-2 pt-1 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        onClick={() => setOpen(false)}
                        className={pillButtonClass('ghost', 'sm')}
                    >
                        Not now
                    </button>

                    <Link
                        href={notice.action_href}
                        className={pillButtonClass('solid', 'sm')}
                    >
                        {notice.action_label}
                    </Link>
                </div>
            </DialogContent>
        </Dialog>
    );
}
