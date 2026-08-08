import { Head, Link, router, useForm } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { Check, Coins, Pencil, Plus, SearchX, Trash2, X } from 'lucide-react';
import React, { useMemo, useState } from 'react';
import { toast } from 'sonner';

import {
    Chip,
    Panel,
    PanelHeader,
    PillButton,
    SearchField,
    pillButtonClass,
} from '@/components/dashboard/primitives';
import NiloSpinner from '@/components/nilo-spinner';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types/index.d';

// shadcn/ui
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';
import { cn } from '@/lib/utils';

type Currency = {
    id: number;
    code: string;
    name: string;
    symbol?: string | null;
    precision: number;
    is_active: boolean;
    created_at: string;
};

type RateRow = {
    code: string;
    synced_rate: number | null;
    override_rate: number | null;
    override_set_at: string | null;
    /** Whether the override is the rate actually being used right now. */
    in_force: boolean;
};

type RateBoard = {
    base: string;
    rates_as_of: string | null;
    last_synced_at: string | null;
    rows: RateRow[];
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Currencies',
        href: '/settings/currencies',
    },
];

/** Danger action styling for the borderless pill buttons. */
const DANGER_PILL =
    'bg-rose-500/10 text-rose-600 hover:bg-rose-500/20 dark:bg-rose-500/15 dark:text-rose-400 dark:hover:bg-rose-500/25';

export default function Currencies({
    catalog: currencies,
    fx,
}: {
    catalog: Currency[];
    fx: RateBoard;
}) {
    const [q, setQ] = useState('');

    /**
     * The table below manages the currencies actually in use. The full ISO 4217
     * catalog lives in the panel above — listing all 150-odd here would bury the
     * handful that matter.
     */
    const inUse = useMemo(
        () => currencies.filter((c) => c.is_active),
        [currencies],
    );

    const filtered = useMemo(() => {
        const needle = q.trim().toLowerCase();
        if (!needle) return inUse;

        return inUse.filter((c) =>
            `${c.code} ${c.name} ${c.symbol ?? ''}`
                .toLowerCase()
                .includes(needle),
        );
    }, [inUse, q]);

    const hasResults = filtered.length > 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Currencies" />

            <div className="mx-auto w-full py-3">
                {/* Header */}
                <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="min-w-0">
                        <h1 className="text-base font-semibold">Currencies</h1>
                        <p className="mt-0.5 text-sm text-muted-foreground">
                            Add, edit, activate and remove the currencies your
                            business bills in.
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Link
                            href="/dashboard"
                            className={pillButtonClass('ghost', 'sm')}
                        >
                            Back to dashboard
                        </Link>
                        <CurrencyModal mode="create" />
                    </div>
                </div>

                <motion.div
                    initial={{ opacity: 0, y: 10 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ duration: 0.25, ease: 'easeOut' }}
                    className="flex flex-col gap-4"
                >
                    <ActivateCurrenciesPanel currencies={currencies} />

                    <ExchangeRatePanel fx={fx} />

                    <Panel>
                        <PanelHeader
                            icon={Coins}
                            title="Currencies in use"
                            subtitle={
                                q.trim()
                                    ? `${filtered.length} of ${inUse.length} shown`
                                    : `${inUse.length} in use • ${currencies.length} in catalog`
                            }
                            action={
                                <SearchField
                                    value={q}
                                    onChange={setQ}
                                    placeholder="Search code, name, symbol…"
                                    className="w-full sm:w-72"
                                />
                            }
                        />

                        {!hasResults ? (
                            <NoCurrencyResults
                                query={q}
                                onClear={() => setQ('')}
                            />
                        ) : (
                            <div className="w-full overflow-x-auto">
                                <table className="w-full min-w-[760px] border-collapse text-sm [&_td]:px-3 [&_th]:px-3">
                                    <thead className="text-xs text-muted-foreground">
                                        <tr className="border-b border-black/[0.06] dark:border-white/10">
                                            <th className="w-[18%] py-2 text-left font-medium">
                                                Code
                                            </th>
                                            <th className="py-2 text-left font-medium">
                                                Name
                                            </th>
                                            <th className="w-[12%] py-2 text-left font-medium">
                                                Symbol
                                            </th>
                                            <th className="w-[14%] py-2 text-left font-medium">
                                                Precision
                                            </th>
                                            <th className="w-[14%] py-2 text-left font-medium">
                                                Status
                                            </th>
                                            <th className="w-[22%] py-2 text-right font-medium">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>

                                    <tbody className="divide-y divide-black/[0.04] dark:divide-white/5">
                                        {filtered.map((c, i) => (
                                            <motion.tr
                                                key={c.id}
                                                initial={{ opacity: 0, y: 10 }}
                                                animate={{ opacity: 1, y: 0 }}
                                                transition={{
                                                    duration: 0.22,
                                                    delay: Math.min(
                                                        i * 0.02,
                                                        0.15,
                                                    ),
                                                }}
                                                className="group transition hover:bg-muted/40 dark:hover:bg-white/5"
                                            >
                                                <td className="py-3">
                                                    <Chip
                                                        interactive={false}
                                                        className="uppercase"
                                                    >
                                                        {c.code}
                                                    </Chip>
                                                </td>

                                                <td className="py-3">
                                                    <div className="text-sm font-semibold">
                                                        {c.name}
                                                    </div>
                                                </td>

                                                <td className="py-3">
                                                    <span className="text-sm text-muted-foreground">
                                                        {c.symbol || '—'}
                                                    </span>
                                                </td>

                                                <td className="py-3">
                                                    <span className="text-sm tabular-nums">
                                                        {c.precision}
                                                    </span>
                                                </td>

                                                <td className="py-3">
                                                    {c.is_active ? (
                                                        <Chip
                                                            active
                                                            interactive={false}
                                                        >
                                                            <Check className="h-3.5 w-3.5" />
                                                            Active
                                                        </Chip>
                                                    ) : (
                                                        <Chip
                                                            interactive={false}
                                                        >
                                                            Inactive
                                                        </Chip>
                                                    )}
                                                </td>

                                                <td className="py-3 text-right">
                                                    <div className="flex justify-end gap-2">
                                                        <CurrencyModal
                                                            mode="edit"
                                                            currency={c}
                                                            trigger={
                                                                <PillButton
                                                                    variant="ghost"
                                                                    size="sm"
                                                                >
                                                                    <Pencil className="h-3.5 w-3.5" />
                                                                    Edit
                                                                </PillButton>
                                                            }
                                                        />

                                                        <DeleteCurrencyButton
                                                            currency={c}
                                                        />
                                                    </div>
                                                </td>
                                            </motion.tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </Panel>
                </motion.div>
            </div>
        </AppLayout>
    );
}

/* --------------------------- Empty states --------------------------- */

function NoCurrencyResults({
    query,
    onClear,
}: {
    query: string;
    onClear: () => void;
}) {
    const searching = Boolean(query.trim());

    return (
        <div className="flex flex-col items-center px-4 py-10 text-center">
            <span className="grid h-14 w-14 place-items-center rounded-2xl bg-muted/60 text-muted-foreground dark:bg-white/5">
                {searching ? (
                    <SearchX className="h-6 w-6" />
                ) : (
                    <Coins className="h-6 w-6" />
                )}
            </span>

            <div className="mt-4 text-sm font-semibold">
                {searching ? 'No matches' : 'No currencies in use'}
            </div>
            <p className="mt-1 max-w-sm text-sm text-muted-foreground">
                {searching
                    ? `Nothing matched “${query.trim()}”. Try a different code, name or symbol.`
                    : 'Tick the currencies you bill in from the catalog above, then save.'}
            </p>

            <div className="mt-5">
                {searching ? (
                    <PillButton variant="ghost" size="sm" onClick={onClear}>
                        Clear search
                    </PillButton>
                ) : (
                    <CurrencyModal mode="create" />
                )}
            </div>
        </div>
    );
}

/* --------------------------- Activation panel --------------------------- */

/* -----------------------------------------
   Exchange rates
------------------------------------------ */

const relativeTime = (iso: string | null): string => {
    if (!iso) return 'never';

    const then = new Date(iso).getTime();
    const minutes = Math.round((Date.now() - then) / 60000);

    if (minutes < 1) return 'just now';
    if (minutes < 60) return `${minutes} min ago`;

    const hours = Math.round(minutes / 60);
    if (hours < 24) return `${hours}h ago`;

    return `${Math.round(hours / 24)}d ago`;
};

/**
 * Rates synced from the provider, and the manual rates a company can put in
 * front of them.
 *
 * A manual rate is deliberately short-lived: the next successful sync takes
 * back over, so the "In force" / "Superseded" status is the important column
 * rather than the number itself.
 */
function ExchangeRatePanel({ fx }: { fx: RateBoard }) {
    const [syncing, setSyncing] = useState(false);

    const syncNow = () => {
        setSyncing(true);

        router.post(
            '/currencies/rates/sync',
            {},
            {
                preserveScroll: true,
                onFinish: () => setSyncing(false),
            },
        );
    };

    return (
        <Panel>
            <PanelHeader
                icon={Coins}
                title="Exchange rates"
                subtitle={`Quoted against ${fx.base} · rates as of ${relativeTime(fx.rates_as_of)} · last checked ${relativeTime(fx.last_synced_at)}`}
                action={<SyncNowButton onConfirm={syncNow} busy={syncing} />}
            />

            {fx.rows.length === 0 ? (
                <div className="rounded-2xl bg-muted/50 p-4 text-sm text-muted-foreground dark:bg-white/5">
                    Activate a currency other than {fx.base} to set a rate for
                    it.
                </div>
            ) : (
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[34rem] border-separate border-spacing-y-1.5 text-sm">
                        <thead>
                            <tr className="text-left text-xs text-muted-foreground">
                                <th className="px-3 pb-1 font-medium">
                                    Currency
                                </th>
                                <th className="px-3 pb-1 font-medium">
                                    Synced
                                </th>
                                <th className="px-3 pb-1 font-medium">
                                    Your rate
                                </th>
                                <th className="px-3 pb-1 font-medium">
                                    Status
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {fx.rows.map((row) => (
                                <RateRowItem key={row.code} row={row} />
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </Panel>
    );
}

/**
 * The sync writes the shared rate table, so the confirm spells out both
 * consequences: everyone's rates move, and your own manual rates retire.
 */
function SyncNowButton({
    onConfirm,
    busy,
}: {
    onConfirm: () => void;
    busy: boolean;
}) {
    return (
        <AlertDialog>
            <AlertDialogTrigger asChild>
                <PillButton variant="ghost" size="sm" disabled={busy}>
                    {busy ? (
                        <>
                            <NiloSpinner className="h-3.5 w-3.5" />
                            Syncing…
                        </>
                    ) : (
                        'Sync now'
                    )}
                </PillButton>
            </AlertDialogTrigger>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Refresh exchange rates?</AlertDialogTitle>
                    <AlertDialogDescription>
                        This refreshes rates for every company on the platform,
                        and retires any manual rates you currently have in
                        force. Rates are published once a day, so this is mainly
                        useful if the overnight refresh did not run.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Cancel</AlertDialogCancel>
                    <AlertDialogAction onClick={onConfirm}>
                        Sync now
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

function RateRowItem({ row }: { row: RateRow }) {
    const [value, setValue] = useState(
        row.override_rate === null ? '' : String(row.override_rate),
    );
    const [saving, setSaving] = useState(false);

    const dirty = value.trim() !== (row.override_rate?.toString() ?? '');

    const save = () => {
        setSaving(true);

        router.post(
            `/currencies/${row.code}/rate`,
            { rate: value },
            {
                preserveScroll: true,
                onFinish: () => setSaving(false),
            },
        );
    };

    const clear = () => {
        setSaving(true);
        setValue('');

        router.delete(`/currencies/${row.code}/rate`, {
            preserveScroll: true,
            onFinish: () => setSaving(false),
        });
    };

    return (
        <tr className="group">
            <td className="rounded-l-2xl bg-muted/40 px-3 py-2.5 font-semibold dark:bg-white/5">
                {row.code}
            </td>

            <td className="bg-muted/40 px-3 py-2.5 tabular-nums dark:bg-white/5">
                {row.synced_rate === null ? (
                    <span className="text-muted-foreground">—</span>
                ) : (
                    row.synced_rate
                )}
            </td>

            <td className="bg-muted/40 px-3 py-2.5 dark:bg-white/5">
                <div className="flex items-center gap-1.5">
                    <Input
                        type="number"
                        step="any"
                        min="0"
                        inputMode="decimal"
                        value={value}
                        onChange={(e) => setValue(e.target.value)}
                        placeholder="—"
                        aria-label={`Manual rate for ${row.code}`}
                        className="h-8 w-28 tabular-nums"
                    />

                    {dirty && value.trim() !== '' ? (
                        <PillButton
                            size="sm"
                            onClick={save}
                            disabled={saving}
                            aria-label={`Save manual rate for ${row.code}`}
                        >
                            Save
                        </PillButton>
                    ) : null}

                    {row.override_rate !== null && !dirty ? (
                        <PillButton
                            size="sm"
                            variant="ghost"
                            className={DANGER_PILL}
                            onClick={clear}
                            disabled={saving}
                            aria-label={`Clear manual rate for ${row.code}`}
                        >
                            Clear
                        </PillButton>
                    ) : null}
                </div>
            </td>

            <td className="rounded-r-2xl bg-muted/40 px-3 py-2.5 dark:bg-white/5">
                <RateStatus row={row} />
            </td>
        </tr>
    );
}

function RateStatus({ row }: { row: RateRow }) {
    if (row.override_rate === null) {
        return <span className="text-xs text-muted-foreground">—</span>;
    }

    if (row.in_force) {
        return (
            <Chip interactive={false} active>
                In force
            </Chip>
        );
    }

    return (
        <span
            className="text-xs text-muted-foreground"
            title="A sync has run since this rate was set, so the synced rate is being used. Save it again to put it back in force."
        >
            Superseded by sync
        </span>
    );
}

/**
 * Tick which currencies the business actually uses. The catalog holds every
 * ISO 4217 code, so this is the screen that keeps the switcher down to a
 * handful rather than 150-odd entries.
 */
function ActivateCurrenciesPanel({ currencies }: { currencies: Currency[] }) {
    const [q, setQ] = useState('');
    const [saving, setSaving] = useState(false);

    const activeCodes = useMemo(
        () =>
            currencies
                .filter((c) => c.is_active)
                .map((c) => c.code)
                .sort(),
        [currencies],
    );

    const [selected, setSelected] = useState<Set<string>>(
        () => new Set(activeCodes),
    );

    /** Re-sync when the server sends a fresh list back after a save. */
    React.useEffect(() => {
        setSelected(new Set(activeCodes));
    }, [activeCodes.join(',')]);

    const filtered = useMemo(() => {
        const needle = q.trim().toLowerCase();
        if (!needle) return currencies;

        return currencies.filter((c) =>
            `${c.code} ${c.name} ${c.symbol ?? ''}`
                .toLowerCase()
                .includes(needle),
        );
    }, [currencies, q]);

    const isDirty = useMemo(() => {
        if (selected.size !== activeCodes.length) return true;

        return activeCodes.some((code) => !selected.has(code));
    }, [selected, activeCodes]);

    const toggle = (code: string) => {
        setSelected((prev) => {
            const next = new Set(prev);
            next.has(code) ? next.delete(code) : next.add(code);

            return next;
        });
    };

    const save = () => {
        setSaving(true);

        router.post(
            '/currencies/active',
            { codes: Array.from(selected) },
            {
                preserveScroll: true,
                onSuccess: () => toast.success('Active currencies updated.'),
                onError: (errors) =>
                    toast.error(
                        errors?.codes || 'Failed to update active currencies.',
                    ),
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <Panel>
            <PanelHeader
                icon={Check}
                title="Currencies you use"
                subtitle={`${selected.size} of ${currencies.length} ticked${
                    isDirty ? ' • unsaved changes' : ''
                }`}
                action={
                    <>
                        <SearchField
                            value={q}
                            onChange={setQ}
                            placeholder="Filter by code or name…"
                            className="w-full sm:w-72"
                        />

                        <PillButton
                            onClick={save}
                            disabled={!isDirty || saving}
                            className="shrink-0"
                        >
                            {saving ? (
                                <>
                                    <NiloSpinner size={16} />
                                    Saving…
                                </>
                            ) : (
                                'Save selection'
                            )}
                        </PillButton>
                    </>
                }
            />

            {filtered.length === 0 ? (
                <div className="py-10 text-center text-sm text-muted-foreground">
                    Nothing matched “{q.trim()}”.
                </div>
            ) : (
                <div className="grid max-h-96 grid-cols-1 gap-2 overflow-y-auto pr-1 sm:grid-cols-2 lg:grid-cols-3">
                    {filtered.map((c) => {
                        const checked = selected.has(c.code);

                        return (
                            <button
                                key={c.id}
                                type="button"
                                role="checkbox"
                                aria-checked={checked}
                                onClick={() => toggle(c.code)}
                                className={cn(
                                    'flex items-center gap-3 rounded-2xl px-3 py-2 text-left transition',
                                    'focus-visible:ring-2 focus-visible:ring-brand-400 focus-visible:outline-none',
                                    checked
                                        ? 'bg-brand-50 dark:bg-brand-500/15'
                                        : 'bg-muted/50 hover:bg-muted dark:bg-white/5 dark:hover:bg-white/10',
                                )}
                            >
                                <span
                                    className={cn(
                                        'grid h-5 w-5 shrink-0 place-items-center rounded-md transition',
                                        checked
                                            ? 'bg-brand text-brand-foreground'
                                            : 'bg-background text-transparent dark:bg-white/10',
                                    )}
                                >
                                    <Check className="h-3.5 w-3.5" />
                                </span>

                                <span className="min-w-0 flex-1">
                                    <span className="flex items-center gap-2">
                                        <span className="text-sm font-semibold">
                                            {c.code}
                                        </span>
                                        {c.symbol ? (
                                            <span className="text-xs text-muted-foreground">
                                                {c.symbol}
                                            </span>
                                        ) : null}
                                    </span>
                                    <span className="block truncate text-xs text-muted-foreground">
                                        {c.name}
                                    </span>
                                </span>
                            </button>
                        );
                    })}
                </div>
            )}

            <p className="mt-4 text-xs text-muted-foreground">
                Only ticked currencies appear in the switcher and when creating
                companies. A currency already used by a company or an issued
                document cannot be unticked.
            </p>
        </Panel>
    );
}

/* --------------------------- Create / edit --------------------------- */

function CurrencyModal({
    mode,
    currency,
    trigger,
}: {
    mode: 'create' | 'edit';
    currency?: Currency;
    trigger?: React.ReactNode;
}) {
    const [open, setOpen] = useState(false);

    const form = useForm({
        code: currency?.code ?? '',
        name: currency?.name ?? '',
        symbol: currency?.symbol ?? '',
        precision: currency?.precision ?? 2,
        is_active: currency?.is_active ?? true,
    });

    const closeAndReset = () => {
        setOpen(false);
        form.clearErrors();
        if (mode === 'create') form.reset();
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        const payload = {
            ...form.data,
            code: form.data.code.trim().toUpperCase(),
        };

        form.setData(payload as any);

        if (mode === 'create') {
            form.post('/currencies', {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Currency added.');
                    closeAndReset();
                },
                onError: (errors) =>
                    toast.error(
                        errors?.code ||
                            errors?.name ||
                            'Failed to add currency.',
                    ),
            });
            return;
        }

        form.put(`/currencies/${currency!.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Currency updated.');
                closeAndReset();
            },
            onError: (errors) =>
                toast.error(
                    errors?.code ||
                        errors?.name ||
                        errors?.is_active ||
                        'Failed to update currency.',
                ),
        });
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(v) => (v ? setOpen(true) : closeAndReset())}
        >
            <DialogTrigger asChild>
                {trigger ?? (
                    <Button className="h-9 gap-2 rounded-xl transition-transform hover:scale-[1.02] active:scale-[0.98]">
                        <Plus className="h-4 w-4" />
                        Add currency
                    </Button>
                )}
            </DialogTrigger>

            <DialogContent className="overflow-hidden rounded-2xl p-0 sm:max-w-xl">
                <AnimatePresence mode="wait">
                    <motion.div
                        key={open ? 'open' : 'closed'}
                        initial={{ opacity: 0, y: 14, scale: 0.99 }}
                        animate={{ opacity: 1, y: 0, scale: 1 }}
                        exit={{ opacity: 0, y: 10, scale: 0.99 }}
                        transition={{ duration: 0.22, ease: 'easeOut' }}
                    >
                        <div className="px-6 pt-6 pb-5">
                            <DialogHeader>
                                <DialogTitle className="flex items-center gap-2">
                                    <span className="grid h-9 w-9 place-items-center rounded-xl bg-muted">
                                        <Coins className="h-5 w-5 text-foreground/80" />
                                    </span>
                                    {mode === 'create'
                                        ? 'Add currency'
                                        : 'Edit currency'}
                                </DialogTitle>
                                <DialogDescription>
                                    Currency codes are ISO 4217 (e.g. ZMW, USD).
                                </DialogDescription>
                            </DialogHeader>

                            <form onSubmit={submit} className="mt-6 space-y-4">
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label>Code *</Label>
                                        <Input
                                            value={form.data.code}
                                            onChange={(e) =>
                                                form.setData(
                                                    'code',
                                                    e.target.value,
                                                )
                                            }
                                            maxLength={3}
                                            required
                                        />
                                        {form.errors.code && (
                                            <p className="text-sm text-destructive">
                                                {form.errors.code}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label>Name *</Label>
                                        <Input
                                            value={form.data.name}
                                            onChange={(e) =>
                                                form.setData(
                                                    'name',
                                                    e.target.value,
                                                )
                                            }
                                            required
                                        />
                                        {form.errors.name && (
                                            <p className="text-sm text-destructive">
                                                {form.errors.name}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label>Symbol</Label>
                                        <Input
                                            value={form.data.symbol ?? ''}
                                            onChange={(e) =>
                                                form.setData(
                                                    'symbol',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        {form.errors.symbol && (
                                            <p className="text-sm text-destructive">
                                                {form.errors.symbol}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label>Precision *</Label>
                                        <Input
                                            type="number"
                                            min={0}
                                            max={6}
                                            value={form.data.precision}
                                            onChange={(e) =>
                                                form.setData(
                                                    'precision',
                                                    Number(e.target.value),
                                                )
                                            }
                                            required
                                        />
                                        {form.errors.precision && (
                                            <p className="text-sm text-destructive">
                                                {form.errors.precision}
                                            </p>
                                        )}
                                    </div>
                                </div>

                                <div className="flex items-center justify-between rounded-2xl bg-muted/50 p-3 dark:bg-white/5">
                                    <div>
                                        <div className="text-sm font-semibold">
                                            Active
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            Only active currencies are
                                            selectable in the switcher.
                                        </div>
                                    </div>
                                    <Switch
                                        checked={!!form.data.is_active}
                                        onCheckedChange={(v: any) =>
                                            form.setData('is_active', !!v)
                                        }
                                    />
                                </div>

                                {form.errors.is_active && (
                                    <p className="text-sm text-destructive">
                                        {form.errors.is_active}
                                    </p>
                                )}

                                <Separator className="my-4" />

                                <div className="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-end">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        onClick={closeAndReset}
                                        disabled={form.processing}
                                        className="h-9 rounded-xl"
                                    >
                                        <X className="mr-2 h-4 w-4" />
                                        Cancel
                                    </Button>

                                    <Button
                                        type="submit"
                                        disabled={
                                            form.processing ||
                                            !form.data.code.trim() ||
                                            !form.data.name.trim()
                                        }
                                        className="h-9 rounded-xl"
                                    >
                                        {form.processing ? (
                                            <>
                                                <NiloSpinner
                                                    size={16}
                                                    className="mr-2"
                                                />
                                                Saving...
                                            </>
                                        ) : (
                                            'Save'
                                        )}
                                    </Button>
                                </div>
                            </form>
                        </div>
                    </motion.div>
                </AnimatePresence>
            </DialogContent>
        </Dialog>
    );
}

function DeleteCurrencyButton({ currency }: { currency: Currency }) {
    const [open, setOpen] = useState(false);

    const del = () => {
        router.delete(`/currencies/${currency.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Currency deleted.');
                setOpen(false);
            },
            onError: (errors) =>
                toast.error(errors?.currency || 'Failed to delete currency.'),
        });
    };

    return (
        <AlertDialog open={open} onOpenChange={setOpen}>
            <AlertDialogTrigger asChild>
                <PillButton variant="ghost" size="sm" className={DANGER_PILL}>
                    <Trash2 className="h-3.5 w-3.5" />
                    Delete
                </PillButton>
            </AlertDialogTrigger>

            <AlertDialogContent className="rounded-2xl">
                <AlertDialogHeader>
                    <AlertDialogTitle>Delete currency?</AlertDialogTitle>
                    <AlertDialogDescription>
                        This will permanently remove{' '}
                        <span className="font-medium">{currency.code}</span>.
                    </AlertDialogDescription>
                </AlertDialogHeader>

                <AlertDialogFooter>
                    <AlertDialogCancel className="rounded-xl">
                        Cancel
                    </AlertDialogCancel>
                    <AlertDialogAction
                        onClick={del}
                        className={cn(
                            'rounded-xl bg-destructive text-destructive-foreground hover:bg-destructive/90',
                        )}
                    >
                        Delete
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
