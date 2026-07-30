// resources/js/Pages/Suppliers/Index.tsx
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import {
    Building2,
    CheckCircle2,
    Factory,
    IdCard,
    Mail,
    PackagePlus,
    Pencil,
    SearchX,
    Trash2,
} from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

import type { PageProps } from '../../types';

import {
    FormField,
    IconButton,
    InitialsAvatar,
    Panel,
    PillButton,
    SearchField,
    StatTile,
    fieldInputClass,
    pillButtonClass,
} from '@/components/dashboard/primitives';
import NiloSpinner from '@/components/nilo-spinner';

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
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types/index.d';

interface Supplier {
    id: number;
    name: string;
    contact_person?: string | null;
    email?: string | null;
    phone?: string | null;
    tpin?: string | null;
    address?: string | null;
    city?: string | null;
    country?: string | null;
    notes?: string | null;
}

interface SuppliersIndexProps extends PageProps {
    suppliers: Supplier[];
    hasActiveCompany?: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Suppliers', href: '/suppliers' },
];

/** Field order matches the create/edit form, so the toast names the first thing the user would scroll to. */
const SUPPLIER_ERROR_ORDER = [
    'name',
    'contact_person',
    'email',
    'phone',
    'tpin',
    'address',
    'city',
    'country',
    'notes',
    'supplier',
    'company_id',
] as const;

function firstSupplierError(
    errors: Partial<Record<string, string>>,
): string | undefined {
    return SUPPLIER_ERROR_ORDER.map((field) => errors[field]).find(Boolean);
}

/** The active company from the shared Inertia `companies` prop. */
function useActiveCompanyName(): string | null {
    const page = usePage<{
        companies?: { current?: { name?: string } | null } | null;
    }>();

    return page.props.companies?.current?.name ?? null;
}

export default function SuppliersIndex({
    suppliers,
    hasActiveCompany = true,
}: SuppliersIndexProps) {
    const activeCompanyName = useActiveCompanyName();

    const [query, setQuery] = React.useState('');

    const visibleSuppliers = React.useMemo(() => {
        const needle = query.trim().toLowerCase();

        if (!needle) {
            return suppliers;
        }

        return suppliers.filter((supplier) =>
            [
                supplier.name,
                supplier.contact_person,
                supplier.email,
                supplier.phone,
                supplier.tpin,
                supplier.city,
                supplier.country,
            ].some((field) => (field ?? '').toLowerCase().includes(needle)),
        );
    }, [suppliers, query]);

    /** Coverage of the two fields that make a purchase order sendable and compliant. */
    const totals = React.useMemo(() => {
        const withEmail = suppliers.filter((s) =>
            Boolean(s.email?.trim()),
        ).length;
        const withTpin = suppliers.filter((s) =>
            Boolean(s.tpin?.trim()),
        ).length;

        return { withEmail, withTpin };
    }, [suppliers]);

    const hasSuppliers = suppliers.length > 0;
    const hasResults = visibleSuppliers.length > 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Suppliers" />

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
                        <AddOrEditSupplierModal
                            mode="create"
                            disabled={!hasActiveCompany}
                        />
                    </div>
                </div>

                {!hasActiveCompany ? (
                    <Panel className="mb-6">
                        <NoActiveCompany />
                    </Panel>
                ) : null}

                {/* Summary */}
                <div className="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
                    <StatTile
                        title={
                            activeCompanyName
                                ? `Suppliers • ${activeCompanyName}`
                                : 'Suppliers'
                        }
                        value={`${suppliers.length}`}
                        icon={Factory}
                        sub={
                            query.trim()
                                ? `${visibleSuppliers.length} matching “${query.trim()}”`
                                : undefined
                        }
                    />
                    <StatTile
                        title="With email"
                        value={`${totals.withEmail}`}
                        icon={Mail}
                        sub={
                            hasSuppliers
                                ? `${suppliers.length - totals.withEmail} cannot be sent an order by email`
                                : undefined
                        }
                    />
                    <StatTile
                        title="With TPIN"
                        value={`${totals.withTpin}`}
                        icon={IdCard}
                        sub={
                            hasSuppliers
                                ? `${suppliers.length - totals.withTpin} missing tax details`
                                : undefined
                        }
                    />
                </div>

                {/* Supplier list */}
                <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-start gap-2.5">
                        <span className="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">
                            <Factory className="h-4 w-4" />
                        </span>
                        <div>
                            {/*
                                Clients and suppliers wear the same panels on
                                purpose. The one thing that must never be
                                ambiguous is which direction the money runs, so
                                the heading says it outright rather than leaving
                                the noun to carry it alone.
                            */}
                            <div className="text-sm font-semibold">
                                Your suppliers
                            </div>
                            <div className="mt-0.5 text-xs text-muted-foreground">
                                {hasSuppliers && query.trim()
                                    ? `Who you buy from • ${visibleSuppliers.length} of ${suppliers.length} shown`
                                    : `Who you buy from • ${suppliers.length} total`}
                            </div>
                        </div>
                    </div>

                    {hasSuppliers ? (
                        <SearchField
                            value={query}
                            onChange={setQuery}
                            placeholder="Search name, contact, email, TPIN…"
                            className="w-full sm:w-72"
                        />
                    ) : null}
                </div>

                <Panel>
                    {!hasSuppliers ? (
                        <EmptySuppliers hasActiveCompany={hasActiveCompany} />
                    ) : !hasResults ? (
                        <NoSearchResults
                            query={query}
                            onClear={() => setQuery('')}
                        />
                    ) : (
                        <SupplierTable suppliers={visibleSuppliers} />
                    )}
                </Panel>
            </div>
        </AppLayout>
    );
}

/* --------------------------- Supplier table --------------------------- */

/** Rows read as tinted tiles rather than ruled lines, matching the panels. */
const cellClass = cn(
    'bg-muted/40 px-3 py-3 align-middle transition dark:bg-white/5',
    'group-hover:bg-brand-50/70 dark:group-hover:bg-brand-500/10',
);

function SupplierTable({ suppliers }: { suppliers: Supplier[] }) {
    return (
        <div className="-mx-1 overflow-x-auto px-1">
            <table className="w-full min-w-[46rem] border-separate border-spacing-y-1.5 text-sm">
                <thead>
                    <tr className="text-left text-xs text-muted-foreground">
                        <th className="px-3 pb-1 font-medium">Supplier</th>
                        <th className="px-3 pb-1 font-medium">Email</th>
                        <th className="px-3 pb-1 font-medium">Phone</th>
                        <th className="px-3 pb-1 font-medium">Location</th>
                        <th className="px-3 pb-1 text-right font-medium">
                            Actions
                        </th>
                    </tr>
                </thead>

                <tbody>
                    {suppliers.map((supplier, index) => (
                        <SupplierRow
                            key={supplier.id}
                            supplier={supplier}
                            index={index}
                        />
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function SupplierRow({
    supplier,
    index,
}: {
    supplier: Supplier;
    index: number;
}) {
    const location =
        [supplier.city, supplier.country].filter(Boolean).join(', ') || null;

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
                    <InitialsAvatar name={supplier.name} />

                    <div className="min-w-0">
                        <div className="truncate font-semibold">
                            {supplier.name}
                        </div>
                        <div className="mt-0.5 truncate text-xs text-muted-foreground">
                            {supplier.contact_person
                                ? supplier.contact_person
                                : 'No contact person'}
                            {supplier.tpin ? ` • TPIN ${supplier.tpin}` : ''}
                        </div>
                    </div>
                </div>
            </td>

            <td className={cellClass}>
                {supplier.email ? (
                    <a
                        href={`mailto:${supplier.email}`}
                        className="truncate transition hover:text-brand-700 dark:hover:text-brand-300"
                    >
                        {supplier.email}
                    </a>
                ) : (
                    <span className="text-muted-foreground/70">—</span>
                )}
            </td>

            <td className={cellClass}>
                {supplier.phone ? (
                    <span className="tabular-nums">{supplier.phone}</span>
                ) : (
                    <span className="text-muted-foreground/70">—</span>
                )}
            </td>

            <td className={cellClass}>
                {location ? (
                    <span className="truncate">{location}</span>
                ) : (
                    <span className="text-muted-foreground/70">—</span>
                )}
            </td>

            <td className={cn(cellClass, 'rounded-r-2xl text-right')}>
                <div className="flex items-center justify-end gap-2">
                    <AddOrEditSupplierModal mode="edit" supplier={supplier} />
                    <DeleteSupplierButton supplier={supplier} />
                </div>
            </td>
        </motion.tr>
    );
}

/* --------------------------- Empty states --------------------------- */

/**
 * Crates on a pallet rather than the tilted contact card the client list uses.
 * The two pages are otherwise identical, so the artwork is what tells a glance
 * which of the two it is looking at. Drawn with theme tokens so it inverts
 * cleanly rather than sitting as a bright block in dark mode.
 */
function EmptySuppliersArt() {
    return (
        <svg
            viewBox="0 0 220 150"
            className="h-36 w-52"
            fill="none"
            aria-hidden="true"
        >
            <ellipse
                cx="110"
                cy="130"
                rx="64"
                ry="8"
                className="fill-black/[0.05] dark:fill-white/[0.06]"
            />

            {/* Pallet */}
            <rect
                x="58"
                y="112"
                width="104"
                height="9"
                rx="4.5"
                className="fill-muted dark:fill-white/10"
            />

            {/* Back crate, tilted */}
            <g transform="rotate(-7 78 92)">
                <rect
                    x="56"
                    y="70"
                    width="46"
                    height="42"
                    rx="9"
                    className="fill-muted dark:fill-white/5"
                />
            </g>

            {/* Front crate */}
            <rect
                x="88"
                y="60"
                width="70"
                height="52"
                rx="12"
                className="fill-card stroke-black/[0.06] dark:stroke-white/10"
                strokeWidth="1.5"
            />
            <path
                d="M88 78h70"
                className="stroke-black/[0.06] dark:stroke-white/10"
                strokeWidth="1.5"
            />

            {/* Tape down the middle of the front crate */}
            <rect
                x="117"
                y="60"
                width="12"
                height="52"
                className="fill-brand-100 dark:fill-brand-500/25"
            />

            {/* Shipping label */}
            <rect
                x="98"
                y="86"
                width="30"
                height="7"
                rx="3.5"
                className="fill-muted dark:fill-white/10"
            />
            <rect
                x="98"
                y="97"
                width="20"
                height="6"
                rx="3"
                className="fill-muted dark:fill-white/10"
            />

            {/* Inbound arrow — goods arriving, not invoices leaving */}
            <path
                d="M172 44v28m0 0-9-9m9 9 9-9"
                className="stroke-brand-600 dark:stroke-brand-300"
                strokeWidth="3"
                strokeLinecap="round"
                strokeLinejoin="round"
            />

            <circle
                cx="52"
                cy="40"
                r="4"
                className="fill-brand-200 dark:fill-brand-500/40"
            />
            <circle
                cx="196"
                cy="96"
                r="5"
                className="fill-brand-100 dark:fill-brand-500/25"
            />
            <circle
                cx="44"
                cy="98"
                r="3"
                className="fill-brand-200 dark:fill-brand-500/40"
            />
        </svg>
    );
}

const SUPPLIER_TIPS = [
    'Add an email so purchase orders can be sent straight from Nilo.',
    'A TPIN keeps the paperwork on what you buy compliant.',
    'A contact person saves you the “who do I chase?” moment.',
];

function EmptySuppliers({ hasActiveCompany }: { hasActiveCompany: boolean }) {
    return (
        <div className="flex flex-col items-center px-4 py-10 text-center">
            <EmptySuppliersArt />

            <div className="mt-4 text-sm font-semibold">No suppliers yet</div>
            <p className="mt-1 max-w-sm text-sm text-muted-foreground">
                Add your first supplier to start raising purchase orders against
                them.
            </p>

            <ul className="mt-5 flex max-w-sm flex-col gap-1.5 text-left text-xs text-muted-foreground">
                {SUPPLIER_TIPS.map((tip) => (
                    <li key={tip} className="flex items-start gap-2">
                        <CheckCircle2 className="mt-0.5 h-3.5 w-3.5 shrink-0 text-brand-600 dark:text-brand-300" />
                        {tip}
                    </li>
                ))}
            </ul>

            <div className="mt-5">
                <AddOrEditSupplierModal
                    mode="create"
                    disabled={!hasActiveCompany}
                />
            </div>
        </div>
    );
}

function NoSearchResults({
    query,
    onClear,
}: {
    query: string;
    onClear: () => void;
}) {
    return (
        <div className="flex flex-col items-center px-4 py-10 text-center">
            <span className="grid h-14 w-14 place-items-center rounded-2xl bg-muted/60 text-muted-foreground dark:bg-white/5">
                <SearchX className="h-6 w-6" />
            </span>

            <div className="mt-4 text-sm font-semibold">No matches</div>
            <p className="mt-1 max-w-sm text-sm text-muted-foreground">
                Nothing matched “{query.trim()}”. Try a different name, contact,
                email or TPIN.
            </p>

            <PillButton
                variant="ghost"
                size="sm"
                className="mt-5"
                onClick={onClear}
            >
                Clear search
            </PillButton>
        </div>
    );
}

function NoActiveCompany() {
    return (
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
            <span className="grid h-10 w-10 shrink-0 place-items-center rounded-2xl bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">
                <Building2 className="h-5 w-5" />
            </span>

            <div className="min-w-0 flex-1">
                <div className="text-sm font-semibold">
                    Add or select a company first
                </div>
                <p className="mt-0.5 text-sm text-muted-foreground">
                    Suppliers belong to a company, so one has to be active
                    before suppliers can be listed or created.
                </p>
            </div>

            <Link
                href="/companies"
                className={pillButtonClass('solid', 'sm', 'shrink-0')}
            >
                Manage companies
            </Link>
        </div>
    );
}

/* --------------------------- Add / Edit supplier --------------------------- */

function AddOrEditSupplierModal({
    mode,
    supplier,
    disabled = false,
}: {
    mode: 'create' | 'edit';
    supplier?: Supplier;
    disabled?: boolean;
}) {
    const [open, setOpen] = React.useState(false);

    /** Ids must stay unique — an edit dialog is rendered per table row. */
    const idPrefix =
        mode === 'create' ? 'create_supplier' : `supplier_${supplier!.id}`;

    const form = useForm({
        name: supplier?.name ?? '',
        contact_person: supplier?.contact_person ?? '',
        email: supplier?.email ?? '',
        phone: supplier?.phone ?? '',
        tpin: supplier?.tpin ?? '',
        address: supplier?.address ?? '',
        city: supplier?.city ?? '',
        country: supplier?.country ?? '',
        notes: supplier?.notes ?? '',
    });

    const closeAndReset = () => {
        setOpen(false);
        form.clearErrors();

        if (mode === 'create') {
            form.reset();
        }
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        const isCreate = mode === 'create';

        const toastId = toast.loading(
            isCreate ? 'Creating supplier...' : 'Updating supplier...',
        );

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(
                    isCreate ? 'Supplier created.' : 'Supplier updated.',
                    {
                        id: toastId,
                    },
                );
                closeAndReset();
            },
            onError: (errors: Partial<Record<string, string>>) => {
                toast.error(
                    firstSupplierError(errors) ??
                        (isCreate
                            ? 'Failed to create supplier.'
                            : 'Failed to update supplier.'),
                    { id: toastId },
                );
            },
        };

        if (isCreate) {
            form.post('/suppliers', options);

            return;
        }

        form.put(`/suppliers/${supplier!.id}`, options);
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(v) => (v ? setOpen(true) : closeAndReset())}
        >
            <DialogTrigger asChild>
                {mode === 'create' ? (
                    <PillButton disabled={disabled}>
                        <PackagePlus className="h-4 w-4" />
                        Add supplier
                    </PillButton>
                ) : (
                    <PillButton variant="ghost" size="sm">
                        <Pencil className="h-3.5 w-3.5" />
                        Edit
                    </PillButton>
                )}
            </DialogTrigger>

            <DialogContent
                aria-describedby={undefined}
                className="max-h-[88vh] w-fit max-w-[min(48rem,calc(100vw_-_2rem))] min-w-[min(32rem,calc(100vw_-_2rem))] gap-0 overflow-y-auto rounded-2xl p-0 sm:max-w-[min(48rem,calc(100vw_-_2rem))]"
            >
                <form onSubmit={submit} className="min-w-0 p-6">
                    <DialogTitle className="text-base font-semibold">
                        {mode === 'create' ? 'New supplier' : 'Edit supplier'}
                    </DialogTitle>

                    <div className="mt-5 grid grid-cols-2 gap-3">
                        <FormField
                            label="Supplier name"
                            htmlFor={`${idPrefix}_name`}
                            required
                            className="col-span-2"
                        >
                            <input
                                id={`${idPrefix}_name`}
                                className={fieldInputClass}
                                value={form.data.name}
                                onChange={(e) =>
                                    form.setData('name', e.target.value)
                                }
                                autoFocus
                                required
                            />
                        </FormField>

                        {form.errors.name ? (
                            <p className="col-span-2 -mt-1 text-xs text-destructive">
                                {form.errors.name}
                            </p>
                        ) : null}

                        <FormField
                            label="Contact person"
                            htmlFor={`${idPrefix}_contact_person`}
                        >
                            <input
                                id={`${idPrefix}_contact_person`}
                                className={fieldInputClass}
                                value={form.data.contact_person ?? ''}
                                onChange={(e) =>
                                    form.setData(
                                        'contact_person',
                                        e.target.value,
                                    )
                                }
                            />
                        </FormField>

                        <FormField label="TPIN" htmlFor={`${idPrefix}_tpin`}>
                            <input
                                id={`${idPrefix}_tpin`}
                                className={fieldInputClass}
                                value={form.data.tpin ?? ''}
                                onChange={(e) =>
                                    form.setData('tpin', e.target.value)
                                }
                            />
                        </FormField>

                        <FormField label="Email" htmlFor={`${idPrefix}_email`}>
                            <input
                                id={`${idPrefix}_email`}
                                type="email"
                                className={fieldInputClass}
                                value={form.data.email ?? ''}
                                onChange={(e) =>
                                    form.setData('email', e.target.value)
                                }
                            />
                        </FormField>

                        <FormField label="Phone" htmlFor={`${idPrefix}_phone`}>
                            <input
                                id={`${idPrefix}_phone`}
                                className={fieldInputClass}
                                value={form.data.phone ?? ''}
                                onChange={(e) =>
                                    form.setData('phone', e.target.value)
                                }
                            />
                        </FormField>

                        <FormField
                            label="Address"
                            htmlFor={`${idPrefix}_address`}
                            className="col-span-2"
                        >
                            <input
                                id={`${idPrefix}_address`}
                                className={fieldInputClass}
                                value={form.data.address ?? ''}
                                onChange={(e) =>
                                    form.setData('address', e.target.value)
                                }
                            />
                        </FormField>

                        <FormField label="City" htmlFor={`${idPrefix}_city`}>
                            <input
                                id={`${idPrefix}_city`}
                                className={fieldInputClass}
                                value={form.data.city ?? ''}
                                onChange={(e) =>
                                    form.setData('city', e.target.value)
                                }
                            />
                        </FormField>

                        <FormField
                            label="Country"
                            htmlFor={`${idPrefix}_country`}
                        >
                            <input
                                id={`${idPrefix}_country`}
                                className={fieldInputClass}
                                value={form.data.country ?? ''}
                                onChange={(e) =>
                                    form.setData('country', e.target.value)
                                }
                            />
                        </FormField>

                        <FormField
                            label="Notes"
                            htmlFor={`${idPrefix}_notes`}
                            className="col-span-2"
                        >
                            <textarea
                                id={`${idPrefix}_notes`}
                                rows={3}
                                className={cn(
                                    fieldInputClass,
                                    'h-auto resize-none py-2.5',
                                )}
                                value={form.data.notes ?? ''}
                                onChange={(e) =>
                                    form.setData('notes', e.target.value)
                                }
                            />
                        </FormField>
                    </div>

                    <div className="mt-6 flex items-center justify-end gap-2">
                        <button
                            type="button"
                            onClick={closeAndReset}
                            disabled={form.processing}
                            className="h-10 rounded-xl px-4 text-sm text-muted-foreground transition hover:text-foreground disabled:opacity-50"
                        >
                            Cancel
                        </button>

                        <button
                            type="submit"
                            disabled={form.processing || !form.data.name.trim()}
                            className="flex h-10 items-center gap-2 rounded-xl bg-primary px-5 text-sm font-medium text-primary-foreground transition hover:bg-primary/90 disabled:opacity-50"
                        >
                            {form.processing ? <NiloSpinner size={16} /> : null}
                            {mode === 'create'
                                ? 'Create supplier'
                                : 'Save changes'}
                        </button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

/* --------------------------- Delete supplier --------------------------- */

function DeleteSupplierButton({ supplier }: { supplier: Supplier }) {
    const [open, setOpen] = React.useState(false);

    const remove = () => {
        const toastId = toast.loading('Deleting supplier...');

        router.delete(`/suppliers/${supplier.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Supplier deleted.', { id: toastId });
                setOpen(false);
            },
            onError: (errors) => {
                toast.error(
                    firstSupplierError(errors) ?? 'Failed to delete supplier.',
                    {
                        id: toastId,
                    },
                );
            },
        });
    };

    return (
        <AlertDialog open={open} onOpenChange={setOpen}>
            <AlertDialogTrigger asChild>
                <IconButton
                    label={`Delete ${supplier.name}`}
                    className="h-8 w-8 bg-transparent hover:bg-destructive/10 hover:text-destructive dark:bg-transparent dark:hover:bg-destructive/15"
                >
                    <Trash2 className="h-4 w-4" />
                </IconButton>
            </AlertDialogTrigger>

            <AlertDialogContent className="rounded-2xl">
                <AlertDialogHeader>
                    <AlertDialogTitle>Delete supplier?</AlertDialogTitle>
                    <AlertDialogDescription>
                        This permanently removes{' '}
                        <span className="font-medium">{supplier.name}</span>.
                    </AlertDialogDescription>
                </AlertDialogHeader>

                <AlertDialogFooter>
                    <AlertDialogCancel className="rounded-xl">
                        Cancel
                    </AlertDialogCancel>
                    <AlertDialogAction
                        onClick={remove}
                        className="rounded-xl bg-destructive text-destructive-foreground hover:bg-destructive/90"
                    >
                        Delete
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
