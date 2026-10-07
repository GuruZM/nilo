import { Head, router, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import {
    ClipboardList,
    Download,
    Eye,
    Factory,
    Printer,
    Receipt,
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
} from '@/components/dashboard/primitives';
import { Money } from '@/components/money';
import SendDocumentButton from '@/components/send-document-button';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types/index.d';

type PurchaseOrderItem = {
    id: number;
    description: string;
    unit?: string | null;
    quantity: number;
    unit_price: number;
    discount: number;
    tax: number;
    line_total: number;
};

type PurchaseOrder = {
    id: number;
    number: string | null;
    title?: string | null;
    reference?: string | null;
    status: 'draft' | 'sent' | 'approved' | 'received' | 'cancelled' | string;
    issue_date: string;

    /** Optional: an order can be placed before a delivery date is agreed. */
    expected_date?: string | null;

    currency_code: string;
    delivery_address?: string | null;

    subtotal: number;
    discount_total: number;
    purchase_order_discount?: number;
    line_discount?: number;
    tax_percent?: number;
    tax_total: number;
    total: number;

    notes?: string | null;
    terms?: string | null;

    supplier?: {
        name: string;
        email?: string | null;
        contact_person?: string | null;
        address?: string | null;
    } | null;

    items: PurchaseOrderItem[];
};

/**
 * A purchase order points away from the customer, so nothing here links back to
 * an invoice — it has no parent document to return to.
 */
export default function PurchaseOrderShow({
    purchaseOrder,
    statuses,
}: {
    purchaseOrder: PurchaseOrder;
    statuses: string[];
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

    const label = purchaseOrder.number ?? `Purchase order #${purchaseOrder.id}`;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Purchase orders', href: '/purchase-orders' },
        { title: label, href: `/purchase-orders/${purchaseOrder.id}` },
    ];

    const money = (n: number): React.ReactNode => (
        <Money amount={n} code={purchaseOrder.currency_code} />
    );

    const openDocument = (path: string) => {
        window.open(
            `/purchase-orders/${purchaseOrder.id}/${path}`,
            '_blank',
            'noopener,noreferrer',
        );
    };

    const setStatus = (next: string) => {
        if (next === purchaseOrder.status) {
            return;
        }

        router.post(
            `/purchase-orders/${purchaseOrder.id}/status`,
            { status: next },
            {
                preserveScroll: true,
                preserveState: false,
                onSuccess: () => {
                    toast.success(`Purchase order marked as ${next}.`);
                    router.reload({ only: ['purchaseOrder', 'flash'] });
                },
                onError: (errors) =>
                    toast.error(
                        errors?.status ||
                            errors?.purchaseOrder ||
                            'Failed to update purchase order status.',
                    ),
            },
        );
    };

    /**
     * The server splits `discount_total` for us, so the two halves cannot
     * disagree. The subtraction stays only as a fallback for a payload that
     * predates `line_discount`.
     */
    const lineDiscount =
        purchaseOrder.line_discount ??
        Math.max(
            0,
            Number(purchaseOrder.discount_total ?? 0) -
                Number(purchaseOrder.purchase_order_discount ?? 0),
        );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head
                title={
                    purchaseOrder.number
                        ? `Purchase order ${purchaseOrder.number}`
                        : 'Purchase order'
                }
            />

            <div className="mx-auto w-full py-3">
                {/* Header */}
                <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-2">
                        <span className="flex items-center gap-2">
                            <span className="text-sm text-muted-foreground">
                                Status
                            </span>
                            <StatusPill status={purchaseOrder.status} />
                        </span>

                        {/* Who the order is placed with, said in the header. */}
                        <span className="flex min-w-0 items-center gap-1.5 text-sm">
                            <Factory className="h-4 w-4 shrink-0 text-muted-foreground" />
                            <span className="truncate font-medium">
                                {purchaseOrder.supplier?.name ?? 'No supplier'}
                            </span>
                        </span>
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
                            url={`/purchase-orders/${purchaseOrder.id}/send`}
                            documentLabel={label}
                            recipientName={purchaseOrder.supplier?.name}
                            recipientEmail={purchaseOrder.supplier?.email}
                            recipientKind="supplier"
                        />
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
                    <Panel className="lg:col-span-8">
                        <PanelHeader
                            icon={ClipboardList}
                            title={label}
                            subtitle={purchaseOrder.title ?? undefined}
                        />

                        <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <Info
                                label="Supplier"
                                value={purchaseOrder.supplier?.name ?? '—'}
                            />
                            <Info
                                label="Issue date"
                                value={purchaseOrder.issue_date}
                            />
                            <Info
                                label="Expected"
                                value={
                                    purchaseOrder.expected_date ?? 'Not agreed'
                                }
                            />
                            <Info
                                label="Reference"
                                value={purchaseOrder.reference ?? '—'}
                            />
                            <Info
                                label="Deliver to"
                                value={purchaseOrder.delivery_address ?? '—'}
                            />
                            <Info
                                label="Currency"
                                value={purchaseOrder.currency_code}
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
                                    {purchaseOrder.items.map((item, index) => (
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

                        {purchaseOrder.notes || purchaseOrder.terms ? (
                            <div className="mt-5 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                {purchaseOrder.notes ? (
                                    <Block
                                        title="Notes"
                                        value={purchaseOrder.notes}
                                    />
                                ) : null}
                                {purchaseOrder.terms ? (
                                    <Block
                                        title="Terms"
                                        value={purchaseOrder.terms}
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
                                    label="Purchase order discount"
                                    value={money(
                                        Number(
                                            purchaseOrder.purchase_order_discount ??
                                                0,
                                        ),
                                    )}
                                />

                                <div className="h-px bg-border/70 dark:bg-white/10" />

                                <TotalRow
                                    label="Subtotal (excl. tax)"
                                    value={money(purchaseOrder.subtotal)}
                                />
                                <TotalRow
                                    label={`Tax (${Number(purchaseOrder.tax_percent ?? 0)}%)`}
                                    value={money(purchaseOrder.tax_total)}
                                />

                                <div className="h-px bg-border/70 dark:bg-white/10" />

                                <TotalRow
                                    label="Total (incl. tax)"
                                    value={money(purchaseOrder.total)}
                                    strong
                                />
                            </SoftTile>
                        </Panel>

                        <Panel>
                            <PanelHeader
                                icon={ClipboardList}
                                title="Status"
                                subtitle="Where this order stands with the supplier"
                            />

                            <div className="flex flex-wrap gap-2">
                                {statuses.map((value) => (
                                    <Chip
                                        key={value}
                                        active={purchaseOrder.status === value}
                                        onClick={() => setStatus(value)}
                                    >
                                        {value}
                                    </Chip>
                                ))}
                            </div>
                        </Panel>

                        {purchaseOrder.supplier ? (
                            <Panel>
                                <PanelHeader
                                    icon={Factory}
                                    title="Supplier"
                                    subtitle={purchaseOrder.supplier.name}
                                />

                                <SoftTile className="space-y-2 p-4 text-sm">
                                    {purchaseOrder.supplier.contact_person ? (
                                        <div className="flex justify-between gap-3">
                                            <span className="text-muted-foreground">
                                                Contact
                                            </span>
                                            <span className="truncate font-medium">
                                                {
                                                    purchaseOrder.supplier
                                                        .contact_person
                                                }
                                            </span>
                                        </div>
                                    ) : null}

                                    {purchaseOrder.supplier.email ? (
                                        <div className="flex justify-between gap-3">
                                            <span className="text-muted-foreground">
                                                Email
                                            </span>
                                            <span className="truncate font-medium">
                                                {purchaseOrder.supplier.email}
                                            </span>
                                        </div>
                                    ) : null}

                                    {purchaseOrder.supplier.address ? (
                                        <div className="text-xs whitespace-pre-wrap text-muted-foreground">
                                            {purchaseOrder.supplier.address}
                                        </div>
                                    ) : null}

                                    {!purchaseOrder.supplier.contact_person &&
                                    !purchaseOrder.supplier.email &&
                                    !purchaseOrder.supplier.address ? (
                                        <div className="text-xs text-muted-foreground">
                                            No contact details on file for this
                                            supplier.
                                        </div>
                                    ) : null}
                                </SoftTile>
                            </Panel>
                        ) : null}
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
