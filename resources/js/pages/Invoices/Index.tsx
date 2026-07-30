import {
    CreateDocumentButton,
    PrerequisiteGate,
    permissivePrerequisites,
    type DocumentPrerequisites,
} from '@/components/document-prerequisites';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import {
    BadgeDollarSign,
    CheckCircle2,
    FileText,
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

type InvoiceStatus = 'draft' | 'sent' | 'paid' | 'overdue' | 'void' | string;

interface Invoice {
    id: number;
    number: string | null;
    total: number;
    status: InvoiceStatus;
    created_at: string;
    issue_date?: string | null;
    due_date?: string | null;
    currency_code?: string | null;
    client_name?: string | null;
}

interface InvoicesIndexProps extends PageProps {
    invoices: Invoice[];
    hasActiveCompany?: boolean;
    prerequisites?: DocumentPrerequisites;

    currencies?: {
        current?: { code: string; symbol?: string | null; precision?: number };
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Invoices', href: '/invoices' },
];

const STATUS_FILTERS = [
    'all',
    'draft',
    'sent',
    'paid',
    'overdue',
    'void',
] as const;

type SortKey = 'created' | 'amount';
type SortDirection = 'asc' | 'desc';

/** Invoices shown per page; the full list is filtered and sorted client-side. */
const ROWS_PER_PAGE = 5;

const fmtDate = (iso: string): string => {
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

export default function InvoicesIndex({
    invoices,
    hasActiveCompany = true,
    prerequisites = permissivePrerequisites,
    currencies,
}: InvoicesIndexProps) {
    const canCreate = prerequisites.can_create;
    const activeCompanyName = useActiveCompanyName();
    const activeCurrency = currencies?.current;

    const [query, setQuery] = React.useState('');
    const [status, setStatus] = React.useState<'all' | InvoiceStatus>('all');
    const [sortKey, setSortKey] = React.useState<SortKey>('created');
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

    const visibleInvoices = React.useMemo(() => {
        const needle = query.trim().toLowerCase();

        let rows = invoices;

        if (status !== 'all') {
            rows = rows.filter(
                (invoice) =>
                    (invoice.status || '').toLowerCase() ===
                    String(status).toLowerCase(),
            );
        }

        if (needle) {
            rows = rows.filter((invoice) =>
                [invoice.number, invoice.client_name, invoice.status].some(
                    (field) => (field ?? '').toLowerCase().includes(needle),
                ),
            );
        }

        return [...rows].sort((a, b) => {
            const delta =
                sortKey === 'amount'
                    ? Number(a.total ?? 0) - Number(b.total ?? 0)
                    : new Date(a.created_at).getTime() -
                      new Date(b.created_at).getTime();

            return sortDirection === 'asc' ? delta : -delta;
        });
    }, [invoices, query, status, sortKey, sortDirection]);

    const pageCount = Math.max(
        1,
        Math.ceil(visibleInvoices.length / ROWS_PER_PAGE),
    );

    /** Filtering or sorting can shrink the list past the current page. */
    const currentPage = Math.min(page, pageCount);

    const pagedInvoices = React.useMemo(
        () =>
            visibleInvoices.slice(
                (currentPage - 1) * ROWS_PER_PAGE,
                currentPage * ROWS_PER_PAGE,
            ),
        [visibleInvoices, currentPage],
    );

    const totals = React.useMemo(() => {
        const count = (value: string) =>
            invoices.filter((i) => (i.status || '').toLowerCase() === value)
                .length;

        return {
            draft: count('draft'),
            sent: count('sent'),
            paid: count('paid'),
            overdue: count('overdue'),
            value: invoices.reduce((sum, i) => sum + Number(i.total ?? 0), 0),
        };
    }, [invoices]);

    const hasInvoices = invoices.length > 0;
    const hasResults = visibleInvoices.length > 0;
    const isFiltered = status !== 'all' || Boolean(query.trim());

    const firstRowOnPage = (currentPage - 1) * ROWS_PER_PAGE + 1;
    const rangeLabel = hasResults
        ? `${firstRowOnPage}–${firstRowOnPage + pagedInvoices.length - 1}`
        : '0';

    const clearFilters = () => {
        setQuery('');
        setStatus('all');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Invoices" />

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
                        <CreateDocumentButton
                            href="/invoices/create"
                            label="Add invoice"
                            canCreate={canCreate}
                            icon={<Plus className="h-4 w-4" />}
                        />
                    </div>
                </div>

                {!canCreate ? (
                    <div className="mb-6">
                        <PrerequisiteGate
                            documentLabel="invoice"
                            blockers={prerequisites.blockers}
                        />
                    </div>
                ) : null}

                {/* Summary */}
                <div className="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
                    <StatTile
                        title={
                            activeCompanyName
                                ? `Invoices • ${activeCompanyName}`
                                : 'Invoices'
                        }
                        value={`${invoices.length}`}
                        icon={FileText}
                        sub={
                            hasInvoices
                                ? `${totals.draft} draft • ${totals.sent} sent`
                                : undefined
                        }
                    />
                    <StatTile
                        title="Paid"
                        value={`${totals.paid}`}
                        icon={CheckCircle2}
                        sub={
                            totals.overdue
                                ? `${totals.overdue} overdue`
                                : hasInvoices
                                  ? 'Nothing overdue'
                                  : undefined
                        }
                    />
                    <StatTile
                        title="Total value"
                        value={
                            <Money
                                amount={totals.value}
                                currency={activeCurrency}
                            />
                        }
                        icon={BadgeDollarSign}
                        sub={
                            hasInvoices
                                ? `Across ${invoices.length} invoice${invoices.length === 1 ? '' : 's'}`
                                : undefined
                        }
                    />
                </div>

                {/* Invoice list */}
                <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-start gap-2.5">
                        <span className="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">
                            <FileText className="h-4 w-4" />
                        </span>
                        <div>
                            <div className="text-sm font-semibold">
                                Your invoices
                            </div>
                            <div className="mt-0.5 text-xs text-muted-foreground">
                                {hasInvoices && isFiltered
                                    ? `${rangeLabel} of ${visibleInvoices.length} matching • ${invoices.length} total`
                                    : `${rangeLabel} of ${invoices.length} total`}
                            </div>
                        </div>
                    </div>

                    {hasInvoices ? (
                        <SearchField
                            value={query}
                            onChange={setQuery}
                            placeholder="Search number, client, status…"
                            className="w-full sm:w-72"
                        />
                    ) : null}
                </div>

                {hasInvoices ? (
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
                    {!hasInvoices ? (
                        <EmptyInvoices canCreate={canCreate} />
                    ) : !hasResults ? (
                        <NoSearchResults onClear={clearFilters} />
                    ) : (
                        <InvoiceTable
                            invoices={pagedInvoices}
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

/* --------------------------- Invoice table --------------------------- */

/** Rows read as tinted tiles rather than ruled lines, matching the panels. */
const cellClass = cn(
    'bg-muted/40 px-3 py-3 align-middle transition dark:bg-white/5',
    'group-hover:bg-brand-50/70 dark:group-hover:bg-brand-500/10',
);

function InvoiceTable({
    invoices,
    activeCurrency,
    sortKey,
    sortDirection,
    onSort,
}: {
    invoices: Invoice[];
    activeCurrency?: { code: string; symbol?: string | null };
    sortKey: SortKey;
    sortDirection: SortDirection;
    onSort: (key: SortKey) => void;
}) {
    return (
        <div className="-mx-1 overflow-x-auto px-1">
            <table className="w-full min-w-[46rem] border-separate border-spacing-y-1.5 text-sm">
                <thead>
                    <tr className="text-left text-xs text-muted-foreground">
                        <th className="px-3 pb-1 font-medium">Invoice</th>
                        <SortableTh
                            label="Amount"
                            active={sortKey === 'amount'}
                            direction={sortDirection}
                            onClick={() => onSort('amount')}
                        />
                        <th className="px-3 pb-1 font-medium">Status</th>
                        <SortableTh
                            label="Created"
                            active={sortKey === 'created'}
                            direction={sortDirection}
                            onClick={() => onSort('created')}
                        />
                        <th className="px-3 pb-1 text-right font-medium">
                            Actions
                        </th>
                    </tr>
                </thead>

                <tbody>
                    {invoices.map((invoice, index) => (
                        <InvoiceRow
                            key={invoice.id}
                            invoice={invoice}
                            index={index}
                            activeCurrency={activeCurrency}
                        />
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function InvoiceRow({
    invoice,
    index,
    activeCurrency,
}: {
    invoice: Invoice;
    index: number;
    activeCurrency?: { code: string; symbol?: string | null };
}) {
    const label = invoice.number ?? `Invoice #${invoice.id}`;
    const client = invoice.client_name ?? 'No client';

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
                            {client} •{' '}
                            {invoice.currency_code ??
                                activeCurrency?.code ??
                                '—'}
                        </div>
                    </div>
                </div>
            </td>

            <td className={cellClass}>
                <span className="tabular-nums">
                    <Money
                        amount={Number(invoice.total ?? 0)}
                        code={invoice.currency_code}
                        currency={activeCurrency}
                    />
                </span>
            </td>

            <td className={cellClass}>
                <StatusPill status={invoice.status} />
            </td>

            <td className={cn(cellClass, 'text-muted-foreground')}>
                {fmtDate(invoice.created_at)}
            </td>

            <td className={cn(cellClass, 'rounded-r-2xl text-right')}>
                <Link
                    href={`/invoices/${invoice.id}`}
                    className={pillButtonClass('soft', 'sm')}
                >
                    View
                </Link>
            </td>
        </motion.tr>
    );
}

/* --------------------------- Empty states --------------------------- */

function EmptyInvoices({ canCreate }: { canCreate: boolean }) {
    return (
        <div className="flex flex-col items-center px-4 py-10 text-center">
            <span className="grid h-14 w-14 place-items-center rounded-2xl bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">
                <FileText className="h-6 w-6" />
            </span>

            <div className="mt-4 text-sm font-semibold">No invoices yet</div>
            <p className="mt-1 max-w-sm text-sm text-muted-foreground">
                Bill a client for work you have delivered. Your first invoice
                takes about a minute to put together.
            </p>

            <div className="mt-5">
                <CreateDocumentButton
                    href="/invoices/create"
                    label="Add invoice"
                    canCreate={canCreate}
                    icon={<Plus className="h-4 w-4" />}
                />
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
                No invoices match the current search and status filter.
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
