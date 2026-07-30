import AppLayout from '@/layouts/app-layout';
import { Head, Link, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import {
    BadgeDollarSign,
    CheckCircle2,
    Plus,
    ReceiptText,
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

type CreditNoteStatus = 'draft' | 'issued' | 'void' | string;

interface CreditNote {
    id: number;
    number: string | null;
    client_name?: string | null;
    invoice_number?: string | null;
    issue_date?: string | null;
    currency_code?: string | null;
    total: number;
    status: CreditNoteStatus;
    reason?: string | null;
}

/**
 * There is no `prerequisites` prop here, and no gate rendered for one. A credit
 * note is raised against an invoice, and that invoice already proves the
 * company, client, currency and template exist — so the only thing that can
 * block the list is having no invoices, which the empty state covers.
 */
interface CreditNotesIndexProps extends PageProps {
    creditNotes: CreditNote[];
    hasActiveCompany?: boolean;

    currencies?: {
        current?: { code: string; symbol?: string | null; precision?: number };
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Credit notes', href: '/credit-notes' },
];

const STATUS_FILTERS = ['all', 'draft', 'issued', 'void'] as const;

type SortKey = 'issued' | 'amount';
type SortDirection = 'asc' | 'desc';

/** Credit notes shown per page; the full list is filtered and sorted client-side. */
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

export default function CreditNotesIndex({
    creditNotes,
    hasActiveCompany = true,
    currencies,
}: CreditNotesIndexProps) {
    const activeCompanyName = useActiveCompanyName();
    const activeCurrency = currencies?.current;

    const [query, setQuery] = React.useState('');
    const [status, setStatus] = React.useState<'all' | CreditNoteStatus>('all');
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

    const visibleCreditNotes = React.useMemo(() => {
        const needle = query.trim().toLowerCase();

        let rows = creditNotes;

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
                    note.reason,
                    note.status,
                ].some((field) => (field ?? '').toLowerCase().includes(needle)),
            );
        }

        return [...rows].sort((a, b) => {
            const delta =
                sortKey === 'amount'
                    ? Number(a.total ?? 0) - Number(b.total ?? 0)
                    : new Date(a.issue_date ?? 0).getTime() -
                      new Date(b.issue_date ?? 0).getTime();

            return sortDirection === 'asc' ? delta : -delta;
        });
    }, [creditNotes, query, status, sortKey, sortDirection]);

    const pageCount = Math.max(
        1,
        Math.ceil(visibleCreditNotes.length / ROWS_PER_PAGE),
    );

    /** Filtering or sorting can shrink the list past the current page. */
    const currentPage = Math.min(page, pageCount);

    const pagedCreditNotes = React.useMemo(
        () =>
            visibleCreditNotes.slice(
                (currentPage - 1) * ROWS_PER_PAGE,
                currentPage * ROWS_PER_PAGE,
            ),
        [visibleCreditNotes, currentPage],
    );

    const totals = React.useMemo(() => {
        const count = (value: string) =>
            creditNotes.filter((n) => (n.status || '').toLowerCase() === value)
                .length;

        return {
            draft: count('draft'),
            issued: count('issued'),
            void: count('void'),

            /**
             * Only issued notes have actually reduced an invoice, so the value
             * tile counts those alone — drafts and voids move no money.
             */
            credited: creditNotes
                .filter((n) => (n.status || '').toLowerCase() === 'issued')
                .reduce((sum, n) => sum + Number(n.total ?? 0), 0),
        };
    }, [creditNotes]);

    const hasCreditNotes = creditNotes.length > 0;
    const hasResults = visibleCreditNotes.length > 0;
    const isFiltered = status !== 'all' || Boolean(query.trim());

    const firstRowOnPage = (currentPage - 1) * ROWS_PER_PAGE + 1;
    const rangeLabel = hasResults
        ? `${firstRowOnPage}–${firstRowOnPage + pagedCreditNotes.length - 1}`
        : '0';

    const clearFilters = () => {
        setQuery('');
        setStatus('all');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Credit notes" />

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
                        <Link
                            href="/credit-notes/create"
                            className={pillButtonClass('solid', 'sm')}
                        >
                            <Plus className="h-4 w-4" />
                            Add credit note
                        </Link>
                    </div>
                </div>

                {/* Summary */}
                <div className="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
                    <StatTile
                        title={
                            activeCompanyName
                                ? `Credit notes • ${activeCompanyName}`
                                : 'Credit notes'
                        }
                        value={`${creditNotes.length}`}
                        icon={ReceiptText}
                        sub={
                            hasCreditNotes
                                ? `${totals.draft} draft • ${totals.issued} issued`
                                : undefined
                        }
                    />
                    <StatTile
                        title="Issued"
                        value={`${totals.issued}`}
                        icon={CheckCircle2}
                        sub={
                            totals.void
                                ? `${totals.void} void`
                                : hasCreditNotes
                                  ? 'Nothing voided'
                                  : undefined
                        }
                    />
                    <StatTile
                        title="Credited"
                        value={
                            <Money
                                amount={totals.credited}
                                currency={activeCurrency}
                            />
                        }
                        icon={BadgeDollarSign}
                        sub={
                            hasCreditNotes
                                ? `Across ${totals.issued} issued note${totals.issued === 1 ? '' : 's'}`
                                : undefined
                        }
                    />
                </div>

                {/* Credit note list */}
                <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-start gap-2.5">
                        <span className="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">
                            <ReceiptText className="h-4 w-4" />
                        </span>
                        <div>
                            <div className="text-sm font-semibold">
                                Your credit notes
                            </div>
                            <div className="mt-0.5 text-xs text-muted-foreground">
                                {hasCreditNotes && isFiltered
                                    ? `${rangeLabel} of ${visibleCreditNotes.length} matching • ${creditNotes.length} total`
                                    : `${rangeLabel} of ${creditNotes.length} total`}
                            </div>
                        </div>
                    </div>

                    {hasCreditNotes ? (
                        <SearchField
                            value={query}
                            onChange={setQuery}
                            placeholder="Search number, invoice, client, reason…"
                            className="w-full sm:w-72"
                        />
                    ) : null}
                </div>

                {hasCreditNotes ? (
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
                    {!hasCreditNotes ? (
                        <EmptyCreditNotes />
                    ) : !hasResults ? (
                        <NoSearchResults onClear={clearFilters} />
                    ) : (
                        <CreditNoteTable
                            creditNotes={pagedCreditNotes}
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

/* --------------------------- Credit note table --------------------------- */

/** Rows read as tinted tiles rather than ruled lines, matching the panels. */
const cellClass = cn(
    'bg-muted/40 px-3 py-3 align-middle transition dark:bg-white/5',
    'group-hover:bg-brand-50/70 dark:group-hover:bg-brand-500/10',
);

function CreditNoteTable({
    creditNotes,
    activeCurrency,
    sortKey,
    sortDirection,
    onSort,
}: {
    creditNotes: CreditNote[];
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
                        <th className="px-3 pb-1 font-medium">Credit note</th>
                        <th className="px-3 pb-1 font-medium">
                            Against invoice
                        </th>
                        <SortableTh
                            label="Amount"
                            active={sortKey === 'amount'}
                            direction={sortDirection}
                            onClick={() => onSort('amount')}
                        />
                        <th className="px-3 pb-1 font-medium">Status</th>
                        <SortableTh
                            label="Issued"
                            active={sortKey === 'issued'}
                            direction={sortDirection}
                            onClick={() => onSort('issued')}
                        />
                        <th className="px-3 pb-1 text-right font-medium">
                            Actions
                        </th>
                    </tr>
                </thead>

                <tbody>
                    {creditNotes.map((creditNote, index) => (
                        <CreditNoteRow
                            key={creditNote.id}
                            creditNote={creditNote}
                            index={index}
                            activeCurrency={activeCurrency}
                        />
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function CreditNoteRow({
    creditNote,
    index,
    activeCurrency,
}: {
    creditNote: CreditNote;
    index: number;
    activeCurrency?: { code: string; symbol?: string | null };
}) {
    const label = creditNote.number ?? `Credit note #${creditNote.id}`;
    const client = creditNote.client_name ?? 'No client';

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
                            {creditNote.currency_code ??
                                activeCurrency?.code ??
                                '—'}
                        </div>
                    </div>
                </div>
            </td>

            <td className={cellClass}>
                <div className="min-w-0">
                    <div className="truncate font-medium">
                        {creditNote.invoice_number ?? '—'}
                    </div>
                    <div className="mt-0.5 truncate text-xs text-muted-foreground">
                        {creditNote.reason?.trim() || 'No reason given'}
                    </div>
                </div>
            </td>

            <td className={cellClass}>
                <span className="tabular-nums">
                    <Money
                        amount={Number(creditNote.total ?? 0)}
                        code={creditNote.currency_code}
                        currency={activeCurrency}
                    />
                </span>
            </td>

            <td className={cellClass}>
                <StatusPill status={creditNote.status} />
            </td>

            <td className={cn(cellClass, 'text-muted-foreground')}>
                {fmtDate(creditNote.issue_date)}
            </td>

            <td className={cn(cellClass, 'rounded-r-2xl text-right')}>
                <Link
                    href={`/credit-notes/${creditNote.id}`}
                    className={pillButtonClass('soft', 'sm')}
                >
                    View
                </Link>
            </td>
        </motion.tr>
    );
}

/* --------------------------- Empty states --------------------------- */

function EmptyCreditNotes() {
    return (
        <div className="flex flex-col items-center px-4 py-10 text-center">
            <span className="grid h-14 w-14 place-items-center rounded-2xl bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">
                <ReceiptText className="h-6 w-6" />
            </span>

            <div className="mt-4 text-sm font-semibold">
                No credit notes yet
            </div>
            <p className="mt-1 max-w-sm text-sm text-muted-foreground">
                Credit an invoice when work is returned, cancelled or
                overbilled. The credit reduces what the invoice still owes.
            </p>

            <div className="mt-5">
                <Link
                    href="/credit-notes/create"
                    className={pillButtonClass('solid', 'sm')}
                >
                    <Plus className="h-4 w-4" />
                    Add credit note
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
                No credit notes match the current search and status filter.
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
