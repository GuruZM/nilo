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
    FileText,
    Filter,
    Plus,
    RefreshCw,
    Search,
    XCircle,
} from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';
import type { PageProps } from '../../types';

// shadcn
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

type InvoiceStatus = 'draft' | 'sent' | 'paid' | 'overdue' | 'void' | string;

interface Invoice {
    id: number;
    number: string | null;
    total: number; // use `total` on backend; keep `amount` if you want, but total is better
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

    // optional (if you already share currencies in middleware)
    currencies?: {
        current?: { code: string; symbol?: string | null; precision?: number };
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Invoices', href: '/invoices' },
];

function statusBadgeVariant(status: InvoiceStatus) {
    const s = (status || '').toLowerCase();
    if (s === 'paid') return 'secondary';
    if (s === 'overdue') return 'destructive';
    if (s === 'sent') return 'outline';
    if (s === 'void') return 'destructive';
    return 'outline';
}

function statusIcon(status: InvoiceStatus) {
    const s = (status || '').toLowerCase();
    if (s === 'paid') return CheckCircle2;
    if (s === 'overdue') return AlertTriangle;
    if (s === 'void') return XCircle;
    if (s === 'sent') return Clock;
    return FileText;
}

function formatMoney(
    value: number,
    opts?: { code?: string | null; symbol?: string | null; precision?: number },
) {
    const precision = opts?.precision ?? 2;
    const symbol = opts?.symbol ?? null;
    const code = opts?.code ?? null;

    const n = Number.isFinite(value) ? value : 0;

    // Prefer symbol, fall back to code, then nothing
    const prefix = symbol ? `${symbol} ` : code ? `${code} ` : '';

    return `${prefix}${n.toFixed(precision)}`;
}

export default function InvoicesIndex({
    invoices,
    hasActiveCompany = true,
    currencies,
}: InvoicesIndexProps) {
    const [query, setQuery] = React.useState('');
    const [status, setStatus] = React.useState<'all' | InvoiceStatus>('all');
    const [sort, setSort] = React.useState<
        'newest' | 'oldest' | 'amount_desc' | 'amount_asc'
    >('newest');

    const activeCurrency = currencies?.current;

    const filtered = React.useMemo(() => {
        const q = query.trim().toLowerCase();

        let rows = invoices;

        if (status !== 'all') {
            rows = rows.filter(
                (i) =>
                    (i.status || '').toLowerCase() ===
                    String(status).toLowerCase(),
            );
        }

        if (q) {
            rows = rows.filter((i) => {
                const hay =
                    `${i.number ?? ''} ${i.client_name ?? ''} ${i.status ?? ''}`.toLowerCase();
                return hay.includes(q);
            });
        }

        rows = [...rows].sort((a, b) => {
            const aDate = new Date(a.created_at).getTime();
            const bDate = new Date(b.created_at).getTime();

            if (sort === 'newest') return bDate - aDate;
            if (sort === 'oldest') return aDate - bDate;

            const aAmt = Number(a.total ?? 0);
            const bAmt = Number(b.total ?? 0);

            if (sort === 'amount_desc') return bAmt - aAmt;
            if (sort === 'amount_asc') return aAmt - bAmt;

            return 0;
        });

        return rows;
    }, [invoices, query, status, sort]);

    const stats = React.useMemo(() => {
        const all = invoices.length;
        const draft = invoices.filter(
            (i) => (i.status || '').toLowerCase() === 'draft',
        ).length;
        const sent = invoices.filter(
            (i) => (i.status || '').toLowerCase() === 'sent',
        ).length;
        const paid = invoices.filter(
            (i) => (i.status || '').toLowerCase() === 'paid',
        ).length;
        const overdue = invoices.filter(
            (i) => (i.status || '').toLowerCase() === 'overdue',
        ).length;

        const totalValue = invoices.reduce(
            (sum, i) => sum + Number(i.total ?? 0),
            0,
        );

        return { all, draft, sent, paid, overdue, totalValue };
    }, [invoices]);

    const resetFilters = () => {
        setQuery('');
        setStatus('all');
        setSort('newest');
        toast.success('Filters cleared.');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Invoices" />

            <motion.div
                initial={{ opacity: 0, y: 14 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.3, ease: 'easeOut' }}
                className="mx-auto w-full px-4 py-10 sm:px-6"
            >
                {/* Header */}
                <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div className="flex items-start gap-3">
                        <div className="grid h-11 w-11 place-items-center rounded-2xl bg-muted">
                            <FileText className="h-6 w-6 text-foreground/80" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                                Invoices
                            </h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Track drafts, sent invoices, and payments — all
                                scoped to your active company.
                            </p>
                        </div>
                    </div>

                    <Button
                        asChild
                        disabled={!hasActiveCompany}
                        className="gap-2 rounded-xl transition-transform hover:scale-[1.02] active:scale-[0.98]"
                    >
                        <Link href="/invoices/create">
                            <Plus className="h-4 w-4" />
                            Add invoice
                        </Link>
                    </Button>
                </div>

                {!hasActiveCompany && (
                    <Card className="mb-6 rounded-2xl border-dashed bg-muted/20 p-5 shadow-sm">
                        <div className="flex flex-col gap-2">
                            <div className="text-sm font-semibold">
                                Add or select a company to use invoices
                            </div>
                            <p className="text-sm text-muted-foreground">
                                The invoices module is available, but you need
                                an active company before invoices can be listed
                                or created.
                            </p>
                            <div>
                                <Button asChild className="rounded-xl">
                                    <Link href="/companies">Manage companies</Link>
                                </Button>
                            </div>
                        </div>
                    </Card>
                )}

                {/* Quick stats */}
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    <StatCard
                        title="Total invoices"
                        value={String(stats.all)}
                        icon={FileText}
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
                        title="Paid"
                        value={String(stats.paid)}
                        icon={CheckCircle2}
                    />
                    <StatCard
                        title="Total value"
                        value={formatMoney(stats.totalValue, activeCurrency)}
                        icon={BadgeDollarSign}
                    />
                </div>

                <Separator className="my-6" />

                {/* Filters */}
                <Card className="rounded-2xl border bg-background p-4 shadow-sm">
                    <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div className="relative w-full lg:max-w-md">
                            <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                                placeholder="Search by number, client, status..."
                                className="rounded-xl pl-9"
                            />
                        </div>

                        <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                            {/* Status */}
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button
                                        variant="outline"
                                        className="gap-2 rounded-xl"
                                    >
                                        <Filter className="h-4 w-4" />
                                        Status:{' '}
                                        {status === 'all' ? 'All' : status}
                                        {/* <ChevronDown className="h-4 w-4 opacity-70" /> */}
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
                                        'paid',
                                        'overdue',
                                        'void',
                                    ].map((s) => (
                                        <DropdownMenuItem
                                            key={s}
                                            onClick={() => setStatus(s as any)}
                                            className="rounded-lg"
                                        >
                                            {s === 'all' ? 'All' : s}
                                        </DropdownMenuItem>
                                    ))}
                                </DropdownMenuContent>
                            </DropdownMenu>

                            {/* Sort */}
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button
                                        variant="outline"
                                        className="gap-2 rounded-xl"
                                    >
                                        <Calendar className="h-4 w-4" />
                                        Sort
                                        {/* <ChevronDown className="h-4 w-4 opacity-70" />  */}
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent
                                    align="end"
                                    className="w-56 rounded-xl"
                                >
                                    <DropdownMenuLabel className="text-xs text-muted-foreground">
                                        Sort invoices
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

                {/* Table */}
                <Card className="mt-4 overflow-hidden rounded-2xl border bg-background shadow-sm">
                    <div className="w-full overflow-x-auto">
                        <table className="min-w-full">
                            <thead className="bg-muted/40">
                                <tr className="text-left text-xs text-muted-foreground">
                                    <th className="px-6 py-3 font-semibold">
                                        Invoice
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
                                    <th className="px-6 py-3 text-right font-semibold">
                                        Action
                                    </th>
                                </tr>
                            </thead>

                            <tbody className="divide-y">
                                <AnimatePresence mode="popLayout">
                                    {filtered.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={5}
                                                className="px-6 py-14 text-center"
                                            >
                                                <div className="mx-auto max-w-sm">
                                                    <div className="mx-auto mb-3 grid h-12 w-12 place-items-center rounded-2xl bg-muted">
                                                        <FileText className="h-6 w-6 text-foreground/70" />
                                                    </div>
                                                    <div className="text-sm font-medium">
                                                        No invoices found
                                                    </div>
                                                    <div className="mt-1 text-sm text-muted-foreground">
                                                        Try changing your
                                                        filters or create your
                                                        first invoice.
                                                    </div>
                                                    <div className="mt-4">
                                                        <Button
                                                            asChild
                                                            className="rounded-xl"
                                                        >
                                                            <Link href="/invoices/create">
                                                                <Plus className="mr-2 h-4 w-4" />
                                                                Add invoice
                                                            </Link>
                                                        </Button>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    ) : (
                                        filtered.map((invoice, i) => {
                                            const StatusIcon = statusIcon(
                                                invoice.status,
                                            );
                                            const money = formatMoney(
                                                Number(invoice.total ?? 0),
                                                {
                                                    code:
                                                        invoice.currency_code ??
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
                                                    key={invoice.id}
                                                    initial={{
                                                        opacity: 0,
                                                        y: 8,
                                                    }}
                                                    animate={{
                                                        opacity: 1,
                                                        y: 0,
                                                    }}
                                                    exit={{ opacity: 0, y: 8 }}
                                                    transition={{
                                                        duration: 0.18,
                                                        delay: Math.min(
                                                            i * 0.02,
                                                            0.14,
                                                        ),
                                                    }}
                                                    className="group"
                                                >
                                                    <td className="px-6 py-4">
                                                        <div className="flex items-center gap-3">
                                                            <div className="grid h-9 w-9 place-items-center rounded-xl bg-muted/60">
                                                                <StatusIcon className="h-4 w-4 opacity-80" />
                                                            </div>
                                                            <div className="min-w-0">
                                                                <div className="truncate text-sm font-semibold">
                                                                    {invoice.number ??
                                                                        `Invoice #${invoice.id}`}
                                                                </div>
                                                                <div className="truncate text-xs text-muted-foreground">
                                                                    {invoice.client_name ??
                                                                        'Client'}{' '}
                                                                    •{' '}
                                                                    {invoice.currency_code ??
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
                                                                    invoice.status,
                                                                ) as any
                                                            }
                                                            className={cn(
                                                                'rounded-xl capitalize',
                                                                invoice.status ===
                                                                    'overdue' &&
                                                                    'gap-1',
                                                            )}
                                                        >
                                                            {invoice.status}
                                                            {String(
                                                                invoice.status,
                                                            ).toLowerCase() ===
                                                                'overdue' && (
                                                                <AlertTriangle className="ml-1 h-3.5 w-3.5" />
                                                            )}
                                                        </Badge>
                                                    </td>

                                                    <td className="px-6 py-4 text-sm text-muted-foreground">
                                                        {new Date(
                                                            invoice.created_at,
                                                        ).toLocaleDateString()}
                                                    </td>

                                                    <td className="px-6 py-4 text-right">
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            className="rounded-xl transition-transform hover:scale-[1.02] active:scale-[0.98]"
                                                            asChild
                                                        >
                                                            <Link
                                                                href={`/invoices/${invoice.id}`}
                                                            >
                                                                View
                                                                <ArrowUpRight className="ml-2 h-4 w-4 opacity-80" />
                                                            </Link>
                                                        </Button>
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
