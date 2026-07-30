// resources/js/Pages/Invoices/Show.tsx
import { Head, router, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import {
    Download,
    Eye,
    FileText,
    Printer,
    Receipt,
    RefreshCw,
} from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

import ConfettiBurst from '@/components/confetti-burst';
import {
    Panel,
    PanelHeader,
    PillButton,
    SoftTile,
    StatusPill,
    TotalRow,
} from '@/components/dashboard/primitives';
import { Money } from '@/components/money';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types/index.d';

type InvoiceItem = {
    id: number;
    description: string;
    unit?: string | null;
    quantity: number;
    unit_price: number;
    discount: number;
    tax: number;
    line_total: number;
};

type Invoice = {
    id: number;
    number: string | null;
    title?: string | null;
    reference?: string | null;
    status: 'pending' | 'paid' | string;
    issue_date: string;
    due_date?: string | null;
    currency_code: string;

    subtotal: number;
    discount_total: number;
    invoice_discount?: number;
    line_discount?: number;
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
    items: InvoiceItem[];
};

export default function InvoiceShow({
    invoice,
    justCreated = false,
}: {
    invoice: Invoice;
    justCreated?: boolean;
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
        { title: 'Invoices', href: '/invoices' },
        {
            title: invoice.number ?? `Invoice #${invoice.id}`,
            href: `/invoices/${invoice.id}`,
        },
    ];

    const money = (n: number): React.ReactNode => (
        <Money amount={n} code={invoice.currency_code} />
    );

    const preview = () => {
        window.open(
            `/invoices/${invoice.id}/preview`,
            '_blank',
            'noopener,noreferrer',
        );
    };

    const print = () => {
        window.open(
            `/invoices/${invoice.id}/print`,
            '_blank',
            'noopener,noreferrer',
        );
    };

    const downloadPdf = () => {
        window.open(
            `/invoices/${invoice.id}/print?download=1`,
            '_blank',
            'noopener,noreferrer',
        );
    };

    const toggleStatus = () => {
        const nextStatus = invoice.status === 'paid' ? 'pending' : 'paid';

        router.post(
            `/invoices/${invoice.id}/status`,
            { status: nextStatus },
            {
                preserveScroll: true,
                preserveState: false,
                onSuccess: () => {
                    toast.success(`Invoice marked as ${nextStatus}.`);
                    // Ensure the updated invoice prop is fetched
                    router.reload({ only: ['invoice', 'flash'] });
                },
                onError: (errors) => {
                    toast.error(
                        errors?.status ||
                            errors?.invoice ||
                            'Failed to update invoice status.',
                    );
                },
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head
                title={invoice.number ? `Invoice ${invoice.number}` : 'Invoice'}
            />

            {/* Celebrates the invoice that was just created, once. */}
            <ConfettiBurst active={justCreated} />

            <div className="mx-auto w-full py-3">
                {/* Header */}
                <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="flex min-w-0 items-center gap-2">
                        <span className="text-sm text-muted-foreground">
                            Status
                        </span>
                        <StatusPill status={invoice.status} />
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <PillButton variant="ghost" size="sm" onClick={preview}>
                            <Eye className="h-4 w-4" />
                            Preview
                        </PillButton>

                        <PillButton variant="ghost" size="sm" onClick={print}>
                            <Printer className="h-4 w-4" />
                            Print
                        </PillButton>

                        <PillButton
                            variant="ghost"
                            size="sm"
                            onClick={downloadPdf}
                        >
                            <Download className="h-4 w-4" />
                            Download PDF
                        </PillButton>

                        <PillButton
                            variant="solid"
                            size="sm"
                            onClick={toggleStatus}
                        >
                            <RefreshCw className="h-4 w-4" />
                            {invoice.status === 'paid'
                                ? 'Mark as pending'
                                : 'Mark as paid'}
                        </PillButton>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
                    <Panel className="lg:col-span-8">
                        <PanelHeader
                            icon={FileText}
                            title={invoice.number ?? `Invoice #${invoice.id}`}
                            subtitle={invoice.title ?? undefined}
                        />

                        <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <Info
                                label="Client"
                                value={invoice.client?.name ?? '—'}
                            />
                            <Info
                                label="Issue date"
                                value={invoice.issue_date}
                            />
                            <Info
                                label="Due date"
                                value={invoice.due_date ?? '—'}
                            />
                            <Info
                                label="Reference"
                                value={invoice.reference ?? '—'}
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
                                    {invoice.items.map((item, index) => (
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

                        {invoice.notes || invoice.terms ? (
                            <div className="mt-5 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                {invoice.notes ? (
                                    <Block
                                        title="Notes"
                                        value={invoice.notes}
                                    />
                                ) : null}
                                {invoice.terms ? (
                                    <Block
                                        title="Terms"
                                        value={invoice.terms}
                                    />
                                ) : null}
                            </div>
                        ) : null}
                    </Panel>

                    <Panel className="lg:col-span-4">
                        <PanelHeader icon={Receipt} title="Totals" />

                        {/* Prices are tax-inclusive, so tax is carved out of the total. */}
                        <SoftTile className="space-y-2 p-4">
                            <TotalRow
                                label="Line discount"
                                value={money(
                                    Number(invoice.line_discount ?? 0),
                                )}
                            />
                            <TotalRow
                                label="Invoice discount"
                                value={money(
                                    Number(invoice.invoice_discount ?? 0),
                                )}
                            />

                            <div className="h-px bg-border/70 dark:bg-white/10" />

                            <TotalRow
                                label="Subtotal (excl. tax)"
                                value={money(invoice.subtotal)}
                            />
                            <TotalRow
                                label={`Tax (${Number(invoice.tax_percent ?? 0)}%)`}
                                value={money(invoice.tax_total)}
                            />

                            <div className="h-px bg-border/70 dark:bg-white/10" />

                            <TotalRow
                                label="Total (incl. tax)"
                                value={money(invoice.total)}
                                strong
                            />
                        </SoftTile>
                    </Panel>
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
