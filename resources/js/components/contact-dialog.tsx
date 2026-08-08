import { useForm } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
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

/** The two kinds of party a document can be addressed to. */
export type ContactKind = 'client' | 'supplier';

export type EditableContact = {
    id: number;
    name: string;
    email?: string | null;
    contact_person?: string | null;
};

const endpoints: Record<ContactKind, string> = {
    client: '/clients',
    supplier: '/suppliers',
};

/**
 * The controller flashes the new record's id here. Diffing the refreshed list
 * would be an alternative, but supplier names are not unique per company, so
 * the id is the only reliable way to know which record was just added.
 */
const createdIdKeys: Record<ContactKind, string> = {
    client: 'created_client_id',
    supplier: 'created_supplier_id',
};

type SharedProps = {
    kind: ContactKind;
    /** The document being built, e.g. "invoice" — used in the dialog copy. */
    documentLabel: string;
};

type EditProps = SharedProps & {
    mode: 'edit';
    contact: EditableContact;
    open?: never;
    onOpenChange?: never;
    onCreated?: never;
};

type CreateProps = SharedProps & {
    mode: 'create';
    contact?: never;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onCreated: (id: number) => void;
};

/**
 * Creates or edits the party a document is addressed to, without leaving the
 * half-built document.
 *
 * `preserveState` keeps the caller mounted across the round trip, so the
 * invoice or quotation in progress survives; the controller's `back()`
 * re-renders the page with a fresh `clients` / `suppliers` prop, which is what
 * puts the new record in the picker and re-enables the send switch once an
 * address exists.
 *
 * Edit mode carries its own trigger button and sends only name, contact person
 * and email — the document pages' `clients` prop does not include phone or the
 * tax fields, so an edit form covering them would post blanks and quietly
 * destroy data the user cannot see from here. Create mode is controlled by the
 * caller (it opens from a row inside the picker, which has to close first) and
 * takes a phone number too, since there is nothing to overwrite.
 */
export default function ContactDialog(props: EditProps | CreateProps) {
    const { kind, documentLabel, mode } = props;
    const isCreate = mode === 'create';

    const [uncontrolledOpen, setUncontrolledOpen] = React.useState(false);
    const open = isCreate ? props.open : uncontrolledOpen;
    const setOpen = isCreate ? props.onOpenChange : setUncontrolledOpen;

    const contact = props.contact;

    const form = useForm({
        name: contact?.name ?? '',
        contact_person: contact?.contact_person ?? '',
        email: contact?.email ?? '',
        phone: '',
    });

    /** Re-seed on open, and whenever a different contact is selected. */
    React.useEffect(() => {
        if (!open) {
            return;
        }

        form.clearErrors();
        form.setData({
            name: contact?.name ?? '',
            contact_person: contact?.contact_person ?? '',
            email: contact?.email ?? '',
            phone: '',
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, contact?.id]);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        /**
         * Radix portals this dialog out of the document form in the DOM, but
         * React still propagates events up the component tree — without this,
         * saving the contact also submits the document.
         */
        e.stopPropagation();

        /** Edit mode must not post the phone field it never rendered. */
        form.transform((data) =>
            isCreate
                ? data
                : {
                      name: data.name,
                      contact_person: data.contact_person,
                      email: data.email,
                  },
        );

        const options = {
            preserveScroll: true,
            preserveState: true,
            onError: (errors: Record<string, string>) =>
                toast.error(
                    errors?.email ||
                        errors?.name ||
                        errors?.client ||
                        errors?.company_id ||
                        `Failed to save the ${kind}.`,
                ),
        };

        if (!isCreate) {
            form.put(`${endpoints[kind]}/${contact!.id}`, {
                ...options,
                onSuccess: () => {
                    setOpen(false);
                    toast.success(`${form.data.name} updated.`);
                },
            });

            return;
        }

        const name = form.data.name;

        form.post(endpoints[kind], {
            ...options,
            onSuccess: (page) => {
                const flash = (page.props as { flash?: Record<string, unknown> })
                    .flash;
                const createdId = Number(flash?.[createdIdKeys[kind]] ?? 0);

                if (createdId) {
                    props.onCreated(createdId);
                }

                form.reset();
                setOpen(false);
                toast.success(`${name} added.`);
            },
        });
    };

    const title = isCreate ? `New ${kind}` : `Edit ${kind}`;

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            {!isCreate && (
                <DialogTrigger asChild>
                    <PillButton variant="ghost" size="sm">
                        <Pencil className="h-4 w-4" />
                        Edit {kind}
                    </PillButton>
                </DialogTrigger>
            )}

            <DialogContent className="rounded-2xl sm:max-w-md">
                <DialogHeader>
                    <DialogTitle className="capitalize">{title}</DialogTitle>
                    <DialogDescription>
                        {isCreate
                            ? `Saved to your ${kind} list and selected on this ${documentLabel} straight away.`
                            : `Changes are saved to the ${kind} record and apply to this ${documentLabel} straight away.`}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-4">
                    <FormField
                        label="Name"
                        htmlFor={`${mode}_${kind}_name`}
                        required
                    >
                        <input
                            id={`${mode}_${kind}_name`}
                            className={fieldInputClass}
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            required
                            autoFocus={isCreate}
                        />
                        {form.errors.name && (
                            <p className="mt-1 text-xs text-destructive">
                                {form.errors.name}
                            </p>
                        )}
                    </FormField>

                    <FormField label="Email" htmlFor={`${mode}_${kind}_email`}>
                        <input
                            id={`${mode}_${kind}_email`}
                            type="email"
                            className={fieldInputClass}
                            value={form.data.email}
                            onChange={(e) =>
                                form.setData('email', e.target.value)
                            }
                            placeholder={`${kind}@example.com`}
                            autoFocus={!isCreate}
                        />
                        {form.errors.email && (
                            <p className="mt-1 text-xs text-destructive">
                                {form.errors.email}
                            </p>
                        )}
                    </FormField>

                    <FormField
                        label="Contact person"
                        htmlFor={`${mode}_${kind}_contact`}
                    >
                        <input
                            id={`${mode}_${kind}_contact`}
                            className={fieldInputClass}
                            value={form.data.contact_person}
                            onChange={(e) =>
                                form.setData('contact_person', e.target.value)
                            }
                        />
                    </FormField>

                    {isCreate && (
                        <FormField
                            label="Phone"
                            htmlFor={`${mode}_${kind}_phone`}
                        >
                            <input
                                id={`${mode}_${kind}_phone`}
                                className={fieldInputClass}
                                value={form.data.phone}
                                onChange={(e) =>
                                    form.setData('phone', e.target.value)
                                }
                                placeholder="+260 900 000 000"
                            />
                            {form.errors.phone && (
                                <p className="mt-1 text-xs text-destructive">
                                    {form.errors.phone}
                                </p>
                            )}
                        </FormField>
                    )}

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
                                <span className="capitalize">
                                    {isCreate
                                        ? `Add ${kind}`
                                        : `Save ${kind}`}
                                </span>
                            )}
                        </PillButton>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

/**
 * The row pinned to the bottom of a picker that opens the create dialog. It
 * sits inside Radix's `SelectContent`, which is a listbox — `onMouseDown` has
 * to be swallowed or the Select treats the press as an item selection and
 * clears the current value on the way out.
 */
export function NewContactOption({
    kind,
    onSelect,
}: {
    kind: ContactKind;
    onSelect: () => void;
}) {
    return (
        <div className="mt-1 border-t border-border pt-1">
            <button
                type="button"
                onMouseDown={(e) => e.preventDefault()}
                onClick={onSelect}
                className="flex w-full items-center gap-2 rounded-md px-2 py-2 text-sm font-medium text-brand-600 hover:bg-accent dark:text-brand-300"
            >
                <Plus className="h-4 w-4" />
                <span className="capitalize">New {kind}</span>
            </button>
        </div>
    );
}

/**
 * Shared open/close wiring for a picker with a "New …" row in it.
 *
 * The dialog opens on the next tick so Radix's Select can unmount its portal
 * and hand focus back to the trigger first. Opening both in the same tick makes
 * the two focus traps fight, and the dialog lands with no field focused.
 */
export function useNewContactDialog() {
    const [selectOpen, setSelectOpen] = React.useState(false);
    const [dialogOpen, setDialogOpen] = React.useState(false);

    const openDialog = React.useCallback(() => {
        setSelectOpen(false);
        window.setTimeout(() => setDialogOpen(true), 0);
    }, []);

    return {
        selectOpen,
        setSelectOpen,
        dialogOpen,
        setDialogOpen,
        openDialog,
    };
}
