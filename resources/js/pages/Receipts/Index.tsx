import AppLayout from '@/layouts/app-layout';
import { Head, Link, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import {
    CheckCircle2,
    FileText,
    HandCoins,
    Plus,
    Receipt,
    SearchX,
    Wallet,
} from 'lucide-react';
import * as React from 'react';

import {
    Chip,
    ClientPagination,
    InitialsAvatar,
    Panel,
    PillButton,
    SearchField,
    SortableTh,
    StatTile,
    pillButtonClass,
} from '@/components/dashboard/primitives';

import { Badge } from '@/components/ui/badge';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types/index.d';
import type { PageProps } from '../../types';

/**
 * A receipt either settles an invoice or stands on its own. `invoice_number` is
 * what tells the two apart on this screen, so it is the field the source filter
 * and the source column both read.
 */
interface ReceiptRowData {
    id: number;
    number: string | null;
    client_name?: string | null;
    invoice_id?: number | null;
    invoice_number?: string | null;
    paid_on?: string | null;
    amount: number;
    currency_code: string;
    method: string;
    method_label: string;
    reference?: string | null;
    description?: string | null;
}

interface ReceiptsIndexProps extends PageProps {
    receipts: ReceiptRowData[];
    hasActiveCompany?: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Receipts', href: '/receipts' },
];

const SOURCE_FILTERS = ['all', 'invoice', 'standalone'] as const;

type SourceFilter = (typeof SOURCE_FILTERS)[number];

const SOURCE_LABELS: Record<SourceFilter, string> = {
    all: 'All',
    invoice: 'Against an invoice',
    standalone: 'Standalone',
};

type SortKey = 'paid' | 'amount';
type SortDirection = 'asc' | 'desc';

/** Receipts shown per page; the full list is filtered and sorted client-side. */
const ROWS_PER_PAGE = 8;

const fmtDate = (iso?: string | null): string => {
    if (!iso) {
        return '—';
    }

    try {
        return new Date(iso).toLocaleDateString();
    } catch {
        return iso;
    }
};

const isStandalone = (row: ReceiptRowData): boolean => !row.invoice_number;

/** The active company from the shared Inertia `companies` prop. */
function useActiveCompanyName(): string | null {
    const page = usePage<{
        companies?: { current?: { name?: string } | null } | null;
    }>();

    return page.props.companies?.current?.name ?? null;
}

export default function ReceiptsIndex({
    receipts,
    hasActiveCompany = true,
}: ReceiptsIndexProps) {
    const activeCompanyName = useActiveCompanyName();

    const [query, setQuery] = React.useState('');
    const [source, setSource] = React.useState<SourceFilter>('all');
    const [sortKey, setSortKey] = React.useState<SortKey>('paid');
    const [sortDirection, setSortDirection] =
        React.useState<SortDirection>('desc');
    const [page, setPage] = React.useState(1);

    /** A new search or filter narrows the list, so start reading from the top. */
    React.useEffect(() => {
        setPage(1);
    }, [query, source]);

    /** Clicking the active column flips it; a new column starts descending. */
    const toggleSort = (key: SortKey) => {
        setPage(1);

        if (key === sortKey) {
            setSortDirection((d) => (d === 'asc' ? 'desc' : 'asc'));
            return;
        }

        setSortKey(key);
        setSortDirection('desc');
    };

    const visibleReceipts = React.useMemo(() => {
        const needle = query.trim().toLowerCase();

        let rows = receipts;

        if (source !== 'all') {
            rows = rows.filter((row) =>
                source === 'standalone'
                    ? isStandalone(row)
                    : !isStandalone(row),
            );
        }

        if (needle) {
            rows = rows.filter((row) =>
                [
                    row.number,
                    row.client_name,
                    row.invoice_number,
                    row.reference,
                    row.description,
                    row.method_label,
                ].some((field) => (field ?? '').toLowerCase().includes(needle)),
            );
        }

        return [...rows].sort((a, b) => {
            const delta =
                sortKey === 'amount'
                    ? Number(a.amount ?? 0) - Number(b.amount ?? 0)
                    : new Date(a.paid_on ?? 0).getTime() -
                      new Date(b.paid_on ?? 0).getTime();

            return sortDirection === 'asc' ? delta : -delta;
        });
    }, [receipts, query, source, sortKey, sortDirection]);

    const pageCount = Math.max(
        1,
        Math.ceil(visibleReceipts.length / ROWS_PER_PAGE),
    );

    /** Filtering or sorting can shrink the list past the current page. */
    const currentPage = Math.min(page, pageCount);

    const pagedReceipts = React.useMemo(
        () =>
            visibleReceipts.slice(
                (currentPage - 1) * ROWS_PER_PAGE,
                currentPage * ROWS_PER_PAGE,
            ),
        [visibleReceipts, currentPage],
    );

    const totals = React.useMemo(() => {
        const standalone = receipts.filter(isStandalone);

        /**
         * Summed only when every receipt shares one currency. Adding K to $
         * would print a number that means nothing, and a company trading in two
         * currencies is exactly who would be misled by it.
         */
        const currencies = new Set(receipts.map((row) => row.currency_code));
        const singleCurrency =
            currencies.size === 1 ? [...currencies][0] : null;

        return {
            standalone: standalone.length,
            againstInvoice: receipts.length - standalone.length,
            currency: singleCurrency,
            received: singleCurrency
                ? receipts.reduce(
                      (sum, row) => sum + Number(row.amount ?? 0),
                      0,
                  )
                : null,
        };
    }, [receipts]);

    const hasReceipts = receipts.length > 0;
    const hasResults = visibleReceipts.length > 0;
    const isFiltered = source !== 'all' || Boolean(query.trim());

    const firstRowOnPage = (currentPage - 1) * ROWS_PER_PAGE + 1;
    const rangeLabel = hasResults
        ? `${firstRowOnPage}–${firstRowOnPage + pagedReceipts.length - 1}`
        : '0';

    const clearFilters = () => {
        setQuery('');
        setSource('all');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Receipts" />

            <div className="mx-auto w-full py-3">
                {/* Header */}
                <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="min-w-0">
                        {hasActiveCompany && activeCompanyName ? (
                            <div className="flex items-center gap-2">
                                <Badge variant="secondary" className="gap-1">
                                    <CheckCircle2 className="h-3.5 w-3.5" />
                                    Active:{' '}
                                    <span className="font-medium">
                                        {activeCompanyName}
                                    </span>
                                </Badge>
                            </div>
                        ) : null}
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Link
                            href="/invoices"
                            className={pillButtonClass('ghost', 'sm')}
                        >
                            <FileText className="h-4 w-4" />
                            Invoices
                        </Link>
                        {hasActiveCompany ? (
                            <Link
                                href="/receipts/create"
                                className={pillButtonClass('solid', 'sm')}
                            >
                                <Plus className="h-4 w-4" />
                                New receipt
                            </Link>
                        ) : null}
                    </div>
                </div>

                {/* Summary */}
                <div className="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
                    <StatTile
                        title={
                            activeCompanyName
                                ? `Receipts • ${activeCompanyName}`
                                : 'Receipts'
                        }
                        value={`${receipts.length}`}
                        icon={Receipt}
                        sub={
                            hasReceipts
                                ? `${totals.againstInvoice} against an invoice • ${totals.standalone} standalone`
                                : undefined
                        }
                    />
                    <StatTile
                        title="Total received"
                        value={
                            totals.received !== null
                                ? formatMoney(
                                      totals.received,
                                      totals.currency ?? 'ZMW',
                                  )
                                : '—'
                        }
                        icon={Wallet}
                        sub={
                            hasReceipts && totals.received === null
                                ? 'Mixed currencies — not summed'
                                : undefined
                        }
                    />
                    <StatTile
                        title="Standalone"
                        value={`${totals.standalone}`}
                        icon={HandCoins}
                        sub={
                            hasReceipts
                                ? 'Money received with no invoice raised'
                                : undefined
                        }
                    />
                </div>

                {/* Receipt list */}
                <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-start gap-2.5">
                        <span className="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">
                            <Receipt className="h-4 w-4" />
                        </span>
                        <div>
                            <div className="text-sm font-semibold">
                                Your receipts
                            </div>
                            <div className="mt-0.5 text-xs text-muted-foreground">
                                {hasReceipts && isFiltered
                                    ? `${rangeLabel} of ${visibleReceipts.length} matching • ${receipts.length} total`
                                    : `${rangeLabel} of ${receipts.length} total`}
                            </div>
                        </div>
                    </div>

                    {hasReceipts ? (
                        <SearchField
                            value={query}
                            onChange={setQuery}
                            placeholder="Search number, client, invoice…"
                            className="w-full sm:w-72"
                        />
                    ) : null}
                </div>

                {hasReceipts ? (
                    <div className="mb-4 flex flex-wrap items-center gap-2">
                        {SOURCE_FILTERS.map((value) => (
                            <Chip
                                key={value}
                                active={source === value}
                                onClick={() => setSource(value)}
                            >
                                {SOURCE_LABELS[value]}
                            </Chip>
                        ))}
                    </div>
                ) : null}

                <Panel>
                    {!hasReceipts ? (
                        <EmptyReceipts hasActiveCompany={hasActiveCompany} />
                    ) : !hasResults ? (
                        <NoSearchResults onClear={clearFilters} />
                    ) : (
                        <ReceiptTable
                            receipts={pagedReceipts}
                            sortKey={sortKey}
                            sortDirection={sortDirection}
                            onSort={toggleSort}
                        />
                    )}
                </Panel>

                {hasResults ? (
                    <ClientPagination
                        page={currentPage}
                        pageCount={pageCount}
                        onPageChange={setPage}
                        className="mt-4"
                    />
                ) : null}
            </div>
        </AppLayout>
    );
}

/* ------------------------------ Receipt table ------------------------------ */

/** Rows read as tinted tiles rather than ruled lines, matching the panels. */
const cellClass = cn(
    'bg-muted/40 px-3 py-3 align-middle transition dark:bg-white/5',
    'group-hover:bg-brand-50/70 dark:group-hover:bg-brand-500/10',
);

function ReceiptTable({
    receipts,
    sortKey,
    sortDirection,
    onSort,
}: {
    receipts: ReceiptRowData[];
    sortKey: SortKey;
    sortDirection: SortDirection;
    onSort: (key: SortKey) => void;
}) {
    return (
        <div className="-mx-1 overflow-x-auto px-1">
            <table className="w-full min-w-[58rem] border-separate border-spacing-y-1.5 text-sm">
                <thead>
                    <tr className="text-left text-xs text-muted-foreground">
                        <th className="px-3 pb-1 font-medium">Receipt</th>
                        <th className="px-3 pb-1 font-medium">Source</th>
                        <th className="px-3 pb-1 font-medium">Method</th>
                        <SortableTh
                            label="Paid on"
                            active={sortKey === 'paid'}
                            direction={sortDirection}
                            onClick={() => onSort('paid')}
                        />
                        <SortableTh
                            label="Amount"
                            active={sortKey === 'amount'}
                            direction={sortDirection}
                            onClick={() => onSort('amount')}
                        />
                        <th className="px-3 pb-1 text-right font-medium">
                            Actions
                        </th>
                    </tr>
                </thead>

                <tbody>
                    {receipts.map((receipt, index) => (
                        <ReceiptRow
                            key={receipt.id}
                            receipt={receipt}
                            index={index}
                        />
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function ReceiptRow({
    receipt,
    index,
}: {
    receipt: ReceiptRowData;
    index: number;
}) {
    const label = receipt.number ?? `Receipt #${receipt.id}`;
    const client = receipt.client_name ?? 'No client';
    const standalone = isStandalone(receipt);

    return (
        <motion.tr
            initial={{ opacity: 0, y: 8 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{
                duration: 0.22,
                delay: Math.min(0.03 * index, 0.18),
            }}
            className="group"
        >
            <td className={cn(cellClass, 'rounded-l-2xl')}>
                <div className="flex items-center gap-3">
                    <InitialsAvatar name={client} />

                    <div className="min-w-0">
                        <div className="truncate font-semibold">{label}</div>
                        <div className="mt-0.5 truncate text-xs text-muted-foreground">
                            {client}
                        </div>
                    </div>
                </div>
            </td>

            {/*
                The one column that says which kind of receipt this is. A
                standalone one names what the money was for, because it has no
                invoice number to point the client at.
            */}
            <td className={cellClass}>
                {standalone ? (
                    <div className="min-w-0">
                        <Badge variant="secondary" className="gap-1">
                            <HandCoins className="h-3.5 w-3.5" />
                            Standalone
                        </Badge>
                        {receipt.description ? (
                            <div className="mt-1 truncate text-xs text-muted-foreground">
                                {receipt.description}
                            </div>
                        ) : null}
                    </div>
                ) : (
                    <Link
                        href={`/invoices/${receipt.invoice_id}`}
                        className="truncate font-medium hover:underline"
                    >
                        {receipt.invoice_number}
                    </Link>
                )}
            </td>

            <td className={cellClass}>
                <div className="min-w-0">
                    <div className="truncate font-medium">
                        {receipt.method_label}
                    </div>
                    {receipt.reference ? (
                        <div className="mt-0.5 truncate text-xs text-muted-foreground">
                            {receipt.reference}
                        </div>
                    ) : null}
                </div>
            </td>

            <td className={cn(cellClass, 'text-muted-foreground')}>
                {fmtDate(receipt.paid_on)}
            </td>

            <td className={cn(cellClass, 'font-semibold tabular-nums')}>
                {formatMoney(receipt.amount, receipt.currency_code)}
            </td>

            <td className={cn(cellClass, 'rounded-r-2xl text-right')}>
                <Link
                    href={`/receipts/${receipt.id}`}
                    className={pillButtonClass('soft', 'sm')}
                >
                    View
                </Link>
            </td>
        </motion.tr>
    );
}

/* ------------------------------ Empty states ------------------------------ */

/**
 * Two ways in, so the empty state names both: most receipts come from recording
 * a payment on an invoice, but money received with no invoice behind it is
 * exactly what the create form is for.
 */
function EmptyReceipts({ hasActiveCompany }: { hasActiveCompany: boolean }) {
    return (
        <div className="flex flex-col items-center px-4 py-10 text-center">
            <span className="grid h-14 w-14 place-items-center rounded-2xl bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">
                <Receipt className="h-6 w-6" />
            </span>

            <div className="mt-4 text-sm font-semibold">No receipts yet</div>
            <p className="mt-1 max-w-md text-sm text-muted-foreground">
                Recording a payment against an invoice issues a receipt
                automatically. For money received with no invoice behind it — a
                deposit, or payment on delivery — issue one directly.
            </p>

            <div className="mt-5 flex flex-wrap items-center justify-center gap-2">
                {hasActiveCompany ? (
                    <Link
                        href="/receipts/create"
                        className={pillButtonClass('solid', 'sm')}
                    >
                        <Plus className="h-4 w-4" />
                        New receipt
                    </Link>
                ) : null}
                <Link
                    href="/invoices"
                    className={pillButtonClass('soft', 'sm')}
                >
                    <FileText className="h-4 w-4" />
                    Go to invoices
                </Link>
            </div>
        </div>
    );
}

function NoSearchResults({ onClear }: { onClear: () => void }) {
    return (
        <div className="flex flex-col items-center px-4 py-10 text-center">
            <span className="grid h-14 w-14 place-items-center rounded-2xl bg-muted/60 text-muted-foreground dark:bg-white/5">
                <SearchX className="h-6 w-6" />
            </span>

            <div className="mt-4 text-sm font-semibold">No matches</div>
            <p className="mt-1 max-w-sm text-sm text-muted-foreground">
                No receipts match the current search and source filter.
            </p>

            <PillButton
                variant="ghost"
                size="sm"
                className="mt-5"
                onClick={onClear}
            >
                Clear filters
            </PillButton>
        </div>
    );
}
