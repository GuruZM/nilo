// resources/js/Pages/Invoices/Show.tsx
import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import {
    Download,
    Eye,
    FileText,
    Printer,
    Receipt,
    RefreshCw,
    Trash2,
    Truck,
    Wallet,
} from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

import ConfettiBurst from '@/components/confetti-burst';
import {
    fieldInputClass,
    FormField,
    Panel,
    PanelHeader,
    PillButton,
    SoftTile,
    StatusPill,
    TotalRow,
} from '@/components/dashboard/primitives';
import { Money } from '@/components/money';
import SendDocumentButton from '@/components/send-document-button';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types/index.d';

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

/** One receipt in the invoice's ledger. */
type InvoicePaymentRow = {
    id: number;
    receipt_number: string | null;
    amount: number;
    paid_on: string | null;
    method: string;
    method_label: string;
    reference?: string | null;
    recorded_by?: string | null;
};

type PaymentMethod = { value: string; label: string };

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

    amount_paid: number;
    balance_due: number;
    payments: InvoicePaymentRow[];

    notes?: string | null;
    terms?: string | null;

    client?: {
        name: string;
        email?: string | null;
        contact_person?: string | null;
    };
    items: InvoiceItem[];

    /** Set only when this invoice was raised from a quotation. */
    quotation?: { id: number; number: string | null } | null;
};

export default function InvoiceShow({
    invoice,
    paymentMethods,
    justCreated = false,
}: {
    invoice: Invoice;
    paymentMethods: PaymentMethod[];
    justCreated?: boolean;
}) {
    const page = usePage<SharedData>();

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

    const printReceipt = (paymentId: number) => {
        window.open(
            `/invoices/${invoice.id}/payments/${paymentId}/print`,
            '_blank',
            'noopener,noreferrer',
        );
    };

    /**
     * Removing money is not undoable and rewrites the invoice status, so it
     * asks first. `back()` on the server re-renders this page, which is what
     * refreshes the ledger and the balance beneath the scroll position.
     */
    const removePayment = (payment: InvoicePaymentRow) => {
        const label = payment.receipt_number ?? 'this payment';

        if (!window.confirm(`Remove ${label}? This cannot be undone.`)) {
            return;
        }

        router.delete(`/invoices/${invoice.id}/payments/${payment.id}`, {
            preserveScroll: true,
        });
    };

    /**
     * Generates a delivery note from this invoice and lands on it.
     *
     * Nothing on the server dedupes: a second POST makes a second note, and the
     * invoice payload does not say whether one already exists, so the page
     * cannot tell. Latching on the request itself at least stops the impatient
     * double-click, which is the way duplicates actually get made.
     */
    const [generatingDeliveryNote, setGeneratingDeliveryNote] =
        React.useState(false);

    const generateDeliveryNote = () => {
        if (generatingDeliveryNote) {
            return;
        }

        router.post(
            `/invoices/${invoice.id}/delivery-note`,
            {},
            {
                preserveScroll: true,
                onStart: () => setGeneratingDeliveryNote(true),
                onFinish: () => setGeneratingDeliveryNote(false),
                onError: () =>
                    toast.error('Failed to generate a delivery note.'),
            },
        );
    };

    /** Today in the browser's own timezone, not UTC. */
    const today = React.useMemo(() => {
        const now = new Date();

        return new Date(now.getTime() - now.getTimezoneOffset() * 60000)
            .toISOString()
            .slice(0, 10);
    }, []);

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

                        <SendDocumentButton
                            url={`/invoices/${invoice.id}/send`}
                            documentLabel={
                                invoice.number ?? `Invoice #${invoice.id}`
                            }
                            recipientName={invoice.client?.name}
                            recipientEmail={invoice.client?.email}
                        />

                        <PillButton
                            variant="ghost"
                            size="sm"
                            onClick={generateDeliveryNote}
                            disabled={generatingDeliveryNote}
                        >
                            <Truck className="size-4" />
                            {generatingDeliveryNote
                                ? 'Generating…'
                                : 'Delivery note'}
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

                            {/* Only on an invoice that was billed from a quote. */}
                            {invoice.quotation && (
                                <Info
                                    label="From quotation"
                                    value={
                                        <Link
                                            href={`/quotations/${invoice.quotation.id}`}
                                            className="underline underline-offset-4 hover:no-underline"
                                        >
                                            {invoice.quotation.number ??
                                                `Quotation #${invoice.quotation.id}`}
                                        </Link>
                                    }
                                />
                            )}
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

                    <Panel className="lg:col-span-4 lg:col-start-9">
                        <PanelHeader
                            icon={Wallet}
                            title="Payments"
                            subtitle={
                                invoice.payments.length === 1
                                    ? '1 receipt issued'
                                    : `${invoice.payments.length} receipts issued`
                            }
                        />

                        <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <SoftTile>
                                <div className="text-xs text-muted-foreground">
                                    Paid to date
                                </div>
                                <div className="mt-1 text-sm font-semibold tabular-nums">
                                    {money(invoice.amount_paid)}
                                </div>
                            </SoftTile>
                            <SoftTile>
                                <div className="text-xs text-muted-foreground">
                                    Balance due
                                </div>
                                <div className="mt-1 text-sm font-semibold tabular-nums">
                                    {money(invoice.balance_due)}
                                </div>
                            </SoftTile>
                        </div>

                        {invoice.payments.length === 0 ? (
                            <p className="mt-3 text-sm text-muted-foreground">
                                No payments recorded yet.
                            </p>
                        ) : (
                            <ul className="mt-3 space-y-2">
                                {invoice.payments.map((payment) => (
                                    <li key={payment.id}>
                                        <SoftTile>
                                            <div className="flex items-start justify-between gap-3">
                                                <div className="min-w-0">
                                                    {/*
                                                        Through to the receipt
                                                        register, which is where
                                                        this receipt sits
                                                        alongside every other one
                                                        the company has issued.
                                                    */}
                                                    <Link
                                                        href={`/receipts/${payment.id}`}
                                                        className="truncate text-sm font-semibold hover:underline"
                                                    >
                                                        {payment.receipt_number ??
                                                            'Receipt'}
                                                    </Link>
                                                    <div className="mt-0.5 truncate text-xs text-muted-foreground">
                                                        {[
                                                            payment.paid_on,
                                                            payment.method_label,
                                                            payment.reference,
                                                        ]
                                                            .filter(Boolean)
                                                            .join(' · ')}
                                                    </div>
                                                    {payment.recorded_by ? (
                                                        <div className="mt-0.5 truncate text-xs text-muted-foreground">
                                                            Recorded by{' '}
                                                            {
                                                                payment.recorded_by
                                                            }
                                                        </div>
                                                    ) : null}
                                                </div>

                                                <div className="shrink-0 text-sm font-semibold tabular-nums">
                                                    {money(payment.amount)}
                                                </div>
                                            </div>

                                            <div className="mt-2 flex flex-wrap items-center gap-2">
                                                <PillButton
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        printReceipt(payment.id)
                                                    }
                                                >
                                                    <Receipt className="h-4 w-4" />
                                                    Receipt
                                                </PillButton>

                                                <PillButton
                                                    variant="ghost"
                                                    size="sm"
                                                    className="text-destructive hover:bg-destructive/10"
                                                    onClick={() =>
                                                        removePayment(payment)
                                                    }
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                    Remove
                                                </PillButton>
                                            </div>
                                        </SoftTile>
                                    </li>
                                ))}
                            </ul>
                        )}

                        {/* A settled invoice takes no more money, so the form goes. */}
                        {invoice.balance_due > 0 ? (
                            <Form
                                action={`/invoices/${invoice.id}/payments`}
                                method="post"
                                resetOnSuccess
                                options={{ preserveScroll: true }}
                                className="mt-4 space-y-3"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <FormField
                                            label="Amount"
                                            htmlFor="payment_amount"
                                            required
                                        >
                                            <input
                                                /* Re-seeds with what is left after each payment. */
                                                key={invoice.balance_due}
                                                id="payment_amount"
                                                name="amount"
                                                type="number"
                                                step="0.01"
                                                min="0.01"
                                                max={invoice.balance_due}
                                                defaultValue={
                                                    invoice.balance_due
                                                }
                                                className={fieldInputClass}
                                            />
                                            {errors.amount ? (
                                                <p className="mt-1 text-sm text-destructive">
                                                    {errors.amount}
                                                </p>
                                            ) : null}
                                        </FormField>

                                        <FormField
                                            label="Paid on"
                                            htmlFor="payment_paid_on"
                                            required
                                        >
                                            <input
                                                id="payment_paid_on"
                                                name="paid_on"
                                                type="date"
                                                defaultValue={today}
                                                className={fieldInputClass}
                                            />
                                            {errors.paid_on ? (
                                                <p className="mt-1 text-sm text-destructive">
                                                    {errors.paid_on}
                                                </p>
                                            ) : null}
                                        </FormField>

                                        <FormField
                                            label="Method"
                                            htmlFor="payment_method"
                                            required
                                        >
                                            <select
                                                id="payment_method"
                                                name="method"
                                                defaultValue={
                                                    paymentMethods[0]?.value
                                                }
                                                className={fieldInputClass}
                                            >
                                                {paymentMethods.map(
                                                    (method) => (
                                                        <option
                                                            key={method.value}
                                                            value={method.value}
                                                        >
                                                            {method.label}
                                                        </option>
                                                    ),
                                                )}
                                            </select>
                                            {errors.method ? (
                                                <p className="mt-1 text-sm text-destructive">
                                                    {errors.method}
                                                </p>
                                            ) : null}
                                        </FormField>

                                        <FormField
                                            label="Reference"
                                            htmlFor="payment_reference"
                                        >
                                            <input
                                                id="payment_reference"
                                                name="reference"
                                                type="text"
                                                className={fieldInputClass}
                                            />
                                        </FormField>

                                        <PillButton
                                            type="submit"
                                            variant="solid"
                                            size="sm"
                                            disabled={processing}
                                            className="w-full"
                                        >
                                            <Wallet className="h-4 w-4" />
                                            {processing
                                                ? 'Recording…'
                                                : 'Record payment'}
                                        </PillButton>
                                    </>
                                )}
                            </Form>
                        ) : null}
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

function Info({ label, value }: { label: string; value: React.ReactNode }) {
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
