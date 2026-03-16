// resources/js/Pages/Invoices/Show.tsx
import { Head, router, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import {
    CheckCircle2,
    Download,
    Eye,
    FileText,
    Printer,
    RefreshCw,
} from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
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

export default function InvoiceShow({ invoice }: { invoice: Invoice }) {
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

    const money = (n: number) =>
        `${invoice.currency_code} ${Number.isFinite(n) ? n.toFixed(2) : '0.00'}`;

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

            <motion.div
                initial={{ opacity: 0, y: 14 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.25, ease: 'easeOut' }}
                className="mx-auto w-full px-4 py-10 sm:px-6"
            >
                <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="flex items-start gap-3">
                        <div className="grid h-11 w-11 place-items-center rounded-2xl bg-muted">
                            <FileText className="h-6 w-6 text-foreground/80" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                                {invoice.number ?? `Invoice #${invoice.id}`}
                            </h1>
                            <div className="mt-1 text-sm text-muted-foreground">
                                Status:{' '}
                                <span
                                    className={cn(
                                        'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold',
                                        invoice.status === 'paid'
                                            ? 'bg-emerald-500/10 text-emerald-600'
                                            : 'bg-amber-500/10 text-amber-600',
                                    )}
                                >
                                    <CheckCircle2 className="h-3.5 w-3.5" />
                                    {invoice.status}
                                </span>
                            </div>
                        </div>
                    </div>

                    {/* ✅ ACTION BUTTONS */}
                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={preview}
                            className="gap-2 rounded-xl"
                        >
                            <Eye className="h-4 w-4" />
                            Preview
                        </Button>

                        <Button
                            type="button"
                            variant="outline"
                            onClick={print}
                            className="gap-2 rounded-xl"
                        >
                            <Printer className="h-4 w-4" />
                            Print
                        </Button>

                        <Button
                            type="button"
                            variant="outline"
                            onClick={downloadPdf}
                            className="gap-2 rounded-xl"
                        >
                            <Download className="h-4 w-4" />
                            Download PDF
                        </Button>

                        <Button
                            type="button"
                            onClick={toggleStatus}
                            className="gap-2 rounded-xl transition-transform hover:scale-[1.02] active:scale-[0.98]"
                        >
                            <RefreshCw className="h-4 w-4" />
                            {invoice.status === 'paid'
                                ? 'Mark as pending'
                                : 'Mark as paid'}
                        </Button>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-12">
                    <Card className="rounded-2xl border bg-background p-6 shadow-sm lg:col-span-8">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
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

                        <Separator className="my-6" />

                        <div className="rounded-2xl border">
                            <div className="grid grid-cols-12 gap-2 border-b bg-muted/40 px-4 py-3 text-xs font-semibold text-muted-foreground">
                                <div className="col-span-6">Description</div>
                                <div className="col-span-2 text-right">Qty</div>
                                <div className="col-span-2 text-right">
                                    Price
                                </div>
                                <div className="col-span-2 text-right">
                                    Line
                                </div>
                            </div>

                            <div className="divide-y">
                                {invoice.items.map((it) => (
                                    <div
                                        key={it.id}
                                        className="grid grid-cols-12 gap-2 px-4 py-3 text-sm"
                                    >
                                        <div className="col-span-6 min-w-0">
                                            <div className="truncate font-medium">
                                                {it.description}
                                            </div>
                                            {it.unit ? (
                                                <div className="text-xs text-muted-foreground">
                                                    Unit: {it.unit}
                                                </div>
                                            ) : null}
                                        </div>
                                        <div className="col-span-2 text-right">
                                            {Number(it.quantity).toFixed(2)}
                                        </div>
                                        <div className="col-span-2 text-right">
                                            {money(Number(it.unit_price))}
                                        </div>
                                        <div className="col-span-2 text-right font-semibold">
                                            {money(Number(it.line_total))}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        {(invoice.notes || invoice.terms) && (
                            <>
                                <Separator className="my-6" />
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
                            </>
                        )}
                    </Card>

                    <Card className="rounded-2xl border bg-background p-6 shadow-sm lg:col-span-4">
                        <div className="mb-3 text-sm font-semibold">Totals</div>

                        <div className="space-y-2 rounded-2xl border bg-muted/20 p-4">
                            <TotalRow
                                label="Subtotal"
                                value={money(invoice.subtotal)}
                            />
                            <TotalRow
                                label="Line discount"
                                value={money(invoice.discount_total)}
                            />
                            <TotalRow
                                label="Invoice discount"
                                value={money(
                                    Number(invoice.invoice_discount ?? 0),
                                )}
                            />
                            <TotalRow
                                label="Tax"
                                value={money(invoice.tax_total)}
                            />
                            <Separator className="my-2" />
                            <TotalRow
                                label="Total"
                                value={money(invoice.total)}
                                strong
                            />
                        </div>
                    </Card>
                </div>
            </motion.div>
        </AppLayout>
    );
}

function Info({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-2xl border p-4">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-1 text-sm font-semibold">{value}</div>
        </div>
    );
}

function TotalRow({
    label,
    value,
    strong,
}: {
    label: string;
    value: string;
    strong?: boolean;
}) {
    return (
        <div className="flex items-center justify-between">
            <span className="text-xs text-muted-foreground">{label}</span>
            <span
                className={cn('text-sm', strong && 'text-base font-semibold')}
            >
                {value}
            </span>
        </div>
    );
}

function Block({ title, value }: { title: string; value: string }) {
    return (
        <div className="mt-4 rounded-2xl border p-4">
            <div className="text-xs font-semibold text-muted-foreground">
                {title}
            </div>
            <div className="mt-2 text-sm whitespace-pre-wrap">{value}</div>
        </div>
    );
}
