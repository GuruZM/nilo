import { useForm } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

import {
    FormField,
    PillButton,
    fieldInputClass,
} from '@/components/dashboard/primitives';
import NiloSpinner from '@/components/nilo-spinner';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

export type EditableClient = {
    id: number;
    name: string;
    email?: string | null;
    contact_person?: string | null;
};

/**
 * Edits the selected client without leaving the half-built document.
 *
 * `preserveState` keeps the caller mounted across the round trip, so the
 * invoice or quotation in progress survives; the controller's `back()`
 * re-renders the page with a fresh `clients` prop, which is what re-enables the
 * send switch once an address exists. Only the fields sent here are written, so
 * the client's other details are left alone.
 */
export default function EditClientDialog({
    client,
    documentLabel,
}: {
    client: EditableClient;
    documentLabel: string;
}) {
    const [open, setOpen] = React.useState(false);

    const form = useForm({
        name: client.name ?? '',
        contact_person: client.contact_person ?? '',
        email: client.email ?? '',
    });

    /** Re-seed on open, and whenever a different client is selected. */
    React.useEffect(() => {
        if (!open) {
            return;
        }

        form.clearErrors();
        form.setData({
            name: client.name ?? '',
            contact_person: client.contact_person ?? '',
            email: client.email ?? '',
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, client.id]);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        /**
         * Radix portals this dialog out of the document form in the DOM, but
         * React still propagates events up the component tree — without this,
         * saving the client also submits the document.
         */
        e.stopPropagation();

        form.put(`/clients/${client.id}`, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                setOpen(false);
                toast.success(`${form.data.name} updated.`);
            },
            onError: (errors) =>
                toast.error(
                    errors?.email ||
                        errors?.name ||
                        errors?.client ||
                        'Failed to update the client.',
                ),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <PillButton variant="ghost" size="sm">
                    <Pencil className="h-4 w-4" />
                    Edit client
                </PillButton>
            </DialogTrigger>

            <DialogContent className="rounded-2xl sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Edit client</DialogTitle>
                    <DialogDescription>
                        Changes are saved to the client record and apply to this{' '}
                        {documentLabel} straight away.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-4">
                    <FormField label="Name" htmlFor="edit_client_name" required>
                        <input
                            id="edit_client_name"
                            className={fieldInputClass}
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            required
                        />
                        {form.errors.name && (
                            <p className="mt-1 text-xs text-destructive">
                                {form.errors.name}
                            </p>
                        )}
                    </FormField>

                    <FormField label="Email" htmlFor="edit_client_email">
                        <input
                            id="edit_client_email"
                            type="email"
                            className={fieldInputClass}
                            value={form.data.email}
                            onChange={(e) =>
                                form.setData('email', e.target.value)
                            }
                            placeholder="client@example.com"
                            autoFocus
                        />
                        {form.errors.email && (
                            <p className="mt-1 text-xs text-destructive">
                                {form.errors.email}
                            </p>
                        )}
                    </FormField>

                    <FormField
                        label="Contact person"
                        htmlFor="edit_client_contact"
                    >
                        <input
                            id="edit_client_contact"
                            className={fieldInputClass}
                            value={form.data.contact_person}
                            onChange={(e) =>
                                form.setData('contact_person', e.target.value)
                            }
                        />
                    </FormField>

                    <div className="flex items-center justify-end gap-2 pt-1">
                        <PillButton
                            variant="ghost"
                            size="sm"
                            onClick={() => setOpen(false)}
                            disabled={form.processing}
                        >
                            Cancel
                        </PillButton>
                        <PillButton
                            type="submit"
                            size="sm"
                            disabled={form.processing}
                        >
                            {form.processing ? (
                                <>
                                    <NiloSpinner size={16} />
                                    Saving…
                                </>
                            ) : (
                                'Save client'
                            )}
                        </PillButton>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
