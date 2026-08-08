import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import {
    Boxes,
    Download,
    Eye,
    FileText,
    PenLine,
    Printer,
    Truck,
} from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

import {
    FormField,
    Panel,
    PanelHeader,
    PillButton,
    SoftTile,
    StatusPill,
    fieldInputClass,
    pillButtonClass,
} from '@/components/dashboard/primitives';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types/index.d';

/**
 * A delivery note line carries no price and no line amount — those columns do
 * not exist on the table. What arrived is a fact about goods, not about money,
 * and nothing on this page may imply otherwise.
 */
type DeliveryNoteItem = {
    id: number;
    description: string;
    unit?: string | null;
    quantity: number | string;
};

type DeliveryNote = {
    id: number;
    number: string | null;
    reference?: string | null;
    status: 'draft' | 'dispatched' | 'delivered' | string;

    issue_date?: string | null;
    delivery_date?: string | null;

    deliver_to?: string | null;
    delivery_address?: string | null;

    received_by?: string | null;
    received_on?: string | null;

    notes?: string | null;

    client?: {
        id: number;
        name: string;
        email?: string | null;
        address?: string | null;
        contact_person?: string | null;
    } | null;

    /** The invoice these goods were dispatched against. */
    invoice?: {
        id: number;
        number: string | null;
    } | null;

    items: DeliveryNoteItem[];
};

/**
 * The dates arrive as Carbon-serialised ISO-8601 (`2026-07-05T00:00:00.000000Z`)
 * because the controller hands raw model attributes to Inertia. `<input
 * type="date">` only accepts `YYYY-MM-DD` and renders anything else as blank,
 * so the date part is taken off the front. The cast is `date`, so the clock
 * component is always midnight UTC and there is no day to lose here.
 */
const toDateInput = (value?: string | null): string =>
    value ? String(value).slice(0, 10) : '';

/** Same trim, for read-only display where a bare `YYYY-MM-DD` is the nicest form. */
const fmtDate = (value?: string | null): string => toDateInput(value) || '—';

export default function DeliveryNoteShow({
    deliveryNote,
    statuses,
}: {
    deliveryNote: DeliveryNote;
    statuses: string[];
}) {
    const page = usePage() as any;

    React.useEffect(() => {
        const s = page.props?.flash?.success;
        const e = page.props?.flash?.error;
        const i = page.props?.flash?.info;
        if (s) toast.success(s);
        if (e) toast.error(e);
        if (i) toast.message(i);
    }, [
        page.props?.flash?.success,
        page.props?.flash?.error,
        page.props?.flash?.info,
    ]);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Delivery notes', href: '/delivery-notes' },
        {
            title: deliveryNote.number ?? `Delivery note #${deliveryNote.id}`,
            href: `/delivery-notes/${deliveryNote.id}`,
        },
    ];

    const openDocument = (path: string) => {
        window.open(
            `/delivery-notes/${deliveryNote.id}/${path}`,
            '_blank',
            'noopener,noreferrer',
        );
    };

    /** Today in the browser's own timezone, not UTC. */
    const today = React.useMemo(() => {
        const now = new Date();

        return new Date(now.getTime() - now.getTimezoneOffset() * 60000)
            .toISOString()
            .slice(0, 10);
    }, []);

    const form = useForm({
        status: deliveryNote.status,
        delivery_date: toDateInput(deliveryNote.delivery_date),
        deliver_to: deliveryNote.deliver_to ?? '',
        delivery_address: deliveryNote.delivery_address ?? '',
        received_by: deliveryNote.received_by ?? '',
        received_on: toDateInput(deliveryNote.received_on),
        notes: deliveryNote.notes ?? '',
    });

    /**
     * Signing and status are coupled here, in the form, not on the server.
     *
     * The backend validates the two independently, which means it will happily
     * store "nobody has dispatched this yet, but J. Banda signed for it". That
     * record is not wrong so much as incoherent, and the person typing the name
     * of the signatory is by definition telling you the goods arrived — so
     * filling in a signature advances the status rather than leaving the form
     * to contradict itself. It is a nudge, not a lock: the status select is
     * still there and can be dragged back to `draft` afterwards.
     */
    const signFor = (field: 'received_by' | 'received_on', value: string) => {
        form.setData((current) => {
            const next = { ...current, [field]: value };
            const signed =
                Boolean(next.received_by.trim()) || Boolean(next.received_on);

            if (!signed) {
                return next;
            }

            return {
                ...next,
                /**
                 * A signature dated nothing is not a signature — but only the
                 * name field seeds the date. Seeding it from the date field too
                 * would refill it the instant somebody tried to clear it.
                 */
                received_on:
                    field === 'received_by'
                        ? next.received_on || today
                        : next.received_on,
                delivery_date: next.delivery_date || today,
                status: 'delivered',
            };
        });
    };

    /** Marking it delivered by hand still needs a date against the signature. */
    const chooseStatus = (next: string) => {
        form.setData((current) => ({
            ...current,
            status: next,
            received_on:
                next === 'delivered' && !current.received_on
                    ? today
                    : current.received_on,
            delivery_date:
                next !== 'draft' && !current.delivery_date
                    ? today
                    : current.delivery_date,
        }));
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        form.put(`/delivery-notes/${deliveryNote.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                form.setDefaults();
            },
            onError: (errors) => {
                toast.error(
                    errors?.status ||
                        errors?.delivery_date ||
                        errors?.received_on ||
                        'Failed to update this delivery note.',
                );
            },
        });
    };

    const fieldError = (message?: string) =>
        message ? <p className="text-xs text-destructive">{message}</p> : null;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head
                title={
                    deliveryNote.number
                        ? `Delivery note ${deliveryNote.number}`
                        : 'Delivery note'
                }
            />

            <div className="mx-auto w-full py-3">
                {/* Header */}
                <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="flex min-w-0 items-center gap-2">
                        <span className="text-sm text-muted-foreground">
                            Status
                        </span>
                        <StatusPill status={deliveryNote.status} />
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <PillButton
                            variant="ghost"
                            size="sm"
                            onClick={() => openDocument('preview')}
                        >
                            <Eye className="h-4 w-4" />
                            Preview
                        </PillButton>

                        <PillButton
                            variant="ghost"
                            size="sm"
                            onClick={() => openDocument('print')}
                        >
                            <Printer className="h-4 w-4" />
                            Print
                        </PillButton>

                        <PillButton
                            variant="ghost"
                            size="sm"
                            onClick={() => openDocument('print?download=1')}
                        >
                            <Download className="h-4 w-4" />
                            Download PDF
                        </PillButton>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
                    <Panel className="lg:col-span-8">
                        <PanelHeader
                            icon={Truck}
                            title={
                                deliveryNote.number ??
                                `Delivery note #${deliveryNote.id}`
                            }
                            subtitle={
                                deliveryNote.deliver_to
                                    ? `Deliver to ${deliveryNote.deliver_to}`
                                    : undefined
                            }
                        />

                        {/* The invoice these goods were dispatched against. */}
                        <SoftTile className="mb-2 flex flex-wrap items-center justify-between gap-3">
                            <div className="min-w-0">
                                <div className="text-xs text-muted-foreground">
                                    Dispatched against
                                </div>
                                <div className="mt-1 truncate text-sm font-semibold">
                                    {deliveryNote.invoice?.number ??
                                        (deliveryNote.invoice
                                            ? `Invoice #${deliveryNote.invoice.id}`
                                            : (deliveryNote.reference ?? '—'))}
                                </div>
                            </div>

                            {deliveryNote.invoice ? (
                                <Link
                                    href={`/invoices/${deliveryNote.invoice.id}`}
                                    className={pillButtonClass('soft', 'sm')}
                                >
                                    <FileText className="h-4 w-4" />
                                    View invoice
                                </Link>
                            ) : null}
                        </SoftTile>

                        <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <Info
                                label="Client"
                                value={deliveryNote.client?.name ?? '—'}
                            />
                            <Info
                                label="Issue date"
                                value={fmtDate(deliveryNote.issue_date)}
                            />
                            <Info
                                label="Delivery date"
                                value={fmtDate(deliveryNote.delivery_date)}
                            />
                            <Info
                                label="Received by"
                                value={
                                    deliveryNote.received_by?.trim() ||
                                    'Not signed for'
                                }
                            />
                        </div>

                        {/*
                            Description, unit and quantity — and nothing else.
                            There is no price or line total to show, and an
                            honest-looking 0.00 under a money header would be
                            worse than showing nothing at all.
                        */}
                        <div className="-mx-1 mt-5 overflow-x-auto px-1">
                            <table className="w-full min-w-[30rem] border-separate border-spacing-y-1.5 text-sm">
                                <thead>
                                    <tr className="text-left text-xs text-muted-foreground">
                                        <th className="px-3 pb-1 font-medium">
                                            Description
                                        </th>
                                        <th className="px-3 pb-1 font-medium">
                                            Unit
                                        </th>
                                        <th className="px-3 pb-1 text-right font-medium">
                                            Qty
                                        </th>
                                    </tr>
                                </thead>

                                <tbody>
                                    {deliveryNote.items.map((item, index) => (
                                        <motion.tr
                                            key={item.id}
                                            initial={{ opacity: 0, y: 8 }}
                                            animate={{ opacity: 1, y: 0 }}
                                            transition={{
                                                duration: 0.22,
                                                delay: Math.min(
                                                    0.03 * index,
                                                    0.18,
                                                ),
                                            }}
                                            className="group"
                                        >
                                            <td
                                                className={cn(
                                                    cellClass,
                                                    'rounded-l-2xl font-medium',
                                                )}
                                            >
                                                {item.description}
                                            </td>
                                            <td
                                                className={cn(
                                                    cellClass,
                                                    'text-muted-foreground',
                                                )}
                                            >
                                                {item.unit?.trim() || '—'}
                                            </td>
                                            <td
                                                className={cn(
                                                    cellClass,
                                                    'rounded-r-2xl text-right font-semibold tabular-nums',
                                                )}
                                            >
                                                {Number(item.quantity).toFixed(
                                                    2,
                                                )}
                                            </td>
                                        </motion.tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {deliveryNote.items.length === 0 ? (
                            <p className="mt-3 text-sm text-muted-foreground">
                                This delivery note has no lines on it.
                            </p>
                        ) : null}

                        {deliveryNote.delivery_address ? (
                            <div className="mt-5">
                                <Block
                                    title="Delivery address"
                                    value={deliveryNote.delivery_address}
                                />
                            </div>
                        ) : null}
                    </Panel>

                    <div className="flex flex-col gap-4 lg:col-span-4">
                        <Panel>
                            <PanelHeader
                                icon={PenLine}
                                title="Dispatch and sign-off"
                                subtitle="Where the goods went, and who took them"
                            />

                            <form
                                onSubmit={submit}
                                className="flex flex-col gap-4"
                            >
                                <FormField
                                    label="Status"
                                    htmlFor="delivery_status"
                                    required
                                >
                                    <select
                                        id="delivery_status"
                                        value={form.data.status}
                                        onChange={(e) =>
                                            chooseStatus(e.target.value)
                                        }
                                        className={fieldInputClass}
                                    >
                                        {statuses.map((value) => (
                                            <option key={value} value={value}>
                                                {value}
                                            </option>
                                        ))}
                                    </select>
                                    {fieldError(form.errors.status)}
                                </FormField>

                                <FormField
                                    label="Delivery date"
                                    htmlFor="delivery_date"
                                >
                                    <input
                                        id="delivery_date"
                                        type="date"
                                        value={form.data.delivery_date}
                                        onChange={(e) =>
                                            form.setData(
                                                'delivery_date',
                                                e.target.value,
                                            )
                                        }
                                        className={fieldInputClass}
                                    />
                                    {fieldError(form.errors.delivery_date)}
                                </FormField>

                                <FormField
                                    label="Deliver to"
                                    htmlFor="deliver_to"
                                >
                                    <input
                                        id="deliver_to"
                                        type="text"
                                        value={form.data.deliver_to}
                                        onChange={(e) =>
                                            form.setData(
                                                'deliver_to',
                                                e.target.value,
                                            )
                                        }
                                        className={fieldInputClass}
                                    />
                                    {fieldError(form.errors.deliver_to)}
                                </FormField>

                                <FormField
                                    label="Delivery address"
                                    htmlFor="delivery_address"
                                >
                                    <textarea
                                        id="delivery_address"
                                        rows={3}
                                        value={form.data.delivery_address}
                                        onChange={(e) =>
                                            form.setData(
                                                'delivery_address',
                                                e.target.value,
                                            )
                                        }
                                        className={cn(
                                            fieldInputClass,
                                            'h-auto py-2',
                                        )}
                                    />
                                    {fieldError(form.errors.delivery_address)}
                                </FormField>

                                <FormField
                                    label="Received by"
                                    htmlFor="received_by"
                                >
                                    <input
                                        id="received_by"
                                        type="text"
                                        value={form.data.received_by}
                                        onChange={(e) =>
                                            signFor(
                                                'received_by',
                                                e.target.value,
                                            )
                                        }
                                        className={fieldInputClass}
                                    />
                                    {fieldError(form.errors.received_by)}
                                </FormField>

                                <FormField
                                    label="Received on"
                                    htmlFor="received_on"
                                >
                                    <input
                                        id="received_on"
                                        type="date"
                                        value={form.data.received_on}
                                        onChange={(e) =>
                                            signFor(
                                                'received_on',
                                                e.target.value,
                                            )
                                        }
                                        className={fieldInputClass}
                                    />
                                    {fieldError(form.errors.received_on)}
                                </FormField>

                                {form.data.received_by.trim() &&
                                form.data.status !== 'delivered' ? (
                                    <p className="text-xs text-amber-600 dark:text-amber-400">
                                        Somebody has signed for these goods but
                                        the status is “{form.data.status}”.
                                        Saving it that way will read as a
                                        contradiction on the sheet.
                                    </p>
                                ) : null}

                                <FormField label="Notes" htmlFor="notes">
                                    <textarea
                                        id="notes"
                                        rows={3}
                                        value={form.data.notes}
                                        onChange={(e) =>
                                            form.setData(
                                                'notes',
                                                e.target.value,
                                            )
                                        }
                                        className={cn(
                                            fieldInputClass,
                                            'h-auto py-2',
                                        )}
                                    />
                                    {fieldError(form.errors.notes)}
                                </FormField>

                                <PillButton
                                    type="submit"
                                    variant="solid"
                                    size="sm"
                                    disabled={form.processing}
                                    className="w-full"
                                >
                                    <PenLine className="h-4 w-4" />
                                    {form.processing
                                        ? 'Saving…'
                                        : 'Save delivery note'}
                                </PillButton>
                            </form>
                        </Panel>

                        <Panel>
                            <PanelHeader
                                icon={Boxes}
                                title="Contents"
                                subtitle="What this sheet says arrived"
                            />

                            <SoftTile>
                                <div className="text-xs text-muted-foreground">
                                    Lines on this note
                                </div>
                                <div className="mt-1 text-sm font-semibold">
                                    {deliveryNote.items.length}{' '}
                                    {deliveryNote.items.length === 1
                                        ? 'item'
                                        : 'items'}
                                </div>
                            </SoftTile>
                        </Panel>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

/** Rows read as tinted tiles rather than ruled lines, matching the panels. */
const cellClass = cn(
    'bg-muted/40 px-3 py-3 align-middle transition dark:bg-white/5',
    'group-hover:bg-brand-50/70 dark:group-hover:bg-brand-500/10',
);

function Info({ label, value }: { label: string; value: string }) {
    return (
        <SoftTile>
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-1 truncate text-sm font-semibold">{value}</div>
        </SoftTile>
    );
}

function Block({ title, value }: { title: string; value: string }) {
    return (
        <SoftTile>
            <div className="text-xs font-semibold text-muted-foreground">
                {title}
            </div>
            <div className="mt-2 text-sm whitespace-pre-wrap">{value}</div>
        </SoftTile>
    );
}
