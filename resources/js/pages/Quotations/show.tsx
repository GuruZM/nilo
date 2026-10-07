import { Head, Link, router, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { Download, Eye, FileSignature, Printer, Receipt } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

import ConfettiBurst from '@/components/confetti-burst';
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
import LimitNoticeDialog, {
    type LimitNotice,
} from '@/components/limit-notice-dialog';
import { Money } from '@/components/money';
import SendDocumentButton from '@/components/send-document-button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types/index.d';

type QuotationItem = {
    id: number;
    description: string;
    unit?: string | null;
    quantity: number;
    unit_price: number;
    discount: number;
    tax: number;
    line_total: number;
};

type Quotation = {
    id: number;
    number: string | null;
    title?: string | null;
    reference?: string | null;
    status: 'draft' | 'sent' | 'accepted' | 'expired' | string;
    issue_date: string;
    valid_until?: string | null;
    currency_code: string;

    subtotal: number;
    discount_total: number;
    quotation_discount?: number;
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
    items: QuotationItem[];
};

/** A quotation moves through these in order, but any of them can be set. */
const STATUSES = ['draft', 'sent', 'accepted', 'expired'] as const;

export default function QuotationShow({
    quotation,
    invoice = null,
    limitNotice = null,
    justCreated = false,
}: {
    quotation: Quotation;
    invoice?: { id: number; number: string | null } | null;
    limitNotice?: LimitNotice | null;
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
        { title: 'Quotations', href: '/quotations' },
        {
            title: quotation.number ?? `Quotation #${quotation.id}`,
            href: `/quotations/${quotation.id}`,
        },
    ];

    const money = (n: number): React.ReactNode => (
        <Money amount={n} code={quotation.currency_code} />
    );

    const openDocument = (path: string) => {
        window.open(
            `/quotations/${quotation.id}/${path}`,
            '_blank',
            'noopener,noreferrer',
        );
    };

    /**
     * Bills this quotation and lands on the invoice, optionally emailing it
     * to the client on the way.
     *
     * The server refuses a second invoice, so the worst a double-click can do
     * is land on the one that already exists — the latch is here to keep the
     * button from looking inert while the first request is in flight.
     */
    const [invoicing, setInvoicing] = React.useState(false);
    const [confirmingInvoice, setConfirmingInvoice] = React.useState(false);
    const [sendToClient, setSendToClient] = React.useState(false);

    /** Only a client with an address on file can be emailed. */
    const clientEmail = quotation.client?.email?.trim() || null;

    const createInvoice = () => {
        if (invoicing) {
            return;
        }

        router.post(
            `/quotations/${quotation.id}/invoice`,
            { send_to_client: !!clientEmail && sendToClient },
            {
                onStart: () => setInvoicing(true),
                onFinish: () => setInvoicing(false),
                onError: () => toast.error('Failed to create the invoice.'),
            },
        );
    };

    const setStatus = (next: string) => {
        if (next === quotation.status) {
            return;
        }

        router.post(
            `/quotations/${quotation.id}/status`,
            { status: next },
            {
                preserveScroll: true,
                preserveState: false,
                onSuccess: () => {
                    toast.success(`Quotation marked as ${next}.`);
                    router.reload({ only: ['quotation', 'flash'] });
                },
                onError: (errors) =>
                    toast.error(
                        errors?.status ||
                            errors?.quotation ||
                            'Failed to update quotation status.',
                    ),
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head
                title={
                    quotation.number
                        ? `Quotation ${quotation.number}`
                        : 'Quotation'
                }
            />

            {/* Celebrates the quotation that was just created, once. */}
            <ConfettiBurst active={justCreated} />

            {/* Explains a plan cap that refused the invoice. */}
            <LimitNoticeDialog notice={limitNotice} />

            <Dialog
                open={confirmingInvoice}
                onOpenChange={(open) =>
                    !invoicing && setConfirmingInvoice(open)
                }
            >
                <DialogContent className="rounded-2xl sm:max-w-md">
                    <DialogHeader>
                        <span className="mb-2 grid h-11 w-11 place-items-center rounded-2xl bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">
                            <Receipt className="h-6 w-6" />
                        </span>

                        <DialogTitle>Create invoice</DialogTitle>
                        <DialogDescription>
                            Bills everything on{' '}
                            {quotation.number ?? 'this quotation'} as a new
                            invoice, dated today.
                        </DialogDescription>
                    </DialogHeader>

                    <SoftTile className="flex items-center justify-between gap-3">
                        <label
                            htmlFor="send-invoice-to-client"
                            className="min-w-0"
                        >
                            <div className="text-sm font-semibold">
                                Email it to the client
                            </div>
                            <div className="truncate text-xs text-muted-foreground">
                                {clientEmail ?? 'No email address on file'}
                            </div>
                        </label>
                        <Switch
                            id="send-invoice-to-client"
                            checked={!!clientEmail && sendToClient}
                            disabled={!clientEmail || invoicing}
                            onCheckedChange={(v) => setSendToClient(!!v)}
                        />
                    </SoftTile>

                    {!clientEmail ? (
                        <p className="text-xs text-muted-foreground">
                            {quotation.client?.name ?? 'This client'} has no
                            email address, so the invoice can only be sent once
                            one is added to the client.
                        </p>
                    ) : null}

                    <div className="flex flex-col-reverse gap-2 pt-1 sm:flex-row sm:justify-end">
                        <PillButton
                            variant="ghost"
                            size="sm"
                            onClick={() => setConfirmingInvoice(false)}
                            disabled={invoicing}
                        >
                            Cancel
                        </PillButton>

                        <PillButton
                            variant="solid"
                            size="sm"
                            onClick={createInvoice}
                            disabled={invoicing}
                        >
                            <Receipt className="h-4 w-4" />
                            {invoicing
                                ? 'Creating…'
                                : clientEmail && sendToClient
                                  ? 'Create and send'
                                  : 'Create invoice'}
                        </PillButton>
                    </div>
                </DialogContent>
            </Dialog>

            <div className="mx-auto w-full py-3">
                {/* Header */}
                <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="flex min-w-0 items-center gap-2">
                        <span className="text-sm text-muted-foreground">
                            Status
                        </span>
                        <StatusPill status={quotation.status} />
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

                        <SendDocumentButton
                            url={`/quotations/${quotation.id}/send`}
                            documentLabel={
                                quotation.number ?? `Quotation #${quotation.id}`
                            }
                            recipientName={quotation.client?.name}
                            recipientEmail={clientEmail}
                        />

                        {/* Billed already, or not yet — never both. */}
                        {invoice ? (
                            <Link
                                href={`/invoices/${invoice.id}`}
                                className={pillButtonClass('solid', 'sm')}
                            >
                                <Receipt className="h-4 w-4" />
                                {invoice.number ?? 'View invoice'}
                            </Link>
                        ) : (
                            <PillButton
                                variant="solid"
                                size="sm"
                                onClick={() => setConfirmingInvoice(true)}
                                disabled={invoicing}
                            >
                                <Receipt className="h-4 w-4" />
                                {invoicing ? 'Creating…' : 'Create invoice'}
                            </PillButton>
                        )}
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
                    <Panel className="lg:col-span-8">
                        <PanelHeader
                            icon={FileSignature}
                            title={
                                quotation.number ?? `Quotation #${quotation.id}`
                            }
                            subtitle={quotation.title ?? undefined}
                        />

                        <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <Info
                                label="Client"
                                value={quotation.client?.name ?? '—'}
                            />
                            <Info
                                label="Issue date"
                                value={quotation.issue_date}
                            />
                            <Info
                                label="Valid until"
                                value={quotation.valid_until ?? '—'}
                            />
                            <Info
                                label="Reference"
                                value={quotation.reference ?? '—'}
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
                                    {quotation.items.map((item, index) => (
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

                        {quotation.notes || quotation.terms ? (
                            <div className="mt-5 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                {quotation.notes ? (
                                    <Block
                                        title="Notes"
                                        value={quotation.notes}
                                    />
                                ) : null}
                                {quotation.terms ? (
                                    <Block
                                        title="Terms"
                                        value={quotation.terms}
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
                                    value={money(
                                        Number(quotation.line_discount ?? 0),
                                    )}
                                />
                                <TotalRow
                                    label="Quotation discount"
                                    value={money(
                                        Number(
                                            quotation.quotation_discount ?? 0,
                                        ),
                                    )}
                                />

                                <div className="h-px bg-border/70 dark:bg-white/10" />

                                <TotalRow
                                    label="Subtotal (excl. tax)"
                                    value={money(quotation.subtotal)}
                                />
                                <TotalRow
                                    label={`Tax (${Number(quotation.tax_percent ?? 0)}%)`}
                                    value={money(quotation.tax_total)}
                                />

                                <div className="h-px bg-border/70 dark:bg-white/10" />

                                <TotalRow
                                    label="Total (incl. tax)"
                                    value={money(quotation.total)}
                                    strong
                                />
                            </SoftTile>
                        </Panel>

                        <Panel>
                            <PanelHeader
                                icon={FileSignature}
                                title="Status"
                                subtitle="Where this quotation stands with the client"
                            />

                            <div className="flex flex-wrap gap-2">
                                {STATUSES.map((value) => (
                                    <Chip
                                        key={value}
                                        active={quotation.status === value}
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
