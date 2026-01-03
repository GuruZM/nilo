// resources/js/Pages/Dashboard.tsx
import { Head, Link, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    CheckCircle2,
    Clock3,
    FileText,
    TrendingUp,
    Users,
} from 'lucide-react';
import * as React from 'react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    Legend,
    Line,
    LineChart,
    Pie,
    PieChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types/index.d';

// shadcn
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';

type DashboardStats = {
    total_invoices: number;
    client_count: number;

    paid_count: number;
    pending_count: number;

    paid_revenue: number;
    pending_revenue: number;

    overdue_revenue: number;
    due_in_7_revenue: number;

    as_of?: string;
};

type Charts = {
    paid_vs_pending: {
        labels: string[];
        counts: number[];
        revenue: number[];
    };
    monthly_trend: {
        labels: string[];
        paid: number[];
        pending: number[];
    };
    aging: {
        overdue: { count: number; amount: number };
        due_in_7_days: { count: number; amount: number };
        due_later: { count: number; amount: number };
        no_due_date: { count: number; amount: number };
    };
};

type CalendarEvent = {
    id: number;
    title: string;
    date: string; // YYYY-MM-DD
    status: 'paid' | 'pending' | string;
    amount: number;
    currency_code: string;
    url: string;
};

type RecentInvoice = {
    id: number;
    number: string | null;
    issue_date: string;
    due_date?: string | null;
    total: number;
    status: 'paid' | 'pending' | string;
    currency_code: string;
    client?: { name: string } | null;
};

type TopClient = {
    client_id: number;
    name: string;
    pending_total: number;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
];

const PIE_COLORS = ['#0ea5e9', '#f59e0b'];

export default function Dashboard() {
    const { company, stats, charts, calendar, recent_invoices, top_clients } =
        usePage().props as any as {
            company: { id: number; name: string } | null;
            stats: DashboardStats | null;
            charts: Charts | null;
            calendar: CalendarEvent[];
            recent_invoices: RecentInvoice[];
            top_clients: TopClient[];
        };

    const currency = React.useMemo(() => {
        // we can’t guarantee a single currency across invoices,
        // but dashboard often uses company’s primary; fall back to ZMW.
        return 'ZMW';
    }, []);

    const money = (n: number, code?: string) =>
        `${code ?? currency} ${Number.isFinite(n) ? n.toFixed(2) : '0.00'}`;

    const paidPendingPie = React.useMemo(() => {
        if (!charts?.paid_vs_pending) return [];
        return charts.paid_vs_pending.labels.map((name, idx) => ({
            name,
            value: charts.paid_vs_pending.counts[idx] ?? 0,
            revenue: charts.paid_vs_pending.revenue[idx] ?? 0,
        }));
    }, [charts?.paid_vs_pending]);

    const monthlyTrend = React.useMemo(() => {
        if (!charts?.monthly_trend) return [];
        return charts.monthly_trend.labels.map((label, idx) => ({
            name: label,
            paid: charts.monthly_trend.paid[idx] ?? 0,
            pending: charts.monthly_trend.pending[idx] ?? 0,
        }));
    }, [charts?.monthly_trend]);

    const agingBars = React.useMemo(() => {
        const a = charts?.aging;
        if (!a) return [];
        return [
            {
                name: 'Overdue',
                amount: a.overdue.amount,
                count: a.overdue.count,
            },
            {
                name: 'Due ≤7d',
                amount: a.due_in_7_days.amount,
                count: a.due_in_7_days.count,
            },
            {
                name: 'Due later',
                amount: a.due_later.amount,
                count: a.due_later.count,
            },
            {
                name: 'No due',
                amount: a.no_due_date.amount,
                count: a.no_due_date.count,
            },
        ];
    }, [charts?.aging]);

    const [tableFilter, setTableFilter] = React.useState<
        'all' | 'paid' | 'pending'
    >('all');

    const filteredRecent = React.useMemo(() => {
        const list = recent_invoices ?? [];
        if (tableFilter === 'all') return list;
        return list.filter((i) => i.status === tableFilter);
    }, [recent_invoices, tableFilter]);

    const monthKey = (d: Date) =>
        `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;

    const [calMonth, setCalMonth] = React.useState(() => monthKey(new Date()));

    const monthDays = React.useMemo(() => {
        const [y, m] = calMonth.split('-').map(Number);
        const first = new Date(y, m - 1, 1);
        const last = new Date(y, m, 0); // last day of month

        // start from Monday
        const start = new Date(first);
        const day = start.getDay(); // 0 Sun ... 6 Sat
        const shift = day === 0 ? 6 : day - 1;
        start.setDate(start.getDate() - shift);

        const days: { date: Date; iso: string }[] = [];
        const cursor = new Date(start);

        while (cursor <= last || cursor.getDay() !== 1) {
            const iso = cursor.toISOString().slice(0, 10);
            days.push({ date: new Date(cursor), iso });
            cursor.setDate(cursor.getDate() + 1);
            if (days.length > 42) break; // 6 weeks max
        }

        return days;
    }, [calMonth]);

    const eventsByDate = React.useMemo(() => {
        const map = new Map<string, CalendarEvent[]>();
        (calendar ?? []).forEach((ev) => {
            if (!map.has(ev.date)) map.set(ev.date, []);
            map.get(ev.date)!.push(ev);
        });
        // sort per day: pending first, then by amount desc
        for (const [k, arr] of map) {
            arr.sort((a, b) => {
                if (a.status !== b.status)
                    return a.status === 'pending' ? -1 : 1;
                return (b.amount ?? 0) - (a.amount ?? 0);
            });
            map.set(k, arr);
        }
        return map;
    }, [calendar]);

    const monthSummary = React.useMemo(() => {
        // revenue expected in selected month from pending invoices due that month
        const [y, m] = calMonth.split('-').map(Number);
        const start = new Date(y, m - 1, 1).toISOString().slice(0, 10);
        const end = new Date(y, m, 0).toISOString().slice(0, 10);

        let pendingDue = 0;
        let overdueInMonth = 0;

        (calendar ?? []).forEach((ev) => {
            if (ev.date >= start && ev.date <= end) {
                if (ev.status === 'pending') pendingDue += ev.amount ?? 0;
            }
        });

        // overdue (today based) from stats
        overdueInMonth = stats?.overdue_revenue ?? 0;

        return {
            pendingDue,
            overdueInMonth,
        };
    }, [calMonth, calendar, stats?.overdue_revenue]);

    const goMonth = (dir: -1 | 1) => {
        const [y, m] = calMonth.split('-').map(Number);
        const d = new Date(y, m - 1, 1);
        d.setMonth(d.getMonth() + dir);
        setCalMonth(monthKey(d));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />

            <div className="mx-auto w-full px-4 py-8 sm:px-6">
                {/* Header */}
                <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div className="flex items-start gap-3">
                        <div className="grid h-11 w-11 place-items-center rounded-2xl bg-muted">
                            <TrendingUp className="h-6 w-6 text-foreground/80" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                                Dashboard
                            </h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {company ? (
                                    <>
                                        Company:{' '}
                                        <span className="font-medium text-foreground">
                                            {company.name}
                                        </span>
                                        {stats?.as_of ? (
                                            <span className="text-muted-foreground">
                                                {' '}
                                                • As of {stats.as_of}
                                            </span>
                                        ) : null}
                                    </>
                                ) : (
                                    'No active company selected.'
                                )}
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <Button asChild className="rounded-xl">
                            <Link href="/invoices/create">Create invoice</Link>
                        </Button>
                        <Button
                            variant="outline"
                            asChild
                            className="rounded-xl"
                        >
                            <Link href="/invoices">View invoices</Link>
                        </Button>
                    </div>
                </div>

                {/* Top stats */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <StatCard
                        icon={CheckCircle2}
                        title="Paid revenue"
                        value={money(stats?.paid_revenue ?? 0)}
                        sub={`${stats?.paid_count ?? 0} invoices`}
                    />
                    <StatCard
                        icon={Clock3}
                        title="Pending revenue"
                        value={money(stats?.pending_revenue ?? 0)}
                        sub={`${stats?.pending_count ?? 0} invoices`}
                        tone="amber"
                    />
                    <StatCard
                        icon={FileText}
                        title="Overdue"
                        value={money(stats?.overdue_revenue ?? 0)}
                        sub="Pending past due date"
                        tone="rose"
                    />
                    <StatCard
                        icon={Users}
                        title="Clients"
                        value={`${stats?.client_count ?? 0}`}
                        sub={`${stats?.total_invoices ?? 0} invoices total`}
                    />
                </div>

                <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-12">
                    {/* Left column: charts */}
                    <div className="space-y-6 lg:col-span-7">
                        <Card className="rounded-2xl border bg-background p-6 shadow-sm">
                            <div className="mb-4 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <div className="text-sm font-semibold">
                                        Paid vs Pending
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        Counts and revenue split.
                                    </div>
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    Paid: {money(stats?.paid_revenue ?? 0)} •
                                    Pending:{' '}
                                    {money(stats?.pending_revenue ?? 0)}
                                </div>
                            </div>

                            <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                <div className="h-[240px]">
                                    <ResponsiveContainer
                                        width="100%"
                                        height="100%"
                                    >
                                        <PieChart>
                                            <Pie
                                                data={paidPendingPie}
                                                dataKey="value"
                                                nameKey="name"
                                                cx="50%"
                                                cy="50%"
                                                outerRadius={80}
                                                label
                                            >
                                                {paidPendingPie.map(
                                                    (_, idx) => (
                                                        <Cell
                                                            key={idx}
                                                            fill={
                                                                PIE_COLORS[
                                                                    idx %
                                                                        PIE_COLORS.length
                                                                ]
                                                            }
                                                        />
                                                    ),
                                                )}
                                            </Pie>
                                            <Tooltip
                                                formatter={(
                                                    value: any,
                                                    name: any,
                                                    props: any,
                                                ) => {
                                                    const rev =
                                                        props?.payload
                                                            ?.revenue ?? 0;
                                                    return [
                                                        `${value} • ${money(rev)}`,
                                                        name,
                                                    ];
                                                }}
                                            />
                                            <Legend />
                                        </PieChart>
                                    </ResponsiveContainer>
                                </div>

                                <div className="space-y-3">
                                    <InsightRow
                                        label="Due in 7 days"
                                        value={money(
                                            stats?.due_in_7_revenue ?? 0,
                                        )}
                                        hint="Pending invoices due soon"
                                    />
                                    <InsightRow
                                        label="Overdue"
                                        value={money(
                                            stats?.overdue_revenue ?? 0,
                                        )}
                                        hint="Pending invoices past due date"
                                        danger
                                    />
                                    <InsightRow
                                        label="Pending total"
                                        value={money(
                                            stats?.pending_revenue ?? 0,
                                        )}
                                        hint="All pending invoices"
                                    />
                                    <Separator />
                                    <div className="text-xs text-muted-foreground">
                                        Tip: keep invoices in{' '}
                                        <span className="font-medium text-foreground">
                                            pending
                                        </span>{' '}
                                        until you confirm payment.
                                    </div>
                                </div>
                            </div>
                        </Card>

                        <Card className="rounded-2xl border bg-background p-6 shadow-sm">
                            <div className="mb-4">
                                <div className="text-sm font-semibold">
                                    Monthly trend
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    Last 12 months paid vs pending totals (by
                                    issue date).
                                </div>
                            </div>

                            <div className="h-[280px]">
                                <ResponsiveContainer width="100%" height="100%">
                                    <LineChart data={monthlyTrend}>
                                        <CartesianGrid strokeDasharray="3 3" />
                                        <XAxis
                                            dataKey="name"
                                            tick={{ fontSize: 12 }}
                                            interval={2}
                                        />
                                        <YAxis tick={{ fontSize: 12 }} />
                                        <Tooltip
                                            formatter={(v: any, k: any) => [
                                                money(Number(v)),
                                                String(k).toUpperCase(),
                                            ]}
                                        />
                                        <Legend />
                                        <Line
                                            type="monotone"
                                            dataKey="paid"
                                            strokeWidth={2}
                                            dot={false}
                                        />
                                        <Line
                                            type="monotone"
                                            dataKey="pending"
                                            strokeWidth={2}
                                            dot={false}
                                        />
                                    </LineChart>
                                </ResponsiveContainer>
                            </div>
                        </Card>

                        <Card className="rounded-2xl border bg-background p-6 shadow-sm">
                            <div className="mb-4">
                                <div className="text-sm font-semibold">
                                    Aging buckets
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    Where your receivables are sitting.
                                </div>
                            </div>

                            <div className="h-[260px]">
                                <ResponsiveContainer width="100%" height="100%">
                                    <BarChart data={agingBars}>
                                        <CartesianGrid strokeDasharray="3 3" />
                                        <XAxis
                                            dataKey="name"
                                            tick={{ fontSize: 12 }}
                                        />
                                        <YAxis tick={{ fontSize: 12 }} />
                                        <Tooltip
                                            formatter={(
                                                v: any,
                                                k: any,
                                                props: any,
                                            ) => {
                                                const c =
                                                    props?.payload?.count ?? 0;
                                                return [
                                                    `${money(Number(v))} • ${c} invoices`,
                                                    k,
                                                ];
                                            }}
                                        />
                                        <Legend />
                                        <Bar dataKey="amount" />
                                    </BarChart>
                                </ResponsiveContainer>
                            </div>
                        </Card>
                    </div>

                    {/* Right column: calendar + lists */}
                    <div className="space-y-6 lg:col-span-5">
                        {/* Calendar */}
                        <Card className="rounded-2xl border bg-background p-6 shadow-sm">
                            <div className="mb-4 flex items-start justify-between gap-3">
                                <div className="flex items-center gap-2">
                                    <CalendarDays className="h-5 w-5 opacity-80" />
                                    <div>
                                        <div className="text-sm font-semibold">
                                            Due dates calendar
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            Revenue to be collected (pending) +
                                            due schedule.
                                        </div>
                                    </div>
                                </div>

                                <div className="flex items-center gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="h-9 rounded-xl px-3"
                                        onClick={() => goMonth(-1)}
                                    >
                                        Prev
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="h-9 rounded-xl px-3"
                                        onClick={() => goMonth(1)}
                                    >
                                        Next
                                    </Button>
                                </div>
                            </div>

                            <div className="mb-4 rounded-2xl border bg-muted/20 p-4">
                                <div className="text-xs text-muted-foreground">
                                    Selected month
                                </div>
                                <div className="mt-1 text-sm font-semibold">
                                    {calMonth}
                                </div>
                                <div className="mt-2 grid grid-cols-2 gap-3">
                                    <MiniInfo
                                        label="Pending due this month"
                                        value={money(monthSummary.pendingDue)}
                                    />
                                    <MiniInfo
                                        label="Overdue (overall)"
                                        value={money(
                                            monthSummary.overdueInMonth,
                                        )}
                                        danger
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-7 gap-2 text-xs">
                                {[
                                    'Mon',
                                    'Tue',
                                    'Wed',
                                    'Thu',
                                    'Fri',
                                    'Sat',
                                    'Sun',
                                ].map((d) => (
                                    <div
                                        key={d}
                                        className="px-1 text-muted-foreground"
                                    >
                                        {d}
                                    </div>
                                ))}
                            </div>

                            <div className="mt-2 grid grid-cols-7 gap-2">
                                {monthDays.map(({ date, iso }) => {
                                    const evs = eventsByDate.get(iso) ?? [];
                                    const [y, m] = calMonth
                                        .split('-')
                                        .map(Number);
                                    const inMonth =
                                        date.getFullYear() === y &&
                                        date.getMonth() === m - 1;

                                    const pendingTotal = evs
                                        .filter((e) => e.status === 'pending')
                                        .reduce(
                                            (a, b) => a + (b.amount ?? 0),
                                            0,
                                        );

                                    return (
                                        <div
                                            key={iso}
                                            className={cn(
                                                'min-h-[76px] rounded-xl border p-2',
                                                !inMonth && 'opacity-50',
                                            )}
                                        >
                                            <div className="flex items-center justify-between">
                                                <div className="text-xs font-semibold">
                                                    {date.getDate()}
                                                </div>
                                                {evs.length > 0 ? (
                                                    <span className="rounded-full bg-muted px-2 py-0.5 text-[10px] font-semibold text-muted-foreground">
                                                        {evs.length}
                                                    </span>
                                                ) : null}
                                            </div>

                                            {pendingTotal > 0 ? (
                                                <div className="mt-2 rounded-lg bg-amber-500/10 px-2 py-1 text-[10px] font-semibold text-amber-700">
                                                    {money(
                                                        pendingTotal,
                                                        evs[0]?.currency_code,
                                                    )}
                                                </div>
                                            ) : evs.some(
                                                  (e) => e.status === 'paid',
                                              ) ? (
                                                <div className="mt-2 rounded-lg bg-emerald-500/10 px-2 py-1 text-[10px] font-semibold text-emerald-700">
                                                    Paid due
                                                </div>
                                            ) : null}

                                            {/* show one event quick link */}
                                            {evs[0] ? (
                                                <Link
                                                    href={evs[0].url}
                                                    className="mt-2 block truncate text-[10px] text-muted-foreground underline-offset-2 hover:underline"
                                                >
                                                    {evs[0].title}
                                                </Link>
                                            ) : null}
                                        </div>
                                    );
                                })}
                            </div>
                        </Card>

                        {/* Recent invoices */}
                        <Card className="rounded-2xl border bg-background p-6 shadow-sm">
                            <div className="mb-4 flex items-start justify-between gap-3">
                                <div>
                                    <div className="text-sm font-semibold">
                                        Recent invoices
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        Quick view of what’s happening now.
                                    </div>
                                </div>

                                <div className="flex items-center gap-2">
                                    {(['all', 'paid', 'pending'] as const).map(
                                        (k) => (
                                            <button
                                                key={k}
                                                type="button"
                                                onClick={() =>
                                                    setTableFilter(k)
                                                }
                                                className={cn(
                                                    'rounded-xl border px-3 py-1.5 text-xs font-semibold transition',
                                                    tableFilter === k
                                                        ? 'bg-foreground text-background'
                                                        : 'bg-background hover:bg-muted/40',
                                                )}
                                            >
                                                {k === 'all' ? 'All' : k}
                                            </button>
                                        ),
                                    )}
                                </div>
                            </div>

                            <div className="overflow-x-auto">
                                <table className="min-w-full text-sm">
                                    <thead>
                                        <tr className="border-b text-left text-xs text-muted-foreground">
                                            <th className="py-2 pr-4">
                                                Invoice
                                            </th>
                                            <th className="py-2 pr-4">
                                                Client
                                            </th>
                                            <th className="py-2 pr-4">Due</th>
                                            <th className="py-2 pr-4 text-right">
                                                Total
                                            </th>
                                            <th className="py-2 pr-0 text-right">
                                                Status
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {filteredRecent.length ? (
                                            filteredRecent.map((inv) => (
                                                <tr
                                                    key={inv.id}
                                                    className="border-b last:border-0"
                                                >
                                                    <td className="py-2 pr-4">
                                                        <Link
                                                            href={`/invoices/${inv.id}`}
                                                            className="font-semibold underline-offset-2 hover:underline"
                                                        >
                                                            {inv.number ??
                                                                `#${inv.id}`}
                                                        </Link>
                                                        <div className="text-xs text-muted-foreground">
                                                            Issued{' '}
                                                            {inv.issue_date}
                                                        </div>
                                                    </td>
                                                    <td className="py-2 pr-4">
                                                        {inv.client?.name ??
                                                            '—'}
                                                    </td>
                                                    <td className="py-2 pr-4">
                                                        {inv.due_date ?? '—'}
                                                    </td>
                                                    <td className="py-2 pr-4 text-right font-semibold">
                                                        {money(
                                                            inv.total,
                                                            inv.currency_code,
                                                        )}
                                                    </td>
                                                    <td className="py-2 pr-0 text-right">
                                                        <StatusPill
                                                            status={inv.status}
                                                        />
                                                    </td>
                                                </tr>
                                            ))
                                        ) : (
                                            <tr>
                                                <td
                                                    colSpan={5}
                                                    className="py-6 text-center text-sm text-muted-foreground"
                                                >
                                                    No invoices found.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>

                            <div className="mt-4 flex items-center justify-between">
                                <div className="text-xs text-muted-foreground">
                                    Showing {filteredRecent.length} invoices
                                </div>
                                <Button
                                    variant="outline"
                                    asChild
                                    className="rounded-xl"
                                >
                                    <Link href="/invoices">Open invoices</Link>
                                </Button>
                            </div>
                        </Card>

                        {/* Top clients */}
                        <Card className="rounded-2xl border bg-background p-6 shadow-sm">
                            <div className="mb-4">
                                <div className="text-sm font-semibold">
                                    Top clients (pending)
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    Biggest outstanding balances right now.
                                </div>
                            </div>

                            <div className="space-y-3">
                                {(top_clients ?? []).length ? (
                                    top_clients.map((c) => (
                                        <div
                                            key={c.client_id}
                                            className="flex items-center justify-between rounded-xl border p-3"
                                        >
                                            <div className="min-w-0">
                                                <div className="truncate text-sm font-semibold">
                                                    {c.name}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    Outstanding
                                                </div>
                                            </div>
                                            <div className="text-sm font-semibold">
                                                {money(c.pending_total)}
                                            </div>
                                        </div>
                                    ))
                                ) : (
                                    <div className="rounded-xl border p-4 text-sm text-muted-foreground">
                                        No pending balances yet.
                                    </div>
                                )}
                            </div>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

function StatCard({
    icon: Icon,
    title,
    value,
    sub,
    tone,
}: {
    icon: any;
    title: string;
    value: string;
    sub: string;
    tone?: 'amber' | 'rose';
}) {
    const toneCls =
        tone === 'amber'
            ? 'bg-amber-500/10 text-amber-700'
            : tone === 'rose'
              ? 'bg-rose-500/10 text-rose-700'
              : 'bg-emerald-500/10 text-emerald-700';

    return (
        <Card className="rounded-2xl border bg-background p-5 shadow-sm">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <div className="text-xs font-semibold text-muted-foreground">
                        {title}
                    </div>
                    <div className="mt-1 truncate text-2xl font-semibold tracking-tight">
                        {value}
                    </div>
                    <div className="mt-1 text-xs text-muted-foreground">
                        {sub}
                    </div>
                </div>
                <div
                    className={cn(
                        'grid h-10 w-10 place-items-center rounded-2xl',
                        toneCls,
                    )}
                >
                    <Icon className="h-5 w-5" />
                </div>
            </div>
        </Card>
    );
}

function StatusPill({ status }: { status: string }) {
    const isPaid = status === 'paid';
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold capitalize',
                isPaid
                    ? 'bg-emerald-500/10 text-emerald-700'
                    : 'bg-amber-500/10 text-amber-700',
            )}
        >
            {isPaid ? (
                <CheckCircle2 className="h-3.5 w-3.5" />
            ) : (
                <Clock3 className="h-3.5 w-3.5" />
            )}
            {status}
        </span>
    );
}

function InsightRow({
    label,
    value,
    hint,
    danger,
}: {
    label: string;
    value: string;
    hint: string;
    danger?: boolean;
}) {
    return (
        <div className="rounded-xl border p-3">
            <div className="flex items-center justify-between">
                <div className="text-sm font-semibold">{label}</div>
                <div
                    className={cn(
                        'text-sm font-semibold',
                        danger && 'text-rose-700',
                    )}
                >
                    {value}
                </div>
            </div>
            <div className="mt-1 text-xs text-muted-foreground">{hint}</div>
        </div>
    );
}

function MiniInfo({
    label,
    value,
    danger,
}: {
    label: string;
    value: string;
    danger?: boolean;
}) {
    return (
        <div className="rounded-xl border bg-background p-3">
            <div className="text-[11px] text-muted-foreground">{label}</div>
            <div
                className={cn(
                    'mt-1 text-sm font-semibold',
                    danger && 'text-rose-700',
                )}
            >
                {value}
            </div>
        </div>
    );
}
