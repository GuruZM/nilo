import AppLayout from '@/layouts/app-layout';
import { Head, Link, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import {
    Boxes,
    CheckCircle2,
    FileText,
    PackageCheck,
    SearchX,
    Truck,
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

import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types/index.d';
import type { PageProps } from '../../types';

type DeliveryNoteStatus = 'draft' | 'dispatched' | 'delivered' | string;

/**
 * There is deliberately no amount and no currency on this row type. A delivery
 * note proves what arrived, not what it cost — the printed sheet suppresses
 * every figure, and this list must not put it back.
 */
interface DeliveryNoteRowData {
    id: number;
    number: string | null;
    client_name?: string | null;
    invoice_number?: string | null;
    issue_date?: string | null;
    delivery_date?: string | null;
    deliver_to?: string | null;
    received_by?: string | null;
    status: DeliveryNoteStatus;
    item_count: number;
}

/**
 * No `prerequisites` prop and no create button: a delivery note is generated
 * from an invoice, never from a blank form, so the invoice it came from has
 * already proved the company, client and template exist.
 */
interface DeliveryNotesIndexProps extends PageProps {
    deliveryNotes: DeliveryNoteRowData[];
    hasActiveCompany?: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Delivery notes', href: '/delivery-notes' },
];

const STATUS_FILTERS = ['all', 'draft', 'dispatched', 'delivered'] as const;

type SortKey = 'delivery' | 'items';
type SortDirection = 'asc' | 'desc';

/** Delivery notes shown per page; the full list is filtered and sorted client-side. */
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

/** Reads as a count of things, never as a figure in a money column. */
const fmtItemCount = (count: number): string =>
    `${count} item${count === 1 ? '' : 's'}`;

/** The active company from the shared Inertia `companies` prop. */
function useActiveCompanyName(): string | null {
    const page = usePage<{
        companies?: { current?: { name?: string } | null } | null;
    }>();

    return page.props.companies?.current?.name ?? null;
}

export default function DeliveryNotesIndex({
    deliveryNotes,
    hasActiveCompany = true,
}: DeliveryNotesIndexProps) {
    const activeCompanyName = useActiveCompanyName();

    const [query, setQuery] = React.useState('');
    const [status, setStatus] = React.useState<'all' | DeliveryNoteStatus>(
        'all',
    );
    const [sortKey, setSortKey] = React.useState<SortKey>('delivery');
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

    const visibleNotes = React.useMemo(() => {
        const needle = query.trim().toLowerCase();

        let rows = deliveryNotes;

        if (status !== 'all') {
            rows = rows.filter(
                (note) =>
                    (note.status || '').toLowerCase() ===
                    String(status).toLowerCase(),
            );
        }

        if (needle) {
            rows = rows.filter((note) =>
                [
                    note.number,
                    note.client_name,
                    note.invoice_number,
                    note.deliver_to,
                    note.received_by,
                    note.status,
                ].some((field) => (field ?? '').toLowerCase().includes(needle)),
            );
        }

        return [...rows].sort((a, b) => {
            /** An undispatched note has no delivery date, so it sorts by when it was raised. */
            const when = (note: DeliveryNoteRowData) =>
                new Date(note.delivery_date ?? note.issue_date ?? 0).getTime();

            const delta =
                sortKey === 'items'
                    ? Number(a.item_count ?? 0) - Number(b.item_count ?? 0)
                    : when(a) - when(b);

            return sortDirection === 'asc' ? delta : -delta;
        });
    }, [deliveryNotes, query, status, sortKey, sortDirection]);

    const pageCount = Math.max(
        1,
        Math.ceil(visibleNotes.length / ROWS_PER_PAGE),
    );

    /** Filtering or sorting can shrink the list past the current page. */
    const currentPage = Math.min(page, pageCount);

    const pagedNotes = React.useMemo(
        () =>
            visibleNotes.slice(
                (currentPage - 1) * ROWS_PER_PAGE,
                currentPage * ROWS_PER_PAGE,
            ),
        [visibleNotes, currentPage],
    );

    const totals = React.useMemo(() => {
        const count = (value: string) =>
            deliveryNotes.filter(
                (note) => (note.status || '').toLowerCase() === value,
            ).length;

        return {
            draft: count('draft'),
            dispatched: count('dispatched'),
            delivered: count('delivered'),
            items: deliveryNotes.reduce(
                (sum, note) => sum + Number(note.item_count ?? 0),
                0,
            ),
        };
    }, [deliveryNotes]);

    const hasNotes = deliveryNotes.length > 0;
    const hasResults = visibleNotes.length > 0;
    const isFiltered = status !== 'all' || Boolean(query.trim());

    const firstRowOnPage = (currentPage - 1) * ROWS_PER_PAGE + 1;
    const rangeLabel = hasResults
        ? `${firstRowOnPage}–${firstRowOnPage + pagedNotes.length - 1}`
        : '0';

    const clearFilters = () => {
        setQuery('');
        setStatus('all');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Delivery notes" />

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

                    {/*
                        No "add delivery note" button. The document is generated
                        from the invoice it dispatches against, so the only way
                        in is through that invoice.
                    */}
                    <div className="flex flex-wrap items-center gap-2">
                        <Link
                            href="/dashboard"
                            className={pillButtonClass('ghost', 'sm')}
                        >
                            Back to dashboard
                        </Link>
                        <Link
                            href="/invoices"
                            className={pillButtonClass('soft', 'sm')}
                        >
                            <FileText className="h-4 w-4" />
                            Go to invoices
                        </Link>
                    </div>
                </div>

                {/* Summary — counts and dates only; this document carries no money. */}
                <div className="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
                    <StatTile
                        title={
                            activeCompanyName
                                ? `Delivery notes • ${activeCompanyName}`
                                : 'Delivery notes'
                        }
                        value={`${deliveryNotes.length}`}
                        icon={Truck}
                        sub={
                            hasNotes
                                ? `${totals.draft} draft • ${totals.dispatched} dispatched`
                                : undefined
                        }
                    />
                    <StatTile
                        title="Delivered"
                        value={`${totals.delivered}`}
                        icon={PackageCheck}
                        sub={
                            hasNotes
                                ? totals.delivered === deliveryNotes.length
                                    ? 'Everything signed for'
                                    : `${deliveryNotes.length - totals.delivered} awaiting sign-off`
                                : undefined
                        }
                    />
                    <StatTile
                        title="Lines dispatched"
                        value={`${totals.items}`}
                        icon={Boxes}
                        sub={
                            hasNotes
                                ? `Across ${deliveryNotes.length} note${deliveryNotes.length === 1 ? '' : 's'}`
                                : undefined
                        }
                    />
                </div>

                {/* Delivery note list */}
                <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-start gap-2.5">
                        <span className="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">
                            <Truck className="h-4 w-4" />
                        </span>
                        <div>
                            <div className="text-sm font-semibold">
                                Your delivery notes
                            </div>
                            <div className="mt-0.5 text-xs text-muted-foreground">
                                {hasNotes && isFiltered
                                    ? `${rangeLabel} of ${visibleNotes.length} matching • ${deliveryNotes.length} total`
                                    : `${rangeLabel} of ${deliveryNotes.length} total`}
                            </div>
                        </div>
                    </div>

                    {hasNotes ? (
                        <SearchField
                            value={query}
                            onChange={setQuery}
                            placeholder="Search number, invoice, recipient…"
                            className="w-full sm:w-72"
                        />
                    ) : null}
                </div>

                {hasNotes ? (
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
                    {!hasNotes ? (
                        <EmptyDeliveryNotes />
                    ) : !hasResults ? (
                        <NoSearchResults onClear={clearFilters} />
                    ) : (
                        <DeliveryNoteTable
                            deliveryNotes={pagedNotes}
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

/* --------------------------- Delivery note table --------------------------- */

/** Rows read as tinted tiles rather than ruled lines, matching the panels. */
const cellClass = cn(
    'bg-muted/40 px-3 py-3 align-middle transition dark:bg-white/5',
    'group-hover:bg-brand-50/70 dark:group-hover:bg-brand-500/10',
);

function DeliveryNoteTable({
    deliveryNotes,
    sortKey,
    sortDirection,
    onSort,
}: {
    deliveryNotes: DeliveryNoteRowData[];
    sortKey: SortKey;
    sortDirection: SortDirection;
    onSort: (key: SortKey) => void;
}) {
    return (
        <div className="-mx-1 overflow-x-auto px-1">
            <table className="w-full min-w-[58rem] border-separate border-spacing-y-1.5 text-sm">
                <thead>
                    <tr className="text-left text-xs text-muted-foreground">
                        <th className="px-3 pb-1 font-medium">Delivery note</th>
                        <th className="px-3 pb-1 font-medium">
                            Against invoice
                        </th>
                        <th className="px-3 pb-1 font-medium">Deliver to</th>
                        <SortableTh
                            label="Contents"
                            active={sortKey === 'items'}
                            direction={sortDirection}
                            onClick={() => onSort('items')}
                        />
                        <th className="px-3 pb-1 font-medium">Status</th>
                        <SortableTh
                            label="Delivery date"
                            active={sortKey === 'delivery'}
                            direction={sortDirection}
                            onClick={() => onSort('delivery')}
                        />
                        <th className="px-3 pb-1 text-right font-medium">
                            Actions
                        </th>
                    </tr>
                </thead>

                <tbody>
                    {deliveryNotes.map((deliveryNote, index) => (
                        <DeliveryNoteRow
                            key={deliveryNote.id}
                            deliveryNote={deliveryNote}
                            index={index}
                        />
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function DeliveryNoteRow({
    deliveryNote,
    index,
}: {
    deliveryNote: DeliveryNoteRowData;
    index: number;
}) {
    const label = deliveryNote.number ?? `Delivery note #${deliveryNote.id}`;
    const client = deliveryNote.client_name ?? 'No client';
    const recipient = deliveryNote.deliver_to?.trim() || client;

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

            <td className={cellClass}>
                <span className="truncate font-medium">
                    {deliveryNote.invoice_number ?? '—'}
                </span>
            </td>

            <td className={cellClass}>
                <div className="min-w-0">
                    <div className="truncate font-medium">{recipient}</div>
                    <div className="mt-0.5 truncate text-xs text-muted-foreground">
                        {deliveryNote.received_by?.trim()
                            ? `Signed by ${deliveryNote.received_by}`
                            : 'Not signed for'}
                    </div>
                </div>
            </td>

            {/*
                A count of lines, worded as a count. Left-aligned and unpadded
                by tabular figures so it never reads as the amount column this
                document does not have.
            */}
            <td className={cellClass}>
                {fmtItemCount(Number(deliveryNote.item_count ?? 0))}
            </td>

            <td className={cellClass}>
                <StatusPill status={deliveryNote.status} />
            </td>

            <td className={cn(cellClass, 'text-muted-foreground')}>
                {fmtDate(deliveryNote.delivery_date)}
            </td>

            <td className={cn(cellClass, 'rounded-r-2xl text-right')}>
                <Link
                    href={`/delivery-notes/${deliveryNote.id}`}
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
 * The empty state has no create action, because there is no create form. It
 * points at the invoices list instead, which is the only place a delivery note
 * can be started from.
 */
function EmptyDeliveryNotes() {
    return (
        <div className="flex flex-col items-center px-4 py-10 text-center">
            <span className="grid h-14 w-14 place-items-center rounded-2xl bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">
                <Truck className="h-6 w-6" />
            </span>

            <div className="mt-4 text-sm font-semibold">
                No delivery notes yet
            </div>
            <p className="mt-1 max-w-sm text-sm text-muted-foreground">
                A delivery note is generated from an invoice, so there is no
                blank form to fill in. Open the invoice you are dispatching
                against and choose “Delivery note”.
            </p>

            <div className="mt-5">
                <Link
                    href="/invoices"
                    className={pillButtonClass('solid', 'sm')}
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
                No delivery notes match the current search and status filter.
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
