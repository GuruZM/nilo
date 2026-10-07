// resources/js/Pages/Dashboard.tsx
import { Head, Link, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    Clock3,
    PieChart as PieChartIcon,
    TrendingUp,
    Users,
    Wallet,
} from 'lucide-react';
import * as React from 'react';
import {
    Area,
    AreaChart,
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
import { type BreadcrumbItem, type SharedData } from '@/types/index.d';
import { toast } from 'sonner';

import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

import {
    Chip,
    IconButton,
    InitialsAvatar,
    Panel,
    PanelHeader,
    pillButtonClass,
    Ring,
    SoftTile,
    StatTile,
    StatusPill,
} from '@/components/dashboard/primitives';
import { FxNote, type FxMeta } from '@/components/fx-note';
import {
    formatMoneyText,
    useActiveCurrency,
    useMoney,
} from '@/components/money';
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

    currency_code?: string | null;
    precision?: number | null;
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
    stat_series: {
        labels: string[];
        paid: number[];
        pending: number[];
        overdue: number[];
        clients: number[];
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

type TopClient = {
    client_id: number;
    name: string;
    pending_total: number;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
];

// Paid = deep brand blue, Pending = light brand blue (matches the hero palette)
const PIE_COLORS = ['#00417d', '#7fadd8'];
const CHART_PAID = '#00417d';
const CHART_PENDING = '#7fadd8';
const STAT_AMBER = '#d97706';
const STAT_ROSE = '#e11d48';

/** Softened recharts tooltip so it matches the borderless card language. */
const TOOLTIP_STYLE = {
    borderRadius: 14,
    border: 'none',
    boxShadow: '0 12px 32px -12px rgb(16 24 40 / 0.24)',
    fontSize: 12,
    padding: '8px 12px',
} as const;

const LEGEND_STYLE = { fontSize: 11 } as const;

const MONTH_NAMES = [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
];

function monthLabel(year: number, month: number): string {
    return `${MONTH_NAMES[month - 1] ?? ''} ${year}`;
}

/**
 * Calendar cells are only ~40px wide once the dashboard is pinned to one
 * viewport, so amounts are abbreviated and the currency code is dropped.
 */
function compactAmount(n: number): string {
    if (!Number.isFinite(n)) {
        return '0';
    }
    if (Math.abs(n) >= 1_000_000) {
        return `${(n / 1_000_000).toFixed(1)}m`;
    }
    if (Math.abs(n) >= 1_000) {
        return `${(n / 1_000).toFixed(1)}k`;
    }
    return String(Math.round(n));
}

export default function Dashboard() {
    const page = usePage<
        SharedData & {
            company: { id: number; name: string } | null;
            stats: DashboardStats | null;
            charts: Charts | null;
            calendar: CalendarEvent[];
            top_clients: TopClient[];
            fx: FxMeta | null;
        }
    >();
    const { company, stats, charts, calendar, top_clients, fx } = page.props;

    // Show the welcome toast after a user verifies their email.
    React.useEffect(() => {
        const success = page.props?.flash?.success;

        if (success) {
            toast.success(success);
        }
    }, [page.props?.flash?.success]);

    // ---------------------------
    // Currency + formatting
    // ---------------------------
    /**
     * The server states which currency it converted the figures into. With no
     * company yet there are no figures to convert, so fall back to the currency
     * the user picked in the switcher rather than to a hardcoded default.
     */
    const activeCurrency = useActiveCurrency();

    const baseCurrency = stats?.currency_code ?? activeCurrency.code;
    const precision = Number.isFinite(Number(stats?.precision))
        ? Number(stats?.precision)
        : Number.isFinite(Number(activeCurrency.precision))
          ? Number(activeCurrency.precision)
          : 2;

    const currency = React.useMemo(
        () => ({ code: baseCurrency, precision }),
        [baseCurrency, precision],
    );

    /**
     * Plain string form, for chart internals (tooltips) that render into SVG
     * and cannot take markup.
     */
    const moneyText = React.useCallback(
        (n: number, code?: string) =>
            formatMoneyText(n, { ...currency, code: code ?? currency.code }),
        [currency],
    );

    /** Markup form — figure plus superscript currency code. */
    const money = useMoney(currency);

    // ---------------------------
    // Charts prep
    // ---------------------------
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

    // ---------------------------
    // Calendar month grid + drawer
    // ---------------------------
    const todayIso = React.useMemo(
        () => new Date().toISOString().slice(0, 10),
        [],
    );

    const monthKey = (d: Date) =>
        `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;

    const [calMonth, setCalMonth] = React.useState(() => monthKey(new Date()));
    const [selectedDay, setSelectedDay] = React.useState<string | null>(null);

    const monthDays = React.useMemo(() => {
        const [y, m] = calMonth.split('-').map(Number);
        const first = new Date(y, m - 1, 1);
        const last = new Date(y, m, 0);

        // start from Monday
        const start = new Date(first);
        const day = start.getDay();
        const shift = day === 0 ? 6 : day - 1;
        start.setDate(start.getDate() - shift);

        const days: { date: Date; iso: string }[] = [];
        const cursor = new Date(start);

        while (cursor <= last || cursor.getDay() !== 1) {
            const iso = cursor.toISOString().slice(0, 10);
            days.push({ date: new Date(cursor), iso });
            cursor.setDate(cursor.getDate() + 1);
            if (days.length > 42) break;
        }

        return days;
    }, [calMonth]);

    const eventsByDate = React.useMemo(() => {
        const map = new Map<string, CalendarEvent[]>();
        (calendar ?? []).forEach((ev) => {
            if (!map.has(ev.date)) map.set(ev.date, []);
            map.get(ev.date)!.push(ev);
        });

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

    const goMonth = (dir: -1 | 1) => {
        const [y, m] = calMonth.split('-').map(Number);
        const d = new Date(y, m - 1, 1);
        d.setMonth(d.getMonth() + dir);
        setCalMonth(monthKey(d));
        setSelectedDay(null);
    };

    const selectedEvents = React.useMemo(() => {
        if (!selectedDay) return [];
        return eventsByDate.get(selectedDay) ?? [];
    }, [selectedDay, eventsByDate]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />

            {/*
                At lg+ the whole dashboard is pinned to one viewport height so
                nothing scrolls. The chrome above is fixed at 88px: 16px of
                AppContent margin plus the 72px header (mt-2 + h-16). py-6 is
                inside this box, not added to it. Below lg this falls back to
                normal document flow and the page scrolls as usual.
             */}
            <div className="flex w-full flex-col gap-4 py-3 lg:h-[calc(100svh-88px)] lg:overflow-hidden">
                <DashboardHeader company={company} />

                <StatsGrid
                    stats={stats}
                    money={money}
                    statSeries={charts?.stat_series}
                />

                <FxNote fx={fx} />

                <div className="grid grid-cols-1 gap-4 lg:min-h-0 lg:flex-1 lg:grid-cols-12">
                    <div className="flex flex-col gap-4 lg:col-span-7 lg:min-h-0">
                        <PaidPendingCard
                            paidPendingPie={paidPendingPie}
                            stats={stats}
                            money={money}
                            moneyText={moneyText}
                        />

                        <MonthlyTrendCard
                            monthlyTrend={monthlyTrend}
                            moneyText={moneyText}
                        />
                    </div>

                    {/*
                        Top clients takes a fixed slice off the top and scrolls
                        internally; the calendar claims whatever height is left.
                     */}
                    <div className="flex flex-col gap-4 lg:col-span-5 lg:min-h-0 lg:gap-3">
                        <TopClientsCard
                            topClients={top_clients ?? []}
                            money={money}
                        />

                        <CalendarCard
                            calMonth={calMonth}
                            goMonth={goMonth}
                            monthDays={monthDays}
                            eventsByDate={eventsByDate}
                            selectedDay={selectedDay}
                            setSelectedDay={setSelectedDay}
                            selectedEvents={selectedEvents}
                            money={money}
                            todayIso={todayIso}
                        />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

/* -----------------------------------------
   Header
------------------------------------------ */
function DashboardHeader({
    company,
}: {
    company: { id: number; name: string } | null;
}) {
    return (
        // The page title lives in the breadcrumb up in the app header, so it is
        // deliberately not repeated here.
        <div className="flex shrink-0 flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h1 className="sr-only">Dashboard</h1>

            <div className="flex min-w-0 flex-wrap items-center gap-2">
                {company ? (
                    <Chip interactive={false} className="normal-case">
                        {company.name}
                    </Chip>
                ) : null}
            </div>

            <div className="flex items-center gap-2">
                <Link
                    href="/invoices/create"
                    className={pillButtonClass('solid')}
                >
                    Create invoice
                </Link>
            </div>
        </div>
    );
}

/* -----------------------------------------
   Stats Grid
------------------------------------------ */
function StatsGrid({
    stats,
    money,
    statSeries,
}: {
    stats: DashboardStats | null;
    money: (n: number, code?: string) => React.ReactNode;
    statSeries?: Charts['stat_series'];
}) {
    const intFmt = React.useCallback(
        (n: number) => Math.round(n).toLocaleString(),
        [],
    );

    return (
        <div className="grid shrink-0 grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
                gradientId="sc-paid"
                title="Paid revenue"
                value={stats?.paid_revenue ?? 0}
                formatValue={money}
                sub={`${stats?.paid_count ?? 0} invoices`}
                series={statSeries?.paid ?? []}
                color={CHART_PAID}
                icon={Wallet}
            />
            <StatCard
                gradientId="sc-pending"
                title="Pending revenue"
                value={stats?.pending_revenue ?? 0}
                formatValue={money}
                sub={`${stats?.pending_count ?? 0} invoices`}
                series={statSeries?.pending ?? []}
                color={STAT_AMBER}
                icon={Clock3}
            />
            <StatCard
                gradientId="sc-overdue"
                title="Overdue"
                value={stats?.overdue_revenue ?? 0}
                formatValue={money}
                sub="Pending past due date"
                series={statSeries?.overdue ?? []}
                color={STAT_ROSE}
                icon={AlertTriangle}
                higherIsBetter={false}
            />
            <StatCard
                gradientId="sc-clients"
                title="Clients"
                value={stats?.client_count ?? 0}
                formatValue={intFmt}
                sub={`${stats?.total_invoices ?? 0} invoices total`}
                series={statSeries?.clients ?? []}
                color={CHART_PAID}
                icon={Users}
            />
        </div>
    );
}

/* -----------------------------------------
   Paid vs Pending Card (fixed height)
------------------------------------------ */
function PaidPendingCard({
    paidPendingPie,
    stats,
    money,
    moneyText,
}: {
    paidPendingPie: Array<{ name: string; value: number; revenue: number }>;
    stats: DashboardStats | null;
    money: (n: number, code?: string) => React.ReactNode;
    moneyText: (n: number, code?: string) => string;
}) {
    const pending = stats?.pending_revenue ?? 0;
    const share = (part: number) => (pending > 0 ? (part / pending) * 100 : 0);

    return (
        <Panel className="flex flex-col lg:min-h-0 lg:flex-1">
            <PanelHeader
                icon={PieChartIcon}
                title="Paid vs Pending"
                subtitle="Counts and revenue split."
                action={
                    <div className="text-xs text-muted-foreground">
                        Paid: {money(stats?.paid_revenue ?? 0)} • Pending:{' '}
                        {money(pending)}
                    </div>
                }
            />

            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:min-h-0 lg:flex-1">
                <div className="h-[200px] lg:h-auto lg:min-h-0">
                    <ResponsiveContainer width="100%" height="100%">
                        <PieChart>
                            <Pie
                                data={paidPendingPie}
                                dataKey="value"
                                nameKey="name"
                                cx="50%"
                                cy="50%"
                                innerRadius="52%"
                                outerRadius="78%"
                                paddingAngle={2}
                                stroke="none"
                            >
                                {paidPendingPie.map((_, idx) => (
                                    <Cell
                                        key={idx}
                                        fill={
                                            PIE_COLORS[idx % PIE_COLORS.length]
                                        }
                                    />
                                ))}
                            </Pie>
                            <Tooltip
                                contentStyle={TOOLTIP_STYLE}
                                formatter={(value, name, props) => {
                                    const rev = props?.payload?.revenue ?? 0;
                                    return [
                                        `${value} • ${moneyText(rev)}`,
                                        name,
                                    ];
                                }}
                            />
                            <Legend
                                iconType="circle"
                                wrapperStyle={LEGEND_STYLE}
                            />
                        </PieChart>
                    </ResponsiveContainer>
                </div>

                <div className="flex flex-col justify-center gap-2 overflow-hidden lg:min-h-0">
                    <InsightRow
                        label="Due in 7 days"
                        value={money(stats?.due_in_7_revenue ?? 0)}
                        percent={share(stats?.due_in_7_revenue ?? 0)}
                        color={STAT_AMBER}
                    />
                    <InsightRow
                        label="Overdue"
                        value={money(stats?.overdue_revenue ?? 0)}
                        percent={share(stats?.overdue_revenue ?? 0)}
                        color={STAT_ROSE}
                        danger
                    />
                </div>
            </div>
        </Panel>
    );
}

/* -----------------------------------------
   Monthly Trend Card (fixed height)
------------------------------------------ */
function MonthlyTrendCard({
    monthlyTrend,
    moneyText,
}: {
    monthlyTrend: Array<{ name: string; paid: number; pending: number }>;
    moneyText: (n: number, code?: string) => string;
}) {
    return (
        <Panel className="flex flex-col lg:min-h-0 lg:flex-1">
            <PanelHeader
                icon={TrendingUp}
                title="Monthly trend"
                subtitle="Last 12 months paid vs pending totals."
            />

            <div className="h-[220px] lg:h-auto lg:min-h-0 lg:flex-1">
                <ResponsiveContainer width="100%" height="100%">
                    <LineChart
                        data={monthlyTrend}
                        margin={{ top: 4, right: 8, bottom: 0, left: -12 }}
                    >
                        <CartesianGrid
                            strokeDasharray="3 3"
                            vertical={false}
                            className="stroke-muted"
                        />
                        <XAxis
                            dataKey="name"
                            tick={{ fontSize: 11 }}
                            tickLine={false}
                            axisLine={false}
                            interval={2}
                        />
                        <YAxis
                            tick={{ fontSize: 11 }}
                            tickLine={false}
                            axisLine={false}
                            width={56}
                        />
                        <Tooltip
                            contentStyle={TOOLTIP_STYLE}
                            formatter={(v, k) => [
                                moneyText(Number(v)),
                                String(k).toUpperCase(),
                            ]}
                        />
                        <Legend iconType="circle" wrapperStyle={LEGEND_STYLE} />
                        <Line
                            type="monotone"
                            dataKey="paid"
                            stroke={CHART_PAID}
                            strokeWidth={2.5}
                            dot={false}
                        />
                        <Line
                            type="monotone"
                            dataKey="pending"
                            stroke={CHART_PENDING}
                            strokeWidth={2.5}
                            dot={false}
                        />
                    </LineChart>
                </ResponsiveContainer>
            </div>
        </Panel>
    );
}

/* -----------------------------------------
   Calendar Card + Drawer (no overflow past card)
------------------------------------------ */
function CalendarCard({
    calMonth,
    goMonth,
    monthDays,
    eventsByDate,
    selectedDay,
    setSelectedDay,
    selectedEvents,
    money,
    todayIso,
}: {
    calMonth: string;
    goMonth: (dir: -1 | 1) => void;
    monthDays: Array<{ date: Date; iso: string }>;
    eventsByDate: Map<string, CalendarEvent[]>;
    selectedDay: string | null;
    setSelectedDay: (d: string | null) => void;
    selectedEvents: CalendarEvent[];
    money: (n: number, code?: string) => React.ReactNode;
    todayIso: string;
}) {
    const [y, m] = calMonth.split('-').map(Number);

    // Rows vary between 5 and 6 depending on where the month starts, so the
    // grid is told how many rows to share the available height between.
    const rowCount = Math.ceil(monthDays.length / 7);

    return (
        <Panel className="flex flex-col lg:min-h-0 lg:flex-1">
            <PanelHeader
                icon={CalendarDays}
                title={monthLabel(y, m)}
                action={
                    <>
                        <IconButton
                            label="Previous month"
                            onClick={() => goMonth(-1)}
                        >
                            <ChevronLeft className="h-4 w-4" />
                        </IconButton>
                        <IconButton
                            label="Next month"
                            onClick={() => goMonth(1)}
                        >
                            <ChevronRight className="h-4 w-4" />
                        </IconButton>
                    </>
                }
            />

            <div className="grid shrink-0 grid-cols-7 gap-1 pb-1 text-center text-[10px] font-medium text-muted-foreground">
                {['M', 'T', 'W', 'T', 'F', 'S', 'S'].map((d, i) => (
                    <div key={`${d}-${i}`}>{d}</div>
                ))}
            </div>

            <div
                className="grid grid-cols-7 gap-1 lg:min-h-0 lg:flex-1"
                style={{
                    gridTemplateRows: `repeat(${rowCount}, minmax(0, 1fr))`,
                }}
            >
                {monthDays.map(({ date, iso }) => {
                    const evs = eventsByDate.get(iso) ?? [];
                    const inMonth =
                        date.getFullYear() === y && date.getMonth() === m - 1;

                    const pendingTotal = evs
                        .filter((e) => e.status === 'pending')
                        .reduce((a, b) => a + (b.amount ?? 0), 0);

                    const isToday = iso === todayIso;
                    const isSelected = selectedDay === iso;

                    return (
                        <button
                            key={iso}
                            type="button"
                            onClick={() => setSelectedDay(iso)}
                            className={cn(
                                'flex min-h-[52px] flex-col overflow-hidden rounded-lg bg-muted/40 px-1 py-0.5 text-left transition lg:min-h-0',
                                !inMonth && 'opacity-45',
                                isToday && 'ring-1 ring-brand-400 ring-inset',
                                isSelected &&
                                    'bg-brand-50 ring-2 ring-brand ring-inset dark:bg-brand-500/20',
                                'hover:bg-brand-50/70 dark:bg-white/5 dark:hover:bg-brand-500/10',
                            )}
                        >
                            <div className="flex shrink-0 items-center justify-between gap-1">
                                <span className="text-[11px] leading-none font-semibold">
                                    {date.getDate()}
                                </span>
                                {evs.length > 0 ? (
                                    <span className="rounded-full bg-background px-1 text-[9px] leading-tight font-semibold text-muted-foreground">
                                        {evs.length}
                                    </span>
                                ) : null}
                            </div>

                            {pendingTotal > 0 ? (
                                <div className="mt-auto truncate rounded bg-amber-500/15 px-1 text-[9px] leading-tight text-amber-700 dark:text-amber-300">
                                    {compactAmount(pendingTotal)}
                                </div>
                            ) : evs.some((e) => e.status === 'paid') ? (
                                <div className="mt-auto h-1 rounded-full bg-emerald-500/50" />
                            ) : null}
                        </button>
                    );
                })}
            </div>

            <DayDetailsDialog
                selectedDay={selectedDay}
                setSelectedDay={setSelectedDay}
                selectedEvents={selectedEvents}
                money={money}
            />
        </Panel>
    );
}

/**
 * Day details moved out of the card body and into a dialog so that opening a
 * day can never change the dashboard's height.
 */
function DayDetailsDialog({
    selectedDay,
    setSelectedDay,
    selectedEvents,
    money,
}: {
    selectedDay: string | null;
    setSelectedDay: (d: string | null) => void;
    selectedEvents: CalendarEvent[];
    money: (n: number, code?: string) => React.ReactNode;
}) {
    return (
        <Dialog
            open={selectedDay !== null}
            onOpenChange={(open) => {
                if (!open) {
                    setSelectedDay(null);
                }
            }}
        >
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Due on {selectedDay}</DialogTitle>
                    <DialogDescription>
                        {selectedEvents.length
                            ? `${selectedEvents.length} invoice(s) due this day.`
                            : 'No invoices due on this day.'}
                    </DialogDescription>
                </DialogHeader>

                <div className="max-h-[50vh] space-y-2 overflow-auto">
                    {selectedEvents.map((ev) => (
                        <Link
                            key={ev.id}
                            href={ev.url}
                            className="flex items-center justify-between gap-3 rounded-2xl bg-muted/40 p-3 transition hover:bg-brand-50/70 dark:bg-white/5 dark:hover:bg-brand-500/10"
                        >
                            <div className="min-w-0">
                                <div className="truncate text-sm font-semibold">
                                    {ev.title}
                                </div>
                                <div className="mt-1">
                                    <StatusPill status={ev.status} />
                                </div>
                            </div>
                            <div className="shrink-0 text-sm tabular-nums">
                                {money(ev.amount, ev.currency_code)}
                            </div>
                        </Link>
                    ))}
                </div>
            </DialogContent>
        </Dialog>
    );
}

/* -----------------------------------------
   Top Clients Card (fills its column)
------------------------------------------ */
function TopClientsCard({
    topClients,
    money,
}: {
    topClients: TopClient[];
    money: (n: number, code?: string) => React.ReactNode;
}) {
    return (
        <Panel className="flex flex-col lg:h-[140px] lg:shrink-0">
            <PanelHeader icon={Users} title="Top clients (pending)" />

            <div className="max-h-[260px] space-y-1.5 overflow-auto pr-1 lg:max-h-none lg:min-h-0 lg:flex-1">
                {topClients.length ? (
                    topClients.map((c) => (
                        <Link
                            key={c.client_id}
                            href={`/invoices?client_id=${c.client_id}&status=pending`}
                            className="flex items-center justify-between gap-2 rounded-xl bg-muted/40 p-1.5 transition hover:bg-brand-50/70 dark:bg-white/5 dark:hover:bg-brand-500/10"
                        >
                            <div className="flex min-w-0 items-center gap-2">
                                <InitialsAvatar
                                    name={c.name}
                                    className="h-6 w-6 text-[10px]"
                                />
                                <span className="truncate text-xs font-semibold">
                                    {c.name}
                                </span>
                            </div>
                            <span className="shrink-0 text-xs tabular-nums">
                                {money(c.pending_total)}
                            </span>
                        </Link>
                    ))
                ) : (
                    <SoftTile className="p-4 text-sm text-muted-foreground">
                        No pending balances yet.
                    </SoftTile>
                )}
            </div>
        </Panel>
    );
}

/* -----------------------------------------
   Shared UI pieces
------------------------------------------ */
/**
 * Animates a number from 0 up to `target` on mount / when target changes.
 */
function useCountUp(target: number, durationMs = 750): number {
    const [display, setDisplay] = React.useState(0);

    React.useEffect(() => {
        let raf = 0;
        const start = performance.now();
        const from = 0;

        const tick = (now: number) => {
            const t = Math.min(1, (now - start) / durationMs);
            // easeOutCubic
            const eased = 1 - Math.pow(1 - t, 3);
            setDisplay(from + (target - from) * eased);
            if (t < 1) {
                raf = requestAnimationFrame(tick);
            }
        };

        raf = requestAnimationFrame(tick);
        return () => cancelAnimationFrame(raf);
    }, [target, durationMs]);

    return display;
}

/** Month-over-month % change from the last two non-empty points. */
function computeTrend(series: number[]): number | null {
    if (!series || series.length < 2) {
        return null;
    }
    const last = series[series.length - 1] ?? 0;
    const prev = series[series.length - 2] ?? 0;
    if (prev === 0) {
        return null;
    }
    return ((last - prev) / prev) * 100;
}

function StatCard({
    gradientId,
    title,
    value,
    formatValue,
    sub,
    series,
    color,
    icon,
    higherIsBetter = true,
}: {
    gradientId: string;
    title: string;
    value: number;
    formatValue: (n: number) => React.ReactNode;
    sub: string;
    series: number[];
    color: string;
    icon: React.ComponentType<{ className?: string }>;
    higherIsBetter?: boolean;
}) {
    const animated = useCountUp(value);
    const trend = computeTrend(series);
    const data = React.useMemo(
        () => series.map((v, i) => ({ i, v })),
        [series],
    );

    return (
        <StatTile
            title={title}
            value={formatValue(animated)}
            sub={sub}
            icon={icon}
            trend={trend}
            higherIsBetter={higherIsBetter}
            sparkline={
                data.length > 1 ? (
                    <ResponsiveContainer width="100%" height="100%">
                        <AreaChart
                            data={data}
                            margin={{ top: 0, right: 0, bottom: 0, left: 0 }}
                        >
                            <defs>
                                <linearGradient
                                    id={gradientId}
                                    x1="0"
                                    y1="0"
                                    x2="0"
                                    y2="1"
                                >
                                    <stop
                                        offset="0%"
                                        stopColor={color}
                                        stopOpacity={0.4}
                                    />
                                    <stop
                                        offset="100%"
                                        stopColor={color}
                                        stopOpacity={0}
                                    />
                                </linearGradient>
                            </defs>
                            <Area
                                type="monotone"
                                dataKey="v"
                                stroke={color}
                                strokeWidth={2}
                                fill={`url(#${gradientId})`}
                                fillOpacity={1}
                                isAnimationActive={false}
                                dot={false}
                            />
                        </AreaChart>
                    </ResponsiveContainer>
                ) : null
            }
        />
    );
}

function InsightRow({
    label,
    value,
    percent,
    color,
    danger,
}: {
    label: string;
    value: React.ReactNode;
    percent: number;
    color: string;
    danger?: boolean;
}) {
    return (
        <SoftTile className="flex shrink-0 items-center gap-3 p-2.5">
            <Ring percent={percent} color={color} label={label} size={42} />
            <div className="min-w-0">
                <div className="truncate text-xs font-semibold">{label}</div>
                <div
                    className={cn(
                        'mt-0.5 truncate text-sm tabular-nums',
                        danger && 'text-rose-600 dark:text-rose-400',
                    )}
                >
                    {value}
                </div>
            </div>
        </SoftTile>
    );
}
