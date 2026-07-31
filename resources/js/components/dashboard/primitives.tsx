import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowDown,
    ArrowUp,
    ArrowUpRight,
    CheckCircle2,
    Clock3,
    FileText,
    PackageCheck,
    Search,
    Truck,
    XCircle,
} from 'lucide-react';
import * as React from 'react';

/* -----------------------------------------
   Surfaces
------------------------------------------ */

/**
 * Borderless white surface with a soft layered shadow. In dark mode the
 * shadow reads as nothing, so a hairline ring carries the edge instead.
 */
export function Panel({
    className,
    children,
}: {
    className?: string;
    children: React.ReactNode;
}) {
    return (
        <div
            className={cn(
                'rounded-3xl bg-card p-4 sm:p-5',
                'shadow-[0_1px_2px_0_rgb(16_24_40/0.04),0_12px_32px_-14px_rgb(16_24_40/0.16)]',
                'dark:shadow-none dark:ring-1 dark:ring-white/10',
                className,
            )}
        >
            {children}
        </div>
    );
}

export function PanelHeader({
    title,
    subtitle,
    action,
    icon: Icon,
    className,
}: {
    title: string;
    subtitle?: string;
    action?: React.ReactNode;
    icon?: React.ComponentType<{ className?: string }>;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'mb-3 flex shrink-0 flex-col gap-2 sm:flex-row sm:items-start sm:justify-between',
                className,
            )}
        >
            <div className="flex items-start gap-2.5">
                {Icon ? (
                    <span className="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">
                        <Icon className="h-4 w-4" />
                    </span>
                ) : null}
                <div>
                    <div className="text-sm font-semibold">{title}</div>
                    {subtitle ? (
                        <div className="mt-0.5 text-xs text-muted-foreground">
                            {subtitle}
                        </div>
                    ) : null}
                </div>
            </div>

            {action ? (
                <div className="flex shrink-0 items-center gap-2">{action}</div>
            ) : null}
        </div>
    );
}

/** Inner block inside a Panel — tinted instead of bordered. */
export function SoftTile({
    className,
    children,
    ...props
}: React.ComponentProps<'div'>) {
    return (
        <div
            className={cn(
                'rounded-2xl bg-muted/50 p-3 dark:bg-white/5',
                className,
            )}
            {...props}
        >
            {children}
        </div>
    );
}

/* -----------------------------------------
   Controls
------------------------------------------ */

type ButtonVariant = 'solid' | 'soft' | 'ghost';
type ButtonSize = 'sm' | 'md';

/**
 * Class string for pill-shaped actions. Exported as a function so `<Link>`
 * can wear the same styling without a Slot wrapper.
 */
export function pillButtonClass(
    variant: ButtonVariant = 'solid',
    size: ButtonSize = 'md',
    className?: string,
): string {
    return cn(
        'inline-flex shrink-0 items-center justify-center gap-2 rounded-lg font-semibold transition',
        'focus-visible:ring-2 focus-visible:ring-brand-400 focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none',
        'disabled:pointer-events-none disabled:opacity-50',
        size === 'sm' ? 'h-8 px-3 text-xs' : 'h-10 px-4 text-sm',
        variant === 'solid' &&
            'bg-brand text-brand-foreground shadow-[0_6px_16px_-6px_rgb(0_65_125/0.6)] hover:bg-brand-700',
        variant === 'soft' &&
            'bg-brand-50 text-brand-700 hover:bg-brand-100 dark:bg-brand-500/15 dark:text-brand-200 dark:hover:bg-brand-500/25',
        variant === 'ghost' &&
            'bg-muted/60 text-foreground hover:bg-muted dark:bg-white/5 dark:hover:bg-white/10',
        className,
    );
}

export function PillButton({
    variant = 'solid',
    size = 'md',
    className,
    children,
    ...props
}: React.ComponentProps<'button'> & {
    variant?: ButtonVariant;
    size?: ButtonSize;
}) {
    return (
        <button
            type="button"
            className={pillButtonClass(variant, size, className)}
            {...props}
        >
            {children}
        </button>
    );
}

export function IconButton({
    label,
    className,
    children,
    ...props
}: React.ComponentProps<'button'> & { label: string }) {
    return (
        <button
            type="button"
            aria-label={label}
            className={cn(
                'grid h-9 w-9 place-items-center rounded-lg bg-muted/60 text-muted-foreground transition',
                'hover:bg-muted hover:text-foreground dark:bg-white/5 dark:hover:bg-white/10',
                'focus-visible:ring-2 focus-visible:ring-brand-400 focus-visible:outline-none',
                className,
            )}
            {...props}
        >
            {children}
        </button>
    );
}

/** Rounded segment used for filters and read-only meta chips. */
export function Chip({
    active,
    interactive = true,
    className,
    children,
    ...props
}: React.ComponentProps<'button'> & {
    active?: boolean;
    interactive?: boolean;
}) {
    const classes = cn(
        'inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold capitalize transition',
        active
            ? 'bg-brand text-brand-foreground'
            : 'bg-muted/60 text-muted-foreground dark:bg-white/5',
        interactive &&
            !active &&
            'hover:bg-brand-50 hover:text-brand-700 dark:hover:bg-brand-500/15 dark:hover:text-brand-200',
        className,
    );

    if (!interactive) {
        return <span className={classes}>{children}</span>;
    }

    return (
        <button type="button" className={classes} {...props}>
            {children}
        </button>
    );
}

export function SearchField({
    value,
    onChange,
    placeholder,
    className,
}: {
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    className?: string;
}) {
    return (
        <div className={cn('relative', className)}>
            <Search className="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <input
                type="search"
                value={value}
                onChange={(e) => onChange(e.target.value)}
                placeholder={placeholder}
                className={cn(
                    'h-10 w-full rounded-md border border-border bg-muted pr-4 pl-10 text-sm shadow-xs transition placeholder:text-muted-foreground',
                    'focus:border-brand-400 focus:ring-2 focus:ring-brand-400/40 focus:outline-none',
                    'dark:border-white/10 dark:bg-white/5',
                )}
            />
        </div>
    );
}

/* -----------------------------------------
   Form fields
------------------------------------------ */

/** Borderless-adjacent input used by the create/edit dialogs. */
export const fieldInputClass = cn(
    'h-10 w-full min-w-0 rounded-xl border border-border bg-transparent px-3 text-sm transition outline-none',
    'placeholder:text-muted-foreground/70 focus:border-foreground/30 focus:ring-2 focus:ring-foreground/10',
);

/** Labelled wrapper that pairs with `fieldInputClass`. */
export function FormField({
    label,
    htmlFor,
    required = false,
    className,
    children,
}: {
    label: string;
    htmlFor?: string;
    required?: boolean;
    className?: string;
    children: React.ReactNode;
}) {
    return (
        <div className={cn('space-y-1.5', className)}>
            <label
                htmlFor={htmlFor}
                className="block text-sm leading-none font-medium select-none"
            >
                {label}
                {required ? (
                    <span className="ml-0.5 text-destructive" aria-hidden>
                        *
                    </span>
                ) : null}
            </label>

            {children}
        </div>
    );
}

/* -----------------------------------------
   Indicators
------------------------------------------ */

export function DeltaBadge({
    trend,
    higherIsBetter = true,
    note,
}: {
    trend: number;
    higherIsBetter?: boolean;
    note?: string;
}) {
    const rising = trend >= 0;
    const good = rising === higherIsBetter;
    const Arrow = rising ? ArrowUp : ArrowDown;

    return (
        <span className="inline-flex items-center gap-1 text-xs">
            <span
                className={cn(
                    'inline-flex items-center gap-0.5 font-semibold',
                    good
                        ? 'text-emerald-600 dark:text-emerald-400'
                        : 'text-rose-600 dark:text-rose-400',
                )}
            >
                <Arrow className="h-3 w-3" />
                {rising ? '+' : ''}
                {trend.toFixed(1)}%
            </span>
            {note ? (
                <span className="text-muted-foreground">{note}</span>
            ) : null}
        </span>
    );
}

/** SVG progress ring — the small circular meters in the reference layout. */
export function Ring({
    percent,
    color,
    label,
    size = 56,
}: {
    percent: number;
    color: string;
    label: string;
    size?: number;
}) {
    const clamped = Math.max(0, Math.min(100, percent));
    const stroke = 5;
    const radius = (size - stroke) / 2;
    const circumference = 2 * Math.PI * radius;

    return (
        <div
            className="relative shrink-0"
            style={{ width: size, height: size }}
            role="img"
            aria-label={`${label}: ${Math.round(clamped)}%`}
        >
            <svg width={size} height={size} className="-rotate-90">
                <circle
                    cx={size / 2}
                    cy={size / 2}
                    r={radius}
                    fill="none"
                    strokeWidth={stroke}
                    className="stroke-muted dark:stroke-white/10"
                />
                <circle
                    cx={size / 2}
                    cy={size / 2}
                    r={radius}
                    fill="none"
                    stroke={color}
                    strokeWidth={stroke}
                    strokeLinecap="round"
                    strokeDasharray={circumference}
                    strokeDashoffset={
                        circumference - (clamped / 100) * circumference
                    }
                />
            </svg>
            <span className="absolute inset-0 grid place-items-center text-[11px] font-semibold tabular-nums">
                {Math.round(clamped)}%
            </span>
        </div>
    );
}

/**
 * Tone per document status. Anything unrecognised falls through to the amber
 * "waiting on someone" treatment, which is what `pending` has always used.
 */
const STATUS_TONES: Record<
    string,
    { className: string; icon: React.ComponentType<{ className?: string }> }
> = {
    paid: {
        className: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
        icon: CheckCircle2,
    },
    /**
     * An issued credit note has moved money; a draft has not. Without its own
     * tone `issued` falls back to amber and reads as a draft, which is the one
     * pair on this document that must never look alike.
     */
    issued: {
        className: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
        icon: CheckCircle2,
    },
    accepted: {
        className: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
        icon: CheckCircle2,
    },
    /**
     * Same trap as `issued` below: without its own tone a signed-for delivery
     * note falls back to amber and reads exactly like a draft one, which on a
     * document whose only job is proving receipt is the worst possible pair to
     * confuse. `dispatched` sits between them — gone, but not yet signed.
     */
    delivered: {
        className: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
        icon: PackageCheck,
    },
    dispatched: {
        className:
            'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-200',
        icon: Truck,
    },
    /**
     * The purchase order pair, and the same trap again: neither had a tone, so
     * an order that had been approved — or whose goods were already on the
     * shelf — rendered in amber, indistinguishable from one still being typed.
     *
     * They take the `dispatched`/`delivered` split rather than both going
     * emerald: `approved` is brand because the order is live but nothing has
     * arrived yet, and only `received` is finished, so only it earns emerald.
     * `sent` is brand too, but carries `ArrowUpRight` against this `CheckCircle2`.
     */
    approved: {
        className:
            'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-200',
        icon: CheckCircle2,
    },
    received: {
        className: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
        icon: PackageCheck,
    },
    confirmed: {
        className: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
        icon: CheckCircle2,
    },
    active: {
        className: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
        icon: CheckCircle2,
    },
    handled: {
        className: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
        icon: CheckCircle2,
    },
    rejected: {
        className: 'bg-rose-500/10 text-rose-700 dark:text-rose-400',
        icon: XCircle,
    },
    cancelled: {
        className: 'bg-rose-500/10 text-rose-700 dark:text-rose-400',
        icon: XCircle,
    },
    expired: {
        className: 'bg-rose-500/10 text-rose-700 dark:text-rose-400',
        icon: AlertTriangle,
    },
    sent: {
        className:
            'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-200',
        icon: ArrowUpRight,
    },
    overdue: {
        className: 'bg-rose-500/10 text-rose-700 dark:text-rose-400',
        icon: AlertTriangle,
    },
    void: {
        className: 'bg-muted/60 text-muted-foreground dark:bg-white/5',
        icon: XCircle,
    },
    draft: {
        className: 'bg-amber-500/10 text-amber-700 dark:text-amber-400',
        icon: FileText,
    },
};

const FALLBACK_STATUS_TONE = {
    className: 'bg-amber-500/10 text-amber-700 dark:text-amber-400',
    icon: Clock3,
};

export function StatusPill({ status }: { status: string }) {
    const tone =
        STATUS_TONES[(status || '').toLowerCase()] ?? FALLBACK_STATUS_TONE;
    const Icon = tone.icon;

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold capitalize',
                tone.className,
            )}
        >
            <Icon className="h-3.5 w-3.5" />
            {status.replace(/_/g, ' ')}
        </span>
    );
}

/** Label/amount line used by the invoice and quotation totals panels. */
export function TotalRow({
    label,
    value,
    strong,
}: {
    label: string;
    value: React.ReactNode;
    strong?: boolean;
}) {
    return (
        <div className="flex items-center justify-between gap-3">
            <span className="text-xs text-muted-foreground">{label}</span>
            <span
                className={cn(
                    'text-sm tabular-nums',
                    strong && 'text-base font-semibold',
                )}
            >
                {value}
            </span>
        </div>
    );
}

const AVATAR_TINTS = [
    'bg-brand-100 text-brand-700 dark:bg-brand-500/20 dark:text-brand-200',
    'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300',
    'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300',
    'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300',
    'bg-violet-100 text-violet-700 dark:bg-violet-500/20 dark:text-violet-300',
];

export function InitialsAvatar({
    name,
    className,
}: {
    name: string;
    className?: string;
}) {
    const initials = React.useMemo(() => {
        const parts = name.trim().split(/\s+/).filter(Boolean);
        if (!parts.length) {
            return '—';
        }
        return (parts[0][0] + (parts[1]?.[0] ?? '')).toUpperCase();
    }, [name]);

    const tint = React.useMemo(() => {
        let sum = 0;
        for (let i = 0; i < name.length; i++) {
            sum += name.charCodeAt(i);
        }
        return AVATAR_TINTS[sum % AVATAR_TINTS.length];
    }, [name]);

    return (
        <span
            className={cn(
                'grid h-8 w-8 shrink-0 place-items-center rounded-full text-[11px] font-semibold',
                tint,
                className,
            )}
        >
            {initials}
        </span>
    );
}

/* -----------------------------------------
   Stat tile
------------------------------------------ */

/**
 * The canonical stat tile. `sub`, `trend` and `sparkline` are all optional so
 * plain count tiles on list pages wear the same shell as the dashboard's
 * trended ones — the meta row and sparkline strip simply collapse.
 */
export function StatTile({
    title,
    value,
    sub,
    icon: Icon,
    trend = null,
    higherIsBetter = true,
    sparkline,
    href,
}: {
    title: string;
    value: React.ReactNode;
    sub?: string;
    icon: React.ComponentType<{ className?: string }>;
    trend?: number | null;
    higherIsBetter?: boolean;
    sparkline?: React.ReactNode;
    href?: string;
}) {
    const hasMeta = trend !== null || Boolean(sub);

    const tile = (
        <Panel
            className={cn(
                'flex flex-col overflow-hidden p-0',
                href && 'transition hover:-translate-y-0.5',
            )}
        >
            <div className="flex items-start justify-between gap-3 px-4 pt-4">
                <div className="min-w-0">
                    <div className="text-xs font-semibold text-muted-foreground">
                        {title}
                    </div>
                    <div className="mt-1 truncate text-xl tracking-tight tabular-nums sm:text-2xl">
                        {value}
                    </div>
                </div>

                <span className="grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-muted/60 text-muted-foreground dark:bg-white/5">
                    <Icon className="h-4 w-4" />
                </span>
            </div>

            {hasMeta ? (
                <div className="mt-1 flex flex-wrap items-center gap-x-2 px-4">
                    {trend !== null ? (
                        <DeltaBadge
                            trend={trend}
                            higherIsBetter={higherIsBetter}
                            note="since last month"
                        />
                    ) : null}
                    {sub ? (
                        <span className="truncate text-xs text-muted-foreground">
                            {sub}
                        </span>
                    ) : null}
                </div>
            ) : null}

            <div className={cn('w-full', sparkline ? 'mt-2 h-8' : 'h-4')}>
                {sparkline}
            </div>
        </Panel>
    );

    if (href) {
        return (
            <Link href={href} className="block">
                {tile}
            </Link>
        );
    }

    return tile;
}

/* -----------------------------------------
   Table pieces
------------------------------------------ */

/**
 * Row cell recipe for the list tables. Rows read as tinted tiles rather than
 * ruled lines, so the first and last cell in a row take `rounded-l-2xl` /
 * `rounded-r-2xl` and the table itself uses `border-separate`.
 */
export const tableCellClass = cn(
    'bg-muted/40 px-3 py-3 align-middle transition dark:bg-white/5',
    'group-hover:bg-brand-50/70 dark:group-hover:bg-brand-500/10',
);

export function SortableTh({
    label,
    active,
    direction,
    onClick,
    align = 'left',
}: {
    label: string;
    active: boolean;
    direction: 'asc' | 'desc';
    onClick: () => void;
    align?: 'left' | 'right';
}) {
    return (
        <th
            className={cn(
                'px-3 pb-1 font-medium',
                align === 'right' ? 'text-right' : 'text-left',
            )}
        >
            <button
                type="button"
                onClick={onClick}
                className={cn(
                    'inline-flex items-center gap-1 transition hover:text-foreground',
                    active && 'text-foreground',
                )}
            >
                {label}
                {active ? (
                    direction === 'asc' ? (
                        <ArrowUp className="h-3 w-3" />
                    ) : (
                        <ArrowDown className="h-3 w-3" />
                    )
                ) : null}
            </button>
        </th>
    );
}

/* -----------------------------------------
   Empty states & pagination
------------------------------------------ */

/**
 * The centred "nothing here" block used inside a Panel. Tinting the icon well
 * with brand keeps it reading as an invitation; `muted` reads as a dead end,
 * which is what filtered-to-nothing states want.
 */
export function EmptyState({
    icon: Icon,
    title,
    description,
    action,
    tone = 'brand',
}: {
    icon: React.ComponentType<{ className?: string }>;
    title: string;
    description?: string;
    action?: React.ReactNode;
    tone?: 'brand' | 'muted';
}) {
    return (
        <div className="flex flex-col items-center px-4 py-10 text-center">
            <span
                className={cn(
                    'grid h-14 w-14 place-items-center rounded-2xl',
                    tone === 'brand'
                        ? 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300'
                        : 'bg-muted/60 text-muted-foreground dark:bg-white/5',
                )}
            >
                <Icon className="h-6 w-6" />
            </span>

            <div className="mt-4 text-sm font-semibold">{title}</div>
            {description ? (
                <p className="mt-1 max-w-sm text-sm text-muted-foreground">
                    {description}
                </p>
            ) : null}

            {action ? <div className="mt-5">{action}</div> : null}
        </div>
    );
}

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

/**
 * Renders a Laravel paginator's `links` array as pill segments. Labels arrive
 * as HTML entities (`&laquo; Previous`), which is why they are set as markup
 * rather than text.
 */
export function Pagination({
    links,
    className,
}: {
    links: PaginationLink[];
    className?: string;
}) {
    if (links.length <= 3) {
        return null;
    }

    return (
        <nav
            aria-label="Pagination"
            className={cn('flex flex-wrap justify-center gap-1.5', className)}
        >
            {links.map((link, index) => {
                const label = (
                    <span dangerouslySetInnerHTML={{ __html: link.label }} />
                );

                if (!link.url) {
                    return (
                        <span
                            key={index}
                            aria-disabled
                            className="inline-flex h-8 items-center rounded-full px-3 text-xs font-semibold text-muted-foreground/50"
                        >
                            {label}
                        </span>
                    );
                }

                return (
                    <Link
                        key={index}
                        href={link.url}
                        aria-current={link.active ? 'page' : undefined}
                        className={cn(
                            'inline-flex h-8 items-center rounded-full px-3 text-xs font-semibold transition',
                            link.active
                                ? 'bg-brand text-brand-foreground'
                                : 'bg-muted/60 text-muted-foreground hover:bg-brand-50 hover:text-brand-700 dark:bg-white/5 dark:hover:bg-brand-500/15 dark:hover:text-brand-200',
                        )}
                    >
                        {label}
                    </Link>
                );
            })}
        </nav>
    );
}

/** Page numbers around `page`, with `null` marking a gap: 1 … 4 5 6 … 20. */
function paginationWindow(page: number, pageCount: number): (number | null)[] {
    if (pageCount <= 7) {
        return Array.from({ length: pageCount }, (_, i) => i + 1);
    }

    const around = [page - 1, page, page + 1].filter(
        (p) => p > 1 && p < pageCount,
    );
    const pages = [1, ...around, pageCount];

    return pages.flatMap((p, i) =>
        i > 0 && p - pages[i - 1] > 1 ? [null, p] : [p],
    );
}

const paginationPillClass =
    'inline-flex h-8 min-w-8 items-center justify-center rounded-full px-3 text-xs font-semibold transition disabled:cursor-not-allowed disabled:opacity-40';

/**
 * Pagination for lists already held in memory, where paging must not refetch —
 * the rows are searched, filtered and sorted client-side before slicing.
 * Use {@link Pagination} instead when the server returns a Laravel paginator.
 */
export function ClientPagination({
    page,
    pageCount,
    onPageChange,
    className,
}: {
    page: number;
    pageCount: number;
    onPageChange: (page: number) => void;
    className?: string;
}) {
    if (pageCount <= 1) {
        return null;
    }

    return (
        <nav
            aria-label="Pagination"
            className={cn('flex flex-wrap justify-center gap-1.5', className)}
        >
            <button
                type="button"
                onClick={() => onPageChange(page - 1)}
                disabled={page <= 1}
                className={cn(
                    paginationPillClass,
                    'bg-muted/60 text-muted-foreground hover:bg-brand-50 hover:text-brand-700 dark:bg-white/5 dark:hover:bg-brand-500/15 dark:hover:text-brand-200',
                )}
            >
                Previous
            </button>

            {paginationWindow(page, pageCount).map((entry, index) =>
                entry === null ? (
                    <span
                        key={`gap-${index}`}
                        aria-hidden
                        className="inline-flex h-8 items-center px-1 text-xs font-semibold text-muted-foreground/50"
                    >
                        …
                    </span>
                ) : (
                    <button
                        key={entry}
                        type="button"
                        onClick={() => onPageChange(entry)}
                        aria-current={entry === page ? 'page' : undefined}
                        className={cn(
                            paginationPillClass,
                            entry === page
                                ? 'bg-brand text-brand-foreground'
                                : 'bg-muted/60 text-muted-foreground hover:bg-brand-50 hover:text-brand-700 dark:bg-white/5 dark:hover:bg-brand-500/15 dark:hover:text-brand-200',
                        )}
                    >
                        {entry}
                    </button>
                ),
            )}

            <button
                type="button"
                onClick={() => onPageChange(page + 1)}
                disabled={page >= pageCount}
                className={cn(
                    paginationPillClass,
                    'bg-muted/60 text-muted-foreground hover:bg-brand-50 hover:text-brand-700 dark:bg-white/5 dark:hover:bg-brand-500/15 dark:hover:text-brand-200',
                )}
            >
                Next
            </button>
        </nav>
    );
}
