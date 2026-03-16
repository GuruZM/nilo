import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import {
    AlertTriangle,
    ArrowUpRight,
    BadgeDollarSign,
    Calendar,
    CheckCircle2,
    Clock,
    FileSignature,
    Filter,
    Plus,
    RefreshCw,
    Search,
    XCircle,
} from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';
import type { PageProps } from '../../types';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types/index.d';

type QuotationStatus = 'draft' | 'sent' | 'accepted' | 'expired' | string;

interface Quotation {
    id: number;
    number: string | null;
    total: number;
    status: QuotationStatus;
    created_at: string;
    issue_date?: string | null;
    valid_until?: string | null;
    currency_code?: string | null;
    client_name?: string | null;
}

interface QuotationsIndexProps extends PageProps {
    quotations: Quotation[];
    hasActiveCompany?: boolean;
    currencies?: {
        current?: { code: string; symbol?: string | null; precision?: number };
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Quotations', href: '/quotations' },
];

function statusBadgeVariant(status: QuotationStatus) {
    const value = (status || '').toLowerCase();

    if (value === 'accepted') {
        return 'secondary';
    }

    if (value === 'expired') {
        return 'destructive';
    }

    if (value === 'sent') {
        return 'outline';
    }

    return 'outline';
}

function statusIcon(status: QuotationStatus) {
    const value = (status || '').toLowerCase();

    if (value === 'accepted') {
        return CheckCircle2;
    }

    if (value === 'expired') {
        return AlertTriangle;
    }

    if (value === 'sent') {
        return ArrowUpRight;
    }

    if (value === 'void') {
        return XCircle;
    }

    return Clock;
}

function formatMoney(
    value: number,
    opts?: { code?: string | null; symbol?: string | null; precision?: number },
) {
    const precision = opts?.precision ?? 2;
    const symbol = opts?.symbol ?? null;
    const code = opts?.code ?? null;
    const amount = Number.isFinite(value) ? value : 0;
    const prefix = symbol ? `${symbol} ` : code ? `${code} ` : '';

    return `${prefix}${amount.toFixed(precision)}`;
}

export default function QuotationsIndex({
    quotations,
    hasActiveCompany = true,
    currencies,
}: QuotationsIndexProps) {
    const [query, setQuery] = React.useState('');
    const [status, setStatus] = React.useState<'all' | QuotationStatus>('all');
    const [sort, setSort] = React.useState<
        'newest' | 'oldest' | 'amount_desc' | 'amount_asc'
    >('newest');

    const activeCurrency = currencies?.current;

    const filtered = React.useMemo(() => {
        const normalizedQuery = query.trim().toLowerCase();
        let rows = quotations;

        if (status !== 'all') {
            rows = rows.filter(
                (quotation) =>
                    (quotation.status || '').toLowerCase() ===
                    String(status).toLowerCase(),
            );
        }

        if (normalizedQuery) {
            rows = rows.filter((quotation) => {
                const haystack =
                    `${quotation.number ?? ''} ${quotation.client_name ?? ''} ${quotation.status ?? ''}`.toLowerCase();

                return haystack.includes(normalizedQuery);
            });
        }

        rows = [...rows].sort((left, right) => {
            const leftDate = new Date(left.created_at).getTime();
            const rightDate = new Date(right.created_at).getTime();

            if (sort === 'newest') {
                return rightDate - leftDate;
            }

            if (sort === 'oldest') {
                return leftDate - rightDate;
            }

            const leftAmount = Number(left.total ?? 0);
            const rightAmount = Number(right.total ?? 0);

            if (sort === 'amount_desc') {
                return rightAmount - leftAmount;
            }

            if (sort === 'amount_asc') {
                return leftAmount - rightAmount;
            }

            return 0;
        });

        return rows;
    }, [quotations, query, sort, status]);

    const stats = React.useMemo(() => {
        const all = quotations.length;
        const draft = quotations.filter(
            (quotation) => (quotation.status || '').toLowerCase() === 'draft',
        ).length;
        const sent = quotations.filter(
            (quotation) => (quotation.status || '').toLowerCase() === 'sent',
        ).length;
        const accepted = quotations.filter(
            (quotation) =>
                (quotation.status || '').toLowerCase() === 'accepted',
        ).length;
        const expired = quotations.filter(
            (quotation) => (quotation.status || '').toLowerCase() === 'expired',
        ).length;
        const totalValue = quotations.reduce(
            (sum, quotation) => sum + Number(quotation.total ?? 0),
            0,
        );

        return { all, draft, sent, accepted, expired, totalValue };
    }, [quotations]);

    const resetFilters = () => {
        setQuery('');
        setStatus('all');
        setSort('newest');
        toast.success('Filters cleared.');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Quotations" />

            <motion.div
                initial={{ opacity: 0, y: 14 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.3, ease: 'easeOut' }}
                className="mx-auto w-full px-4 py-10 sm:px-6"
            >
                <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div className="flex items-start gap-3">
                        <div className="grid h-11 w-11 place-items-center rounded-2xl bg-muted">
                            <FileSignature className="h-6 w-6 text-foreground/80" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                                Quotations
                            </h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Track drafts, sent quotations, and accepted
                                offers for your active company.
                            </p>
                        </div>
                    </div>

                    {hasActiveCompany ? (
                        <Button
                            asChild
                            className="gap-2 rounded-xl transition-transform hover:scale-[1.02] active:scale-[0.98]"
                        >
                            <Link href="/quotations/create">
                                <Plus className="h-4 w-4" />
                                Add quotation
                            </Link>
                        </Button>
                    ) : (
                        <Button
                            type="button"
                            disabled
                            className="gap-2 rounded-xl"
                        >
                            <Plus className="h-4 w-4" />
                            Add quotation
                        </Button>
                    )}
                </div>

                {!hasActiveCompany && (
                    <Card className="mb-6 rounded-2xl border-dashed bg-muted/20 p-5 shadow-sm">
                        <div className="flex flex-col gap-2">
                            <div className="text-sm font-semibold">
                                Add or select a company to use quotations
                            </div>
                            <p className="text-sm text-muted-foreground">
                                The quotations module is available, but you need
                                an active company before quotations can be
                                listed or created.
                            </p>
                            <div>
                                <Button asChild className="rounded-xl">
                                    <Link href="/companies">Manage companies</Link>
                                </Button>
                            </div>
                        </div>
                    </Card>
                )}

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    <StatCard
                        title="Total quotations"
                        value={String(stats.all)}
                        icon={FileSignature}
                    />
                    <StatCard
                        title="Drafts"
                        value={String(stats.draft)}
                        icon={Clock}
                    />
                    <StatCard
                        title="Sent"
                        value={String(stats.sent)}
                        icon={ArrowUpRight}
                    />
                    <StatCard
                        title="Accepted"
                        value={String(stats.accepted)}
                        icon={CheckCircle2}
                    />
                    <StatCard
                        title="Total value"
                        value={formatMoney(stats.totalValue, activeCurrency)}
                        icon={BadgeDollarSign}
                    />
                </div>

                <Separator className="my-6" />

                <Card className="rounded-2xl border bg-background p-4 shadow-sm">
                    <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div className="relative w-full lg:max-w-md">
                            <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={query}
                                onChange={(event) => setQuery(event.target.value)}
                                placeholder="Search by number, client, status..."
                                className="rounded-xl pl-9"
                            />
                        </div>

                        <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button
                                        variant="outline"
                                        className="gap-2 rounded-xl"
                                    >
                                        <Filter className="h-4 w-4" />
                                        Status:{' '}
                                        {status === 'all' ? 'All' : status}
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent
                                    align="end"
                                    className="w-56 rounded-xl"
                                >
                                    <DropdownMenuLabel className="text-xs text-muted-foreground">
                                        Filter by status
                                    </DropdownMenuLabel>
                                    <DropdownMenuSeparator />
                                    {[
                                        'all',
                                        'draft',
                                        'sent',
                                        'accepted',
                                        'expired',
                                    ].map((value) => (
                                        <DropdownMenuItem
                                            key={value}
                                            onClick={() =>
                                                setStatus(value as typeof status)
                                            }
                                            className="rounded-lg"
                                        >
                                            {value === 'all' ? 'All' : value}
                                        </DropdownMenuItem>
                                    ))}
                                </DropdownMenuContent>
                            </DropdownMenu>

                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button
                                        variant="outline"
                                        className="gap-2 rounded-xl"
                                    >
                                        <Calendar className="h-4 w-4" />
                                        Sort
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent
                                    align="end"
                                    className="w-56 rounded-xl"
                                >
                                    <DropdownMenuLabel className="text-xs text-muted-foreground">
                                        Sort quotations
                                    </DropdownMenuLabel>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        onClick={() => setSort('newest')}
                                        className="rounded-lg"
                                    >
                                        Newest first
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        onClick={() => setSort('oldest')}
                                        className="rounded-lg"
                                    >
                                        Oldest first
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        onClick={() => setSort('amount_desc')}
                                        className="rounded-lg"
                                    >
                                        Amount (high → low)
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        onClick={() => setSort('amount_asc')}
                                        className="rounded-lg"
                                    >
                                        Amount (low → high)
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>

                            <Button
                                variant="ghost"
                                onClick={resetFilters}
                                className="gap-2 rounded-xl"
                            >
                                <RefreshCw className="h-4 w-4" />
                                Reset
                            </Button>
                        </div>
                    </div>
                </Card>

                <Card className="mt-4 overflow-hidden rounded-2xl border bg-background shadow-sm">
                    <div className="w-full overflow-x-auto">
                        <table className="min-w-full">
                            <thead className="bg-muted/40">
                                <tr className="text-left text-xs text-muted-foreground">
                                    <th className="px-6 py-3 font-semibold">
                                        Quotation
                                    </th>
                                    <th className="px-6 py-3 font-semibold">
                                        Amount
                                    </th>
                                    <th className="px-6 py-3 font-semibold">
                                        Status
                                    </th>
                                    <th className="px-6 py-3 font-semibold">
                                        Created
                                    </th>
                                </tr>
                            </thead>

                            <tbody className="divide-y">
                                <AnimatePresence mode="popLayout">
                                    {filtered.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={4}
                                                className="px-6 py-14 text-center"
                                            >
                                                <div className="mx-auto max-w-sm">
                                                    <div className="mx-auto mb-3 grid h-12 w-12 place-items-center rounded-2xl bg-muted">
                                                        <FileSignature className="h-6 w-6 text-foreground/70" />
                                                    </div>
                                                    <div className="text-sm font-medium">
                                                        No quotations found
                                                    </div>
                                                    <div className="mt-1 text-sm text-muted-foreground">
                                                        Try changing your
                                                        filters or create your
                                                        first quotation.
                                                    </div>
                                                    <div className="mt-4">
                                                        {hasActiveCompany ? (
                                                            <Button
                                                                asChild
                                                                className="rounded-xl"
                                                            >
                                                                <Link href="/quotations/create">
                                                                    <Plus className="mr-2 h-4 w-4" />
                                                                    Add quotation
                                                                </Link>
                                                            </Button>
                                                        ) : (
                                                            <Button
                                                                type="button"
                                                                disabled
                                                                className="rounded-xl"
                                                            >
                                                                <Plus className="mr-2 h-4 w-4" />
                                                                Add quotation
                                                            </Button>
                                                        )}
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    ) : (
                                        filtered.map((quotation, index) => {
                                            const StatusIcon = statusIcon(
                                                quotation.status,
                                            );
                                            const money = formatMoney(
                                                Number(quotation.total ?? 0),
                                                {
                                                    code:
                                                        quotation.currency_code ??
                                                        activeCurrency?.code ??
                                                        null,
                                                    symbol:
                                                        activeCurrency?.symbol ??
                                                        null,
                                                    precision:
                                                        activeCurrency?.precision ??
                                                        2,
                                                },
                                            );

                                            return (
                                                <motion.tr
                                                    key={quotation.id}
                                                    initial={{ opacity: 0, y: 8 }}
                                                    animate={{ opacity: 1, y: 0 }}
                                                    exit={{ opacity: 0, y: 8 }}
                                                    transition={{
                                                        duration: 0.18,
                                                        delay: Math.min(
                                                            index * 0.02,
                                                            0.14,
                                                        ),
                                                    }}
                                                >
                                                    <td className="px-6 py-4">
                                                        <div className="flex items-center gap-3">
                                                            <div className="grid h-9 w-9 place-items-center rounded-xl bg-muted/60">
                                                                <StatusIcon className="h-4 w-4 opacity-80" />
                                                            </div>
                                                            <div className="min-w-0">
                                                                <div className="truncate text-sm font-semibold">
                                                                    {quotation.number ??
                                                                        `Quotation #${quotation.id}`}
                                                                </div>
                                                                <div className="truncate text-xs text-muted-foreground">
                                                                    {quotation.client_name ??
                                                                        'Client'}{' '}
                                                                    •{' '}
                                                                    {quotation.currency_code ??
                                                                        activeCurrency?.code ??
                                                                        '—'}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>

                                                    <td className="px-6 py-4">
                                                        <div className="text-sm font-semibold">
                                                            {money}
                                                        </div>
                                                    </td>

                                                    <td className="px-6 py-4">
                                                        <Badge
                                                            variant={
                                                                statusBadgeVariant(
                                                                    quotation.status,
                                                                ) as never
                                                            }
                                                            className={cn(
                                                                'rounded-xl capitalize',
                                                                String(
                                                                    quotation.status,
                                                                ).toLowerCase() ===
                                                                    'expired' &&
                                                                    'gap-1',
                                                            )}
                                                        >
                                                            {quotation.status}
                                                            {String(
                                                                quotation.status,
                                                            ).toLowerCase() ===
                                                                'expired' && (
                                                                <AlertTriangle className="ml-1 h-3.5 w-3.5" />
                                                            )}
                                                        </Badge>
                                                    </td>

                                                    <td className="px-6 py-4 text-sm text-muted-foreground">
                                                        {new Date(
                                                            quotation.created_at,
                                                        ).toLocaleDateString()}
                                                    </td>
                                                </motion.tr>
                                            );
                                        })
                                    )}
                                </AnimatePresence>
                            </tbody>
                        </table>
                    </div>
                </Card>
            </motion.div>
        </AppLayout>
    );
}

function StatCard({
    title,
    value,
    icon: Icon,
}: {
    title: string;
    value: string;
    icon: React.ElementType;
}) {
    return (
        <motion.div
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.22, ease: 'easeOut' }}
        >
            <Card className="rounded-2xl border bg-background p-4 shadow-sm">
                <div className="flex items-center gap-3">
                    <div className="grid h-10 w-10 place-items-center rounded-2xl bg-muted">
                        <Icon className="h-5 w-5 text-foreground/80" />
                    </div>
                    <div className="min-w-0">
                        <div className="text-xs text-muted-foreground">
                            {title}
                        </div>
                        <div className="truncate text-lg font-semibold">
                            {value}
                        </div>
                    </div>
                </div>
            </Card>
        </motion.div>
    );
}
