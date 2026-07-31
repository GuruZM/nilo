import AppLayout from '@/layouts/app-layout';
import { Head, Link, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import {
    BadgeDollarSign,
    CheckCircle2,
    ClipboardList,
    Factory,
    PackageCheck,
    Plus,
    SearchX,
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
    StatusPill,
    pillButtonClass,
} from '@/components/dashboard/primitives';
import { Money } from '@/components/money';

import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types/index.d';
import type { PageProps } from '../../types';

type PurchaseOrderStatus =
    | 'draft'
    | 'sent'
    | 'approved'
    | 'received'
    | 'cancelled'
    | string;

interface PurchaseOrder {
    id: number;
    number: string | null;
    supplier_name?: string | null;
    issue_date?: string | null;
    expected_date?: string | null;
    currency_code?: string | null;
    total: number;
    status: PurchaseOrderStatus;
}

/**
 * There is no prerequisite gate here, unlike the quotation list. A purchase
 * order needs exactly one thing that may not exist yet — a supplier — and the
 * controller answers that directly with `hasSuppliers`, so the empty state
 * carries it rather than a separate blocker panel.
 */
interface PurchaseOrdersIndexProps extends PageProps {
    purchaseOrders: PurchaseOrder[];
    hasActiveCompany?: boolean;
    hasSuppliers?: boolean;

    currencies?: {
        current?: { code: string; symbol?: string | null; precision?: number };
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Purchase orders', href: '/purchase-orders' },
];

const STATUS_FILTERS = [
    'all',
    'draft',
    'sent',
    'approved',
    'received',
    'cancelled',
] as const;

type SortKey = 'issued' | 'expected' | 'amount';
type SortDirection = 'asc' | 'desc';

/** Orders shown per page; the full list is filtered and sorted client-side. */
const ROWS_PER_PAGE = 5;

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

/** The active company from the shared Inertia `companies` prop. */
function useActiveCompanyName(): string | null {
    const page = usePage<{
        companies?: { current?: { name?: string } | null } | null;
    }>();

    return page.props.companies?.current?.name ?? null;
}

export default function PurchaseOrdersIndex({
    purchaseOrders,
    hasActiveCompany = true,
    hasSuppliers = true,
    currencies,
}: PurchaseOrdersIndexProps) {
    const activeCompanyName = useActiveCompanyName();
    const activeCurrency = currencies?.current;

    const [query, setQuery] = React.useState('');
    const [status, setStatus] = React.useState<'all' | PurchaseOrderStatus>(
        'all',
    );
    const [sortKey, setSortKey] = React.useState<SortKey>('issued');
    const [sortDirection, setSortDirection] =
        React.useState<SortDirection>('desc');
    const [page, setPage] = React.useState(1);

    /** A new search or status narrows the list, so start reading from the top. */
    React.useEffect(() => {
        setPage(1);
    }, [query, status]);

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

    const visibleOrders = React.useMemo(() => {
        const needle = query.trim().toLowerCase();

        let rows = purchaseOrders;

        if (status !== 'all') {
            rows = rows.filter(
                (order) =>
                    (order.status || '').toLowerCase() ===
                    String(status).toLowerCase(),
            );
        }

        if (needle) {
            rows = rows.filter((order) =>
                [order.number, order.supplier_name, order.status].some(
                    (field) => (field ?? '').toLowerCase().includes(needle),
                ),
            );
        }

        return [...rows].sort((a, b) => {
            const dateOf = (value?: string | null) =>
                new Date(value ?? 0).getTime();

            const delta =
                sortKey === 'amount'
                    ? Number(a.total ?? 0) - Number(b.total ?? 0)
                    : sortKey === 'expected'
                      ? dateOf(a.expected_date) - dateOf(b.expected_date)
                      : dateOf(a.issue_date) - dateOf(b.issue_date);

            return sortDirection === 'asc' ? delta : -delta;
        });
    }, [purchaseOrders, query, status, sortKey, sortDirection]);

    const pageCount = Math.max(
        1,
        Math.ceil(visibleOrders.length / ROWS_PER_PAGE),
    );

    /** Filtering or sorting can shrink the list past the current page. */
    const currentPage = Math.min(page, pageCount);

    const pagedOrders = React.useMemo(
        () =>
            visibleOrders.slice(
                (currentPage - 1) * ROWS_PER_PAGE,
                currentPage * ROWS_PER_PAGE,
            ),
        [visibleOrders, currentPage],
    );

    const totals = React.useMemo(() => {
        const count = (value: string) =>
            purchaseOrders.filter(
                (o) => (o.status || '').toLowerCase() === value,
            ).length;

        const cancelled = count('cancelled');

        return {
            draft: count('draft'),
            sent: count('sent'),
            approved: count('approved'),
            received: count('received'),
            cancelled,

            /**
             * A cancelled order is never going to be paid for, so it is left
             * out of the committed figure — everything else is money the
             * company has undertaken to spend.
             */
            committed: purchaseOrders
                .filter((o) => (o.status || '').toLowerCase() !== 'cancelled')
                .reduce((sum, o) => sum + Number(o.total ?? 0), 0),
        };
    }, [purchaseOrders]);

    const hasOrders = purchaseOrders.length > 0;
    const hasResults = visibleOrders.length > 0;
    const isFiltered = status !== 'all' || Boolean(query.trim());

    const firstRowOnPage = (currentPage - 1) * ROWS_PER_PAGE + 1;
    const rangeLabel = hasResults
        ? `${firstRowOnPage}–${firstRowOnPage + pagedOrders.length - 1}`
        : '0';

    const clearFilters = () => {
        setQuery('');
        setStatus('all');
    };

    /** Nothing to order from means the create form has nothing to offer. */
    const canCreate = hasActiveCompany && hasSuppliers;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Purchase orders" />

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
                            href="/dashboard"
                            className={pillButtonClass('ghost', 'sm')}
                        >
                            Back to dashboard
                        </Link>
                        {canCreate ? (
                            <Link
                                href="/purchase-orders/create"
                                className={pillButtonClass('solid', 'sm')}
                            >
                                <Plus className="h-4 w-4" />
                                Add purchase order
                            </Link>
                        ) : null}
                    </div>
                </div>

                {/* Summary */}
                <div className="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
                    <StatTile
                        title={
                            activeCompanyName
                                ? `Purchase orders • ${activeCompanyName}`
                                : 'Purchase orders'
                        }
                        value={`${purchaseOrders.length}`}
                        icon={ClipboardList}
                        sub={
                            hasOrders
                                ? `${totals.draft} draft • ${totals.sent} sent`
                                : undefined
                        }
                    />
                    <StatTile
                        title="Received"
                        value={`${totals.received}`}
                        icon={PackageCheck}
                        sub={
                            hasOrders
                                ? `${totals.approved} approved and still awaited`
                                : undefined
                        }
                    />
                    <StatTile
                        title="Committed"
                        value={
                            <Money
                                amount={totals.committed}
                                currency={activeCurrency}
                            />
                        }
                        icon={BadgeDollarSign}
                        sub={
                            totals.cancelled
                                ? `${totals.cancelled} cancelled and excluded`
                                : hasOrders
                                  ? 'Nothing cancelled'
                                  : undefined
                        }
                    />
                </div>

                {/* Purchase order list */}
                <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-start gap-2.5">
                        <span className="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">
                            <ClipboardList className="h-4 w-4" />
                        </span>
                        <div>
                            <div className="text-sm font-semibold">
                                Your purchase orders
                            </div>
                            <div className="mt-0.5 text-xs text-muted-foreground">
                                {hasOrders && isFiltered
                                    ? `${rangeLabel} of ${visibleOrders.length} matching • ${purchaseOrders.length} total`
                                    : `${rangeLabel} of ${purchaseOrders.length} total`}
                            </div>
                        </div>
                    </div>

                    {hasOrders ? (
                        <SearchField
                            value={query}
                            onChange={setQuery}
                            placeholder="Search number, supplier, status…"
                            className="w-full sm:w-72"
                        />
                    ) : null}
                </div>

                {hasOrders ? (
                    <div className="mb-4 flex flex-wrap items-center gap-2">
                        {STATUS_FILTERS.map((value) => (
                            <Chip
                                key={value}
                                active={status === value}
                                onClick={() => setStatus(value)}
                            >
                                {value === 'all' ? 'All' : value}
                            </Chip>
                        ))}
                    </div>
                ) : null}

                <Panel>
                    {!hasOrders ? (
                        <EmptyPurchaseOrders
                            hasActiveCompany={hasActiveCompany}
                            hasSuppliers={hasSuppliers}
                        />
                    ) : !hasResults ? (
                        <NoSearchResults onClear={clearFilters} />
                    ) : (
                        <PurchaseOrderTable
                            purchaseOrders={pagedOrders}
                            activeCurrency={activeCurrency}
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

/* --------------------------- Purchase order table --------------------------- */

/** Rows read as tinted tiles rather than ruled lines, matching the panels. */
const cellClass = cn(
    'bg-muted/40 px-3 py-3 align-middle transition dark:bg-white/5',
    'group-hover:bg-brand-50/70 dark:group-hover:bg-brand-500/10',
);

function PurchaseOrderTable({
    purchaseOrders,
    activeCurrency,
    sortKey,
    sortDirection,
    onSort,
}: {
    purchaseOrders: PurchaseOrder[];
    activeCurrency?: { code: string; symbol?: string | null };
    sortKey: SortKey;
    sortDirection: SortDirection;
    onSort: (key: SortKey) => void;
}) {
    return (
        <div className="-mx-1 overflow-x-auto px-1">
            <table className="w-full min-w-[52rem] border-separate border-spacing-y-1.5 text-sm">
                <thead>
                    <tr className="text-left text-xs text-muted-foreground">
                        <th className="px-3 pb-1 font-medium">
                            Purchase order
                        </th>
                        <th className="px-3 pb-1 font-medium">Supplier</th>
                        <SortableTh
                            label="Amount"
                            active={sortKey === 'amount'}
                            direction={sortDirection}
                            onClick={() => onSort('amount')}
                        />
                        <th className="px-3 pb-1 font-medium">Status</th>
                        <SortableTh
                            label="Expected"
                            active={sortKey === 'expected'}
                            direction={sortDirection}
                            onClick={() => onSort('expected')}
                        />
                        <th className="px-3 pb-1 text-right font-medium">
                            Actions
                        </th>
                    </tr>
                </thead>

                <tbody>
                    {purchaseOrders.map((purchaseOrder, index) => (
                        <PurchaseOrderRow
                            key={purchaseOrder.id}
                            purchaseOrder={purchaseOrder}
                            index={index}
                            activeCurrency={activeCurrency}
                        />
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function PurchaseOrderRow({
    purchaseOrder,
    index,
    activeCurrency,
}: {
    purchaseOrder: PurchaseOrder;
    index: number;
    activeCurrency?: { code: string; symbol?: string | null };
}) {
    const label = purchaseOrder.number ?? `Purchase order #${purchaseOrder.id}`;
    const supplier = purchaseOrder.supplier_name ?? 'No supplier';

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
                    <InitialsAvatar name={supplier} />

                    <div className="min-w-0">
                        <div className="truncate font-semibold">{label}</div>
                        <div className="mt-0.5 truncate text-xs text-muted-foreground">
                            Ordered {fmtDate(purchaseOrder.issue_date)} •{' '}
                            {purchaseOrder.currency_code ??
                                activeCurrency?.code ??
                                '—'}
                        </div>
                    </div>
                </div>
            </td>

            <td className={cellClass}>
                <span className="truncate">{supplier}</span>
            </td>

            <td className={cellClass}>
                <span className="tabular-nums">
                    <Money
                        amount={Number(purchaseOrder.total ?? 0)}
                        code={purchaseOrder.currency_code}
                        currency={activeCurrency}
                    />
                </span>
            </td>

            <td className={cellClass}>
                <StatusPill status={purchaseOrder.status} />
            </td>

            <td className={cn(cellClass, 'text-muted-foreground')}>
                {fmtDate(purchaseOrder.expected_date)}
            </td>

            <td className={cn(cellClass, 'rounded-r-2xl text-right')}>
                <Link
                    href={`/purchase-orders/${purchaseOrder.id}`}
                    className={pillButtonClass('soft', 'sm')}
                >
                    View
                </Link>
            </td>
        </motion.tr>
    );
}

/* --------------------------- Empty states --------------------------- */

/**
 * One empty state covering three situations, rather than a separate blocker
 * panel above the list. An order is placed with a supplier, so with no supplier
 * on file there is nothing to create — and pointing at `/purchase-orders/create`
 * would only land the user on a form with an empty picker.
 */
function EmptyPurchaseOrders({
    hasActiveCompany,
    hasSuppliers,
}: {
    hasActiveCompany: boolean;
    hasSuppliers: boolean;
}) {
    if (!hasActiveCompany) {
        return (
            <div className="flex flex-col items-center px-4 py-10 text-center">
                <span className="grid h-14 w-14 place-items-center rounded-2xl bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">
                    <ClipboardList className="h-6 w-6" />
                </span>

                <div className="mt-4 text-sm font-semibold">
                    Add or select a company first
                </div>
                <p className="mt-1 max-w-sm text-sm text-muted-foreground">
                    Purchase orders belong to a company, so one has to be active
                    before suppliers and orders can be managed.
                </p>

                <div className="mt-5">
                    <Link
                        href="/companies"
                        className={pillButtonClass('solid', 'sm')}
                    >
                        Manage companies
                    </Link>
                </div>
            </div>
        );
    }

    if (!hasSuppliers) {
        return (
            <div className="flex flex-col items-center px-4 py-10 text-center">
                <span className="grid h-14 w-14 place-items-center rounded-2xl bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">
                    <Factory className="h-6 w-6" />
                </span>

                <div className="mt-4 text-sm font-semibold">
                    Add a supplier before raising a purchase order
                </div>
                <p className="mt-1 max-w-sm text-sm text-muted-foreground">
                    An order is always placed with somebody. Add the vendor you
                    are buying from, then come back and order from them.
                </p>

                <div className="mt-5">
                    <Link
                        href="/suppliers"
                        className={pillButtonClass('solid', 'sm')}
                    >
                        <Factory className="h-4 w-4" />
                        Go to suppliers
                    </Link>
                </div>
            </div>
        );
    }

    return (
        <div className="flex flex-col items-center px-4 py-10 text-center">
            <span className="grid h-14 w-14 place-items-center rounded-2xl bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">
                <ClipboardList className="h-6 w-6" />
            </span>

            <div className="mt-4 text-sm font-semibold">
                No purchase orders yet
            </div>
            <p className="mt-1 max-w-sm text-sm text-muted-foreground">
                Raise a purchase order to put in writing what you are buying,
                from whom, and when you expect it.
            </p>

            <div className="mt-5">
                <Link
                    href="/purchase-orders/create"
                    className={pillButtonClass('solid', 'sm')}
                >
                    <Plus className="h-4 w-4" />
                    Add purchase order
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
                No purchase orders match the current search and status filter.
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
