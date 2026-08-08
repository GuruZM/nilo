import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Download,
    Eye,
    FileText,
    HandCoins,
    Printer,
    Receipt,
    Trash2,
    Wallet,
} from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

import {
    Panel,
    PanelHeader,
    PillButton,
    SoftTile,
    pillButtonClass,
} from '@/components/dashboard/primitives';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { formatMoney } from '@/lib/money';
import { type BreadcrumbItem } from '@/types/index.d';

type ReceiptData = {
    id: number;
    number: string | null;
    amount: number;

    /**
     * What the invoice was left owing, frozen when the payment was recorded.
     * Null on a standalone receipt, which settles no invoice — so this page
     * must never render a zero in its place.
     */
    balance_after: number | null;

    currency_code: string;
    paid_on?: string | null;
    method: string;
    method_label: string;
    reference?: string | null;
    description?: string | null;
    recorded_by?: string | null;

    client?: {
        id: number;
        name: string;
        email?: string | null;
        address?: string | null;
        contact_person?: string | null;
    } | null;

    invoice?: {
        id: number;
        number: string | null;
    } | null;
};

const fmtDate = (value?: string | null): string =>
    value ? String(value).slice(0, 10) : '—';

export default function ReceiptShow({ receipt }: { receipt: ReceiptData }) {
    const { flash } = usePage<{
        flash?: {
            success?: string | null;
            error?: string | null;
            info?: string | null;
        };
    }>().props;

    React.useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
        if (flash?.info) toast.message(flash.info);
    }, [flash?.success, flash?.error, flash?.info]);

    const label = receipt.number ?? `Receipt #${receipt.id}`;
    const standalone = !receipt.invoice;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Receipts', href: '/receipts' },
        { title: label, href: `/receipts/${receipt.id}` },
    ];

    const openDocument = (path: string) => {
        window.open(
            `/receipts/${receipt.id}/${path}`,
            '_blank',
            'noopener,noreferrer',
        );
    };

    /**
     * Deleting an invoice-backed receipt puts money back onto the invoice's
     * balance, so the confirmation says so rather than asking a generic
     * "are you sure".
     */
    const remove = () => {
        const consequence = receipt.invoice
            ? `This will put ${formatMoney(receipt.amount, receipt.currency_code)} back onto invoice ${receipt.invoice.number}.`
            : 'This receipt will be removed permanently.';

        if (!window.confirm(`Remove ${label}?\n\n${consequence}`)) {
            return;
        }

        router.delete(`/receipts/${receipt.id}`, {
            onError: () => toast.error('Failed to remove this receipt.'),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head
                title={receipt.number ? `Receipt ${receipt.number}` : 'Receipt'}
            />

            <div className="mx-auto w-full py-3">
                {/* Header */}
                <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="flex min-w-0 items-center gap-2">
                        {standalone ? (
                            <Badge variant="secondary" className="gap-1">
                                <HandCoins className="h-3.5 w-3.5" />
                                Standalone
                            </Badge>
                        ) : (
                            <Badge variant="secondary" className="gap-1">
                                <FileText className="h-3.5 w-3.5" />
                                Against invoice {receipt.invoice?.number}
                            </Badge>
                        )}
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

                        <PillButton variant="ghost" size="sm" onClick={remove}>
                            <Trash2 className="h-4 w-4" />
                            Remove
                        </PillButton>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
                    <Panel className="lg:col-span-8">
                        <PanelHeader
                            icon={Receipt}
                            title={label}
                            subtitle={
                                receipt.client?.name
                                    ? `Received from ${receipt.client.name}`
                                    : undefined
                            }
                        />

                        <SoftTile className="mb-2 flex flex-wrap items-center justify-between gap-3">
                            <div className="min-w-0">
                                <div className="text-xs text-muted-foreground">
                                    {standalone ? 'Received for' : 'Settles'}
                                </div>
                                <div className="mt-1 truncate text-sm font-semibold">
                                    {standalone
                                        ? (receipt.description ??
                                          'Payment received')
                                        : (receipt.invoice?.number ??
                                          `Invoice #${receipt.invoice?.id}`)}
                                </div>
                            </div>

                            {receipt.invoice ? (
                                <Link
                                    href={`/invoices/${receipt.invoice.id}`}
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
                                value={receipt.client?.name ?? '—'}
                            />
                            <Info
                                label="Paid on"
                                value={fmtDate(receipt.paid_on)}
                            />
                            <Info label="Method" value={receipt.method_label} />
                            <Info
                                label="Reference"
                                value={receipt.reference?.trim() || '—'}
                            />
                            <Info
                                label="Recorded by"
                                value={receipt.recorded_by?.trim() || '—'}
                            />
                            <Info
                                label="Currency"
                                value={receipt.currency_code}
                            />
                        </div>
                    </Panel>

                    <Panel className="lg:col-span-4">
                        <PanelHeader icon={Wallet} title="Amount" />

                        <SoftTile>
                            <div className="text-xs text-muted-foreground">
                                Received
                            </div>
                            <div className="mt-1 text-2xl font-semibold tabular-nums">
                                {formatMoney(
                                    receipt.amount,
                                    receipt.currency_code,
                                )}
                            </div>
                        </SoftTile>

                        {/*
                            Only an invoice-backed receipt has a balance. Showing
                            a zero on a standalone one would claim an invoice was
                            settled that never existed.
                        */}
                        {receipt.balance_after !== null ? (
                            <SoftTile className="mt-2">
                                <div className="text-xs text-muted-foreground">
                                    Invoice balance after this payment
                                </div>
                                <div className="mt-1 text-sm font-semibold tabular-nums">
                                    {formatMoney(
                                        receipt.balance_after,
                                        receipt.currency_code,
                                    )}
                                </div>
                                <p className="mt-2 text-xs text-muted-foreground">
                                    Frozen when the payment was recorded, so the
                                    client’s copy and this one always agree.
                                </p>
                            </SoftTile>
                        ) : (
                            <SoftTile className="mt-2">
                                <div className="text-xs text-muted-foreground">
                                    No invoice
                                </div>
                                <p className="mt-2 text-xs text-muted-foreground">
                                    This receipt acknowledges money received
                                    without an invoice behind it, so there is no
                                    balance to carry.
                                </p>
                            </SoftTile>
                        )}
                    </Panel>
                </div>
            </div>
        </AppLayout>
    );
}

function Info({ label, value }: { label: string; value: string }) {
    return (
        <SoftTile>
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-1 truncate text-sm font-semibold">{value}</div>
        </SoftTile>
    );
}
