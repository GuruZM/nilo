import { Head, Link, router, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import {
    Download,
    Eye,
    FileText,
    Printer,
    Receipt,
    ReceiptText,
} from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

import {
    Chip,
    Panel,
    PanelHeader,
    PillButton,
    SoftTile,
    StatusPill,
    TotalRow,
    pillButtonClass,
} from '@/components/dashboard/primitives';
import { Money } from '@/components/money';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types/index.d';

type CreditNoteItem = {
    id: number;
    description: string;
    unit?: string | null;
    quantity: number;
    unit_price: number;
    discount: number;
    tax: number;
    line_total: number;
};

type CreditNote = {
    id: number;
    number: string | null;
    title?: string | null;
    reference?: string | null;
    reason?: string | null;
    status: 'draft' | 'issued' | 'void' | string;
    issue_date: string;
    currency_code: string;

    subtotal: number;
    discount_total: number;
    credit_note_discount?: number;
    tax_percent?: number;
    tax_total: number;
    total: number;

    notes?: string | null;
    terms?: string | null;

    client?: {
        name: string;
        email?: string | null;
        contact_person?: string | null;
    };

    /** The invoice this credit is raised against. */
    invoice?: {
        id: number;
        number: string | null;
        total?: number;
        status?: string;
    } | null;

    items: CreditNoteItem[];
};

/**
 * A credit note is written, then applied, and can be withdrawn. Only `issued`
 * moves money — {@see InvoiceSettlement} counts nothing else against the
 * invoice — so voiding a note hands the balance back.
 */
const STATUSES = ['draft', 'issued', 'void'] as const;

export default function CreditNoteShow({
    creditNote,
}: {
    creditNote: CreditNote;
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
        { title: 'Credit notes', href: '/credit-notes' },
        {
            title: creditNote.number ?? `Credit note #${creditNote.id}`,
            href: `/credit-notes/${creditNote.id}`,
        },
    ];

    const money = (n: number): React.ReactNode => (
        <Money amount={n} code={creditNote.currency_code} />
    );

    /** Only the overall discount is stored separately; the rest came off lines. */
    const lineDiscount = Math.max(
        0,
        Number(creditNote.discount_total ?? 0) -
            Number(creditNote.credit_note_discount ?? 0),
    );

    const openDocument = (path: string) => {
        window.open(
            `/credit-notes/${creditNote.id}/${path}`,
            '_blank',
            'noopener,noreferrer',
        );
    };

    const setStatus = (next: string) => {
        if (next === creditNote.status) {
            return;
        }

        router.post(
            `/credit-notes/${creditNote.id}/status`,
            { status: next },
            {
                preserveScroll: true,
                preserveState: false,
                onSuccess: () => {
                    toast.success(`Credit note marked as ${next}.`);
                    router.reload({ only: ['creditNote', 'flash'] });
                },
                /**
                 * Issuing is re-guarded server-side against the invoice balance,
                 * so `items` is a real failure here — the credit no longer fits.
                 */
                onError: (errors) =>
                    toast.error(
                        errors?.status ||
                            errors?.items ||
                            errors?.creditNote ||
                            'Failed to update credit note status.',
                    ),
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head
                title={
                    creditNote.number
                        ? `Credit note ${creditNote.number}`
                        : 'Credit note'
                }
            />

            <div className="mx-auto w-full py-3">
                {/* Header */}
                <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="flex min-w-0 items-center gap-2">
                        <span className="text-sm text-muted-foreground">
                            Status
                        </span>
                        <StatusPill status={creditNote.status} />
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
                            icon={ReceiptText}
                            title={
                                creditNote.number ??
                                `Credit note #${creditNote.id}`
                            }
                            subtitle={creditNote.title ?? undefined}
                        />

                        {/* The invoice this credit belongs to, one click away. */}
                        <SoftTile className="mb-2 flex flex-wrap items-center justify-between gap-3">
                            <div className="min-w-0">
                                <div className="text-xs text-muted-foreground">
                                    Credited against
                                </div>
                                <div className="mt-1 truncate text-sm font-semibold">
                                    {creditNote.invoice?.number ??
                                        (creditNote.invoice
                                            ? `Invoice #${creditNote.invoice.id}`
                                            : '—')}
                                </div>
                            </div>

                            {creditNote.invoice ? (
                                <Link
                                    href={`/invoices/${creditNote.invoice.id}`}
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
                                value={creditNote.client?.name ?? '—'}
                            />
                            <Info
                                label="Issue date"
                                value={creditNote.issue_date}
                            />
                            <Info
                                label="Reason"
                                value={creditNote.reason ?? '—'}
                            />
                            <Info
                                label="Reference"
                                value={creditNote.reference ?? '—'}
                            />
                        </div>

                        <div className="-mx-1 mt-5 overflow-x-auto px-1">
                            <table className="w-full min-w-[34rem] border-separate border-spacing-y-1.5 text-sm">
                                <thead>
                                    <tr className="text-left text-xs text-muted-foreground">
                                        <th className="px-3 pb-1 font-medium">
                                            Description
                                        </th>
                                        <th className="px-3 pb-1 text-right font-medium">
                                            Qty
                                        </th>
                                        <th className="px-3 pb-1 text-right font-medium">
                                            Price
                                        </th>
                                        <th className="px-3 pb-1 text-right font-medium">
                                            Line
                                        </th>
                                    </tr>
                                </thead>

                                <tbody>
                                    {creditNote.items.map((item, index) => (
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
                                                    'rounded-l-2xl',
                                                )}
                                            >
                                                <div className="min-w-0">
                                                    <div className="truncate font-medium">
                                                        {item.description}
                                                    </div>
                                                    {item.unit ? (
                                                        <div className="mt-0.5 text-xs text-muted-foreground">
                                                            Unit: {item.unit}
                                                        </div>
                                                    ) : null}
                                                </div>
                                            </td>
                                            <td
                                                className={cn(
                                                    cellClass,
                                                    'text-right tabular-nums',
                                                )}
                                            >
                                                {Number(item.quantity).toFixed(
                                                    2,
                                                )}
                                            </td>
                                            <td
                                                className={cn(
                                                    cellClass,
                                                    'text-right tabular-nums',
                                                )}
                                            >
                                                {money(Number(item.unit_price))}
                                            </td>
                                            <td
                                                className={cn(
                                                    cellClass,
                                                    'rounded-r-2xl text-right font-semibold tabular-nums',
                                                )}
                                            >
                                                {money(Number(item.line_total))}
                                            </td>
                                        </motion.tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {creditNote.notes || creditNote.terms ? (
                            <div className="mt-5 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                {creditNote.notes ? (
                                    <Block
                                        title="Notes"
                                        value={creditNote.notes}
                                    />
                                ) : null}
                                {creditNote.terms ? (
                                    <Block
                                        title="Terms"
                                        value={creditNote.terms}
                                    />
                                ) : null}
                            </div>
                        ) : null}
                    </Panel>

                    <div className="flex flex-col gap-4 lg:col-span-4">
                        <Panel>
                            <PanelHeader icon={Receipt} title="Totals" />

                            {/* Prices are tax-inclusive, so tax is carved out of the total. */}
                            <SoftTile className="space-y-2 p-4">
                                <TotalRow
                                    label="Line discount"
                                    value={money(lineDiscount)}
                                />
                                <TotalRow
                                    label="Credit note discount"
                                    value={money(
                                        Number(
                                            creditNote.credit_note_discount ??
                                                0,
                                        ),
                                    )}
                                />

                                <div className="h-px bg-border/70 dark:bg-white/10" />

                                <TotalRow
                                    label="Subtotal (excl. tax)"
                                    value={money(creditNote.subtotal)}
                                />
                                <TotalRow
                                    label={`Tax (${Number(creditNote.tax_percent ?? 0)}%)`}
                                    value={money(creditNote.tax_total)}
                                />

                                <div className="h-px bg-border/70 dark:bg-white/10" />

                                <TotalRow
                                    label="Total credited"
                                    value={money(creditNote.total)}
                                    strong
                                />
                            </SoftTile>
                        </Panel>

                        <Panel>
                            <PanelHeader
                                icon={ReceiptText}
                                title="Status"
                                subtitle="Only an issued credit reduces the invoice"
                            />

                            <div className="flex flex-wrap gap-2">
                                {STATUSES.map((value) => (
                                    <Chip
                                        key={value}
                                        active={creditNote.status === value}
                                        onClick={() => setStatus(value)}
                                    >
                                        {value}
                                    </Chip>
                                ))}
                            </div>
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
