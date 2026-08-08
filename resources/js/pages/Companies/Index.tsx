// resources/js/Pages/Companies/Index.tsx
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import {
    Building2,
    CheckCircle2,
    ChevronRight,
    Clock,
    Download,
    FileText,
    Image as ImageIcon,
    Package,
    Paperclip,
    Pencil,
    Plus,
    SearchX,
    Trash2,
    UploadCloud,
    Wrench,
    X,
} from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';
import type { PageProps } from '../../types';

import {
    Chip,
    FormField,
    Panel,
    PillButton,
    SearchField,
    StatTile,
    fieldInputClass,
    pillButtonClass,
} from '@/components/dashboard/primitives';
import { type FxMeta } from '@/components/fx-note';
import LimitNoticeDialog, {
    type LimitNotice,
} from '@/components/limit-notice-dialog';
import {
    Money,
    formatMoneyText,
    useActiveCurrency,
    type CurrencyLike,
} from '@/components/money';
import NiloSpinner from '@/components/nilo-spinner';

// shadcn/ui
import { Badge } from '@/components/ui/badge';
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
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types/index.d';

type CompanyType = 'services' | 'products';

const COMPANY_TYPES: {
    value: CompanyType;
    label: string;
    description: string;
    icon: typeof Wrench;
}[] = [
    {
        value: 'services',
        label: 'Services business',
        description: 'Bill for time, projects and deliverables.',
        icon: Wrench,
    },
    {
        value: 'products',
        label: 'Products business',
        description: 'Sell physical or stocked goods.',
        icon: Package,
    },
];

const DEFAULT_COMPANY_TYPE: CompanyType = 'services';

const FALLBACK_CURRENCY_CODE = 'ZMW';

/** Active currencies from the shared Inertia `currencies` prop. */
function useAvailableCurrencies(): CurrencyLike[] {
    const page = usePage<{
        currencies?: { all?: CurrencyLike[] | null } | null;
    }>();

    return page.props.currencies?.all ?? [];
}

interface CompanyDocument {
    id: number;
    name: string;
    original_filename: string;
    mime_type: string;
    size: number;
}

interface ProfileCompletion {
    filled: number;
    total: number;
    percent: number;
}

interface Company {
    id: number;
    name: string;
    type: CompanyType;
    currency_code: string;

    email?: string | null;
    phone?: string | null;
    tpin?: string | null;
    address?: string | null;

    logo_path?: string | null;
    logo_url?: string | null;
    primary_color?: string | null;

    clients_count?: number;
    total_invoices?: number;

    paid_revenue?: number;
    pending_revenue?: number;

    documents?: CompanyDocument[];
    profile_completion?: ProfileCompletion;
}

interface CompaniesIndexProps extends PageProps {
    companies: Company[];
    active_company_id: number | null;
    fx?: FxMeta | null;
    limitNotice?: LimitNotice | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Companies', href: '/companies' },
];

/** Field order matches the create/edit forms, so the toast names the first thing the user would scroll to. */
const COMPANY_ERROR_ORDER = [
    'name',
    'type',
    'currency_code',
    'email',
    'phone',
    'tpin',
    'address',
    'primary_color',
    'logo',
    'company',
] as const;

function firstCompanyError(
    errors: Partial<Record<string, string>>,
): string | undefined {
    return COMPANY_ERROR_ORDER.map((field) => errors[field]).find(Boolean);
}

const getCsrfHeaders = (): Record<string, string> => {
    const token = (
        document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement
    )?.content;

    if (!token) {
        return {};
    }

    return {
        'X-CSRF-TOKEN': token,
    };
};

export default function CompaniesIndex({
    companies,
    active_company_id,
    limitNotice = null,
}: CompaniesIndexProps) {
    const activeCompany = React.useMemo(
        () => companies.find((c) => c.id === active_company_id) || null,
        [companies, active_company_id],
    );

    const currency = useActiveCurrency();

    /** Plain-string form, for the summary sub-lines that interpolate amounts. */
    const money = (n: number | null | undefined) =>
        formatMoneyText(Number(n ?? 0), currency);

    // ✅ totals across all companies (optional context)
    const allTotals = React.useMemo(() => {
        const paid = companies.reduce(
            (a, c) => a + Number(c.paid_revenue ?? 0),
            0,
        );
        const pending = companies.reduce(
            (a, c) => a + Number(c.pending_revenue ?? 0),
            0,
        );
        const clients = companies.reduce(
            (a, c) => a + Number(c.clients_count ?? 0),
            0,
        );
        const invoices = companies.reduce(
            (a, c) => a + Number(c.total_invoices ?? 0),
            0,
        );
        return { paid, pending, clients, invoices };
    }, [companies]);

    // ✅ totals for ACTIVE company (this is what must change when you switch)
    const activeTotals = React.useMemo(() => {
        const c = activeCompany;
        return {
            paid: Number(c?.paid_revenue ?? 0),
            pending: Number(c?.pending_revenue ?? 0),
            clients: Number(c?.clients_count ?? 0),
            invoices: Number(c?.total_invoices ?? 0),
        };
    }, [activeCompany]);

    const [query, setQuery] = React.useState('');

    const visibleCompanies = React.useMemo(() => {
        const needle = query.trim().toLowerCase();

        const matched = needle
            ? companies.filter((company) =>
                  [
                      company.name,
                      company.email,
                      company.phone,
                      company.tpin,
                      company.address,
                  ].some((field) =>
                      (field ?? '').toLowerCase().includes(needle),
                  ),
              )
            : companies;

        /** The active company leads, then alphabetical. */
        return [...matched].sort((a, b) => {
            const activeDelta =
                (b.id === active_company_id ? 1 : 0) -
                (a.id === active_company_id ? 1 : 0);

            return activeDelta !== 0
                ? activeDelta
                : a.name.localeCompare(b.name);
        });
    }, [companies, query, active_company_id]);

    const hasCompanies = companies.length > 0;
    const hasResults = visibleCompanies.length > 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Companies" />

            {/* Plan refusals stop the work, so they get a dialog not a toast. */}
            <LimitNoticeDialog notice={limitNotice} />

            <div className="mx-auto w-full py-3">
                {/* Header */}
                <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="min-w-0">
                        {activeCompany ? (
                            <div className="flex items-center gap-2">
                                <Badge variant="secondary" className="gap-1">
                                    <CheckCircle2 className="h-3.5 w-3.5" />
                                    Active:{' '}
                                    <span className="font-medium">
                                        {activeCompany.name}
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
                        <AddCompanyModal />
                    </div>
                </div>

                {/* ✅ Summary cards (ACTIVE company) */}
                <div className="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
                    <StatTile
                        title={
                            activeCompany
                                ? `Paid revenue • ${activeCompany.name}`
                                : 'Paid revenue'
                        }
                        value={<Money amount={activeTotals.paid} />}
                        icon={CheckCircle2}
                        sub={
                            activeCompany
                                ? `All companies: ${money(allTotals.paid)}`
                                : undefined
                        }
                    />
                    <StatTile
                        title={
                            activeCompany
                                ? `Pending revenue • ${activeCompany.name}`
                                : 'Pending revenue'
                        }
                        value={<Money amount={activeTotals.pending} />}
                        icon={Clock}
                        sub={
                            activeCompany
                                ? `All companies: ${money(allTotals.pending)}`
                                : undefined
                        }
                    />
                    <StatTile
                        title={
                            activeCompany
                                ? `Clients • ${activeCompany.name}`
                                : 'Clients'
                        }
                        value={`${activeTotals.clients}`}
                        icon={Building2}
                        sub={
                            activeCompany
                                ? `Invoices: ${activeTotals.invoices} • All companies: ${allTotals.clients} clients`
                                : `All companies: ${allTotals.clients} clients • ${allTotals.invoices} invoices`
                        }
                    />
                </div>

                {/* Company cards */}
                <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-start gap-2.5">
                        <span className="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">
                            <Building2 className="h-4 w-4" />
                        </span>
                        <div>
                            <div className="text-sm font-semibold">
                                Your companies
                            </div>
                            <div className="mt-0.5 text-xs text-muted-foreground">
                                {hasCompanies && query.trim()
                                    ? `${visibleCompanies.length} of ${companies.length} shown`
                                    : `${companies.length} total`}
                            </div>
                        </div>
                    </div>

                    {hasCompanies ? (
                        <SearchField
                            value={query}
                            onChange={setQuery}
                            placeholder="Search name, email, TPIN…"
                            className="w-full sm:w-72"
                        />
                    ) : null}
                </div>

                {!hasCompanies ? (
                    <Panel>
                        <EmptyCompanies />
                    </Panel>
                ) : !hasResults ? (
                    <Panel>
                        <NoSearchResults
                            query={query}
                            onClear={() => setQuery('')}
                        />
                    </Panel>
                ) : (
                    /* Horizontal rail. The negative inset lets card shadows
                       breathe without the scroll container clipping them. */
                    <div
                        className="-mx-1 flex snap-x snap-mandatory gap-4 overflow-x-auto px-1 pt-1 pb-3"
                        role="region"
                        aria-label="Your companies"
                        tabIndex={0}
                    >
                        {visibleCompanies.map((company, index) => (
                            <CompanyCard
                                key={company.id}
                                company={company}
                                isActive={company.id === active_company_id}
                                index={index}
                            />
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

/* --------------------------- Company card --------------------------- */

/**
 * Completion reads as a warning while a profile is barely filled, brand while
 * it is on its way, and green once nothing is left to add.
 */
function completionTone(percent: number): { bar: string; text: string } {
    if (percent >= 100) {
        return {
            bar: 'bg-emerald-500',
            text: 'text-emerald-600 dark:text-emerald-400',
        };
    }

    if (percent < 50) {
        return {
            bar: 'bg-amber-500',
            text: 'text-amber-600 dark:text-amber-400',
        };
    }

    return { bar: 'bg-brand', text: 'text-foreground' };
}

function CompanyCard({
    company,
    isActive,
    index,
}: {
    company: Company;
    isActive: boolean;
    index: number;
}) {
    const logoUrl = company.logo_url ?? null;
    const documents = company.documents ?? [];

    const completion = company.profile_completion ?? {
        filled: 0,
        total: 9,
        percent: 0,
    };

    const tone = completionTone(completion.percent);

    const setActive = () => {
        router.post(
            '/companies/switch',
            { company_id: company.id },
            {
                preserveScroll: true,
                onSuccess: () => toast.success('Company switched.'),
                onError: (errors) => {
                    const msg =
                        (errors as Record<string, string>)?.company_id ||
                        'Failed to switch company.';
                    toast.error(msg);
                },
            },
        );
    };

    return (
        <motion.div
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{
                duration: 0.25,
                delay: Math.min(0.03 * index, 0.18),
            }}
            className={cn(
                'flex flex-col rounded-3xl bg-card p-5 transition',
                /* Fixed width so the rail scrolls instead of squeezing cards. */
                'w-[19rem] shrink-0 snap-start sm:w-[21rem]',
                'shadow-[0_1px_2px_0_rgb(16_24_40/0.04),0_12px_32px_-14px_rgb(16_24_40/0.16)]',
                'dark:shadow-none dark:ring-1 dark:ring-white/10',
                isActive && 'ring-2 ring-brand-400 dark:ring-brand-400/60',
            )}
        >
            {/* Identity */}
            <div className="flex items-start gap-3">
                <div
                    className={cn(
                        'grid h-12 w-12 shrink-0 place-items-center overflow-hidden rounded-2xl bg-muted/60 dark:bg-white/5',
                        isActive && 'bg-brand-100 dark:bg-brand-500/20',
                    )}
                >
                    {logoUrl ? (
                        <img
                            src={logoUrl}
                            className="h-full w-full object-cover"
                            alt={`${company.name} logo`}
                        />
                    ) : (
                        <Building2
                            className={cn(
                                'h-5 w-5 text-foreground/70',
                                isActive && 'text-primary',
                            )}
                        />
                    )}
                </div>

                <div className="min-w-0 flex-1">
                    <div className="truncate text-sm font-semibold">
                        {company.name}
                    </div>
                    <div className="mt-0.5 text-xs text-muted-foreground">
                        ID: {company.id}
                    </div>
                </div>

                {isActive ? (
                    <Chip active interactive={false}>
                        <CheckCircle2 className="h-3.5 w-3.5" />
                        Active
                    </Chip>
                ) : null}
            </div>

            {/* Meta chips */}
            <div className="mt-3 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                <span className="inline-flex items-center gap-1 rounded-full bg-muted/60 px-2 py-0.5 dark:bg-white/5">
                    {company.type === 'products' ? (
                        <Package className="h-3 w-3" />
                    ) : (
                        <Wrench className="h-3 w-3" />
                    )}
                    {company.type === 'products' ? 'Products' : 'Services'}
                </span>

                <span className="inline-flex items-center rounded-full bg-muted/60 px-2 py-0.5 dark:bg-white/5">
                    {company.currency_code ?? FALLBACK_CURRENCY_CODE}
                </span>

                {company.primary_color ? (
                    <span className="inline-flex items-center gap-1 rounded-full bg-muted/60 px-2 py-0.5 dark:bg-white/5">
                        <span
                            className="h-2.5 w-2.5 rounded-full"
                            style={{
                                backgroundColor: company.primary_color,
                            }}
                        />
                        {company.primary_color}
                    </span>
                ) : null}
            </div>

            {/* Revenue + volume */}
            <div className="mt-4 grid grid-cols-2 gap-3">
                <div className="rounded-2xl bg-muted/50 p-3 dark:bg-white/5">
                    <div className="text-xs text-muted-foreground">
                        Paid revenue
                    </div>
                    <div className="mt-1 text-sm font-semibold text-emerald-600 tabular-nums dark:text-emerald-400">
                        <Money amount={Number(company.paid_revenue ?? 0)} />
                    </div>
                </div>

                <div className="rounded-2xl bg-muted/50 p-3 dark:bg-white/5">
                    <div className="text-xs text-muted-foreground">
                        Pending revenue
                    </div>
                    <div className="mt-1 text-sm font-semibold text-amber-600 tabular-nums dark:text-amber-400">
                        <Money amount={Number(company.pending_revenue ?? 0)} />
                    </div>
                </div>

                <div className="rounded-2xl bg-muted/50 p-3 dark:bg-white/5">
                    <div className="text-xs text-muted-foreground">Clients</div>
                    <div className="mt-1 text-sm font-semibold tabular-nums">
                        {company.clients_count ?? 0}
                    </div>
                </div>

                <div className="rounded-2xl bg-muted/50 p-3 dark:bg-white/5">
                    <div className="text-xs text-muted-foreground">
                        Invoices
                    </div>
                    <div className="mt-1 text-sm font-semibold tabular-nums">
                        {company.total_invoices ?? 0}
                    </div>
                </div>
            </div>

            {/* Profile completion */}
            <div className="mt-4">
                <div className="flex items-center justify-between text-xs">
                    <span className="font-medium">Company details</span>
                    <span className={cn('tabular-nums', tone.text)}>
                        {completion.filled}/{completion.total} •{' '}
                        {completion.percent}%
                    </span>
                </div>

                <div
                    className="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-muted dark:bg-white/10"
                    role="progressbar"
                    aria-valuenow={completion.percent}
                    aria-valuemin={0}
                    aria-valuemax={100}
                    aria-label={`${company.name} profile completion`}
                >
                    <div
                        className={cn(
                            'h-full rounded-full transition-all',
                            tone.bar,
                        )}
                        style={{ width: `${completion.percent}%` }}
                    />
                </div>
            </div>

            {/* Compliance */}
            <ComplianceModal company={company} documentCount={documents.length}>
                <button
                    type="button"
                    className={cn(
                        'mt-4 flex w-full items-center gap-2.5 rounded-2xl bg-muted/50 p-3 text-left transition',
                        'hover:bg-muted focus-visible:ring-2 focus-visible:ring-brand-400 focus-visible:outline-none',
                        'dark:bg-white/5 dark:hover:bg-white/10',
                    )}
                >
                    <span className="grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-background text-muted-foreground">
                        <Paperclip className="h-4 w-4" />
                    </span>

                    <span className="min-w-0 flex-1">
                        <span className="block text-xs font-medium">
                            Compliance documents
                        </span>
                        <span className="block text-xs text-muted-foreground">
                            {documents.length === 0
                                ? 'None uploaded yet'
                                : `${documents.length} ${documents.length === 1 ? 'document' : 'documents'}`}
                        </span>
                    </span>

                    <ChevronRight className="h-4 w-4 shrink-0 text-muted-foreground" />
                </button>
            </ComplianceModal>

            {/* Actions */}
            <div className="mt-4 flex items-center justify-end gap-2 pt-1">
                <EditCompanyModal company={company} />

                {isActive ? (
                    <PillButton variant="ghost" size="sm" disabled>
                        Current
                    </PillButton>
                ) : (
                    <PillButton variant="soft" size="sm" onClick={setActive}>
                        Set active
                    </PillButton>
                )}
            </div>
        </motion.div>
    );
}

/* --------------------------- Compliance documents --------------------------- */

const MAX_DOCUMENT_BYTES = 5 * 1024 * 1024;

function formatFileSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(0)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function ComplianceModal({
    company,
    documentCount,
    children,
}: {
    company: Company;
    documentCount: number;
    children: React.ReactNode;
}) {
    const [open, setOpen] = React.useState(false);

    const documents = company.documents ?? [];

    const form = useForm<{ name: string; file: File | null }>({
        name: '',
        file: null,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        const toastId = toast.loading('Uploading document...');

        form.post(`/companies/${company.id}/documents`, {
            preserveScroll: true,
            forceFormData: true,
            headers: getCsrfHeaders(),
            onSuccess: () => {
                toast.success('Document uploaded.', { id: toastId });
                form.reset();
                form.clearErrors();
            },
            onError: (errors) => {
                const first =
                    (errors as Record<string, string>)?.name ||
                    (errors as Record<string, string>)?.file;

                toast.error(first ?? 'Failed to upload document.', {
                    id: toastId,
                });
            },
        });
    };

    const remove = (document: CompanyDocument) => {
        const toastId = toast.loading('Deleting document...');

        router.delete(`/companies/${company.id}/documents/${document.id}`, {
            preserveScroll: true,
            headers: getCsrfHeaders(),
            onSuccess: () =>
                toast.success('Document deleted.', { id: toastId }),
            onError: () =>
                toast.error('Failed to delete document.', { id: toastId }),
        });
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(v) => {
                setOpen(v);

                if (!v) {
                    form.reset();
                    form.clearErrors();
                }
            }}
        >
            <DialogTrigger asChild>{children}</DialogTrigger>

            <DialogContent className="max-h-[88vh] overflow-y-auto rounded-2xl p-0 sm:max-w-lg">
                <div className="px-6 pt-6 pb-5">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <span className="grid h-9 w-9 place-items-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">
                                <Paperclip className="h-4 w-4" />
                            </span>
                            Compliance documents
                        </DialogTitle>
                        <DialogDescription>
                            {documentCount === 0
                                ? `Nothing uploaded for ${company.name} yet.`
                                : `${documentCount} stored for ${company.name}.`}{' '}
                            Files are private and only reachable by members of
                            this company.
                        </DialogDescription>
                    </DialogHeader>

                    {/* Existing documents */}
                    {documents.length > 0 ? (
                        <ul className="mt-5 flex flex-col gap-2">
                            {documents.map((document) => (
                                <li
                                    key={document.id}
                                    className="flex items-center gap-3 rounded-2xl bg-muted/50 p-3 dark:bg-white/5"
                                >
                                    <span className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-background text-muted-foreground">
                                        <FileText className="h-4 w-4" />
                                    </span>

                                    <div className="min-w-0 flex-1">
                                        <div className="truncate text-sm font-medium">
                                            {document.name}
                                        </div>
                                        <div className="truncate text-xs text-muted-foreground">
                                            {document.original_filename} •{' '}
                                            {formatFileSize(document.size)}
                                        </div>
                                    </div>

                                    <a
                                        href={`/companies/${company.id}/documents/${document.id}/download`}
                                        className="grid h-9 w-9 shrink-0 place-items-center rounded-xl text-muted-foreground transition hover:bg-background hover:text-foreground"
                                        aria-label={`Download ${document.name}`}
                                    >
                                        <Download className="h-4 w-4" />
                                    </a>

                                    <button
                                        type="button"
                                        onClick={() => remove(document)}
                                        className="grid h-9 w-9 shrink-0 place-items-center rounded-xl text-muted-foreground transition hover:bg-background hover:text-destructive"
                                        aria-label={`Delete ${document.name}`}
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </button>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <div className="mt-5 flex flex-col items-center rounded-2xl bg-muted/40 px-4 py-8 text-center dark:bg-white/5">
                            <span className="grid h-12 w-12 place-items-center rounded-2xl bg-background text-muted-foreground">
                                <FileText className="h-5 w-5" />
                            </span>
                            <div className="mt-3 text-sm font-semibold">
                                No documents yet
                            </div>
                            <p className="mt-1 max-w-xs text-xs text-muted-foreground">
                                Upload certificates, licences or anything else
                                worth keeping alongside this company.
                            </p>
                        </div>
                    )}

                    <Separator className="my-5" />

                    {/* Upload */}
                    <form onSubmit={submit} className="space-y-3">
                        <div className="space-y-1.5">
                            <Label htmlFor={`document_name_${company.id}`}>
                                Document name
                                <span
                                    className="ml-0.5 text-destructive"
                                    aria-hidden
                                >
                                    *
                                </span>
                            </Label>
                            <input
                                id={`document_name_${company.id}`}
                                className={fieldInputClass}
                                value={form.data.name}
                                onChange={(e) =>
                                    form.setData('name', e.target.value)
                                }
                            />
                            {form.errors.name ? (
                                <p className="text-xs text-destructive">
                                    {form.errors.name}
                                </p>
                            ) : null}
                        </div>

                        <DocumentDropzone
                            value={form.data.file}
                            onChange={(f) => form.setData('file', f)}
                        />

                        {form.errors.file ? (
                            <p className="text-xs text-destructive">
                                {form.errors.file}
                            </p>
                        ) : null}

                        <div className="flex items-center justify-end gap-2 pt-1">
                            <button
                                type="button"
                                onClick={() => setOpen(false)}
                                className="h-10 rounded-xl px-4 text-sm text-muted-foreground transition hover:text-foreground"
                            >
                                Close
                            </button>

                            <button
                                type="submit"
                                disabled={
                                    form.processing ||
                                    !form.data.name.trim() ||
                                    !form.data.file
                                }
                                className="flex h-10 items-center gap-2 rounded-xl bg-primary px-5 text-sm font-medium text-primary-foreground transition hover:bg-primary/90 disabled:opacity-50"
                            >
                                {form.processing ? (
                                    <NiloSpinner size={16} />
                                ) : null}
                                Upload document
                            </button>
                        </div>
                    </form>
                </div>
            </DialogContent>
        </Dialog>
    );
}

/** PDF/JPG picker for compliance uploads. */
function DocumentDropzone({
    value,
    onChange,
}: {
    value: File | null;
    onChange: (file: File | null) => void;
}) {
    const inputRef = React.useRef<HTMLInputElement | null>(null);
    const [isOver, setIsOver] = React.useState(false);

    const tooLarge = value ? value.size > MAX_DOCUMENT_BYTES : false;

    return (
        <div
            onDragOver={(e) => {
                e.preventDefault();
                setIsOver(true);
            }}
            onDragLeave={() => setIsOver(false)}
            onDrop={(e) => {
                e.preventDefault();
                setIsOver(false);
                onChange(e.dataTransfer.files?.[0] ?? null);
            }}
            className={cn(
                'flex w-full items-center gap-3 rounded-2xl border border-dashed p-3 transition',
                isOver
                    ? 'border-primary bg-primary/5'
                    : 'border-border bg-muted/30',
                tooLarge && 'border-destructive',
            )}
        >
            <button
                type="button"
                onClick={() => inputRef.current?.click()}
                className="flex min-w-0 flex-1 items-center gap-3 text-left"
            >
                <span className="grid h-12 w-12 shrink-0 place-items-center rounded-xl border border-border bg-background text-muted-foreground">
                    <UploadCloud className="h-5 w-5" />
                </span>

                <span className="min-w-0">
                    <span className="block truncate text-sm font-medium">
                        {value ? value.name : 'Choose a file'}
                    </span>
                    <span
                        className={cn(
                            'block text-xs',
                            tooLarge
                                ? 'text-destructive'
                                : 'text-muted-foreground',
                        )}
                    >
                        {isOver
                            ? 'Drop to attach'
                            : tooLarge
                              ? `${formatFileSize(value!.size)} — the limit is 5MB`
                              : value
                                ? formatFileSize(value.size)
                                : 'PDF or JPG • up to 5MB'}
                    </span>
                </span>
            </button>

            <button
                type="button"
                onClick={() =>
                    value ? onChange(null) : inputRef.current?.click()
                }
                className="h-9 shrink-0 rounded-xl border border-border bg-background px-3 text-sm text-muted-foreground transition hover:text-foreground"
            >
                {value ? 'Remove' : 'Browse'}
            </button>

            <input
                ref={inputRef}
                type="file"
                className="hidden"
                accept=".pdf,.jpg,.jpeg"
                onChange={(e) => onChange(e.target.files?.[0] ?? null)}
            />
        </div>
    );
}

/* --------------------------- Empty states --------------------------- */

/**
 * Two tilted company cards on a soft shadow. Drawn with theme tokens so it
 * inverts cleanly rather than sitting as a bright block in dark mode.
 */
function EmptyCompaniesArt() {
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

            <g transform="rotate(-9 92 74)">
                <rect
                    x="46"
                    y="36"
                    width="92"
                    height="76"
                    rx="14"
                    className="fill-muted dark:fill-white/5"
                />
            </g>

            <rect
                x="82"
                y="30"
                width="98"
                height="84"
                rx="16"
                className="fill-card stroke-black/[0.06] dark:stroke-white/10"
                strokeWidth="1.5"
            />

            <rect
                x="96"
                y="44"
                width="30"
                height="30"
                rx="9"
                className="fill-brand-100 dark:fill-brand-500/25"
            />
            <path
                d="M105 66v-12a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v12"
                className="stroke-brand-600 dark:stroke-brand-300"
                strokeWidth="2"
                strokeLinecap="round"
            />
            <path
                d="M109 58h4M109 62h4"
                className="stroke-brand-600 dark:stroke-brand-300"
                strokeWidth="2"
                strokeLinecap="round"
            />

            <rect
                x="96"
                y="84"
                width="70"
                height="7"
                rx="3.5"
                className="fill-muted dark:fill-white/10"
            />
            <rect
                x="96"
                y="96"
                width="44"
                height="7"
                rx="3.5"
                className="fill-muted dark:fill-white/10"
            />

            <circle
                cx="62"
                cy="24"
                r="4"
                className="fill-brand-200 dark:fill-brand-500/40"
            />
            <circle
                cx="192"
                cy="60"
                r="5"
                className="fill-brand-100 dark:fill-brand-500/25"
            />
            <circle
                cx="52"
                cy="98"
                r="3"
                className="fill-brand-200 dark:fill-brand-500/40"
            />
        </svg>
    );
}

function EmptyCompanies() {
    return (
        <div className="flex flex-col items-center px-4 py-10 text-center">
            <EmptyCompaniesArt />

            <div className="mt-4 text-sm font-semibold">No companies yet</div>
            <p className="mt-1 max-w-sm text-sm text-muted-foreground">
                Create your first company to start issuing invoices and tracking
                revenue.
            </p>

            <div className="mt-5">
                <AddCompanyModal />
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
                Nothing matched “{query.trim()}”. Try a different name, email or
                TPIN.
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

/* --------------------------- Upload Field --------------------------- */

function LogoUpload({
    label = 'Logo',
    value,
    existingUrl,
    onChange,
    onClear,
    hint = 'PNG/JPG/WebP/SVG • Recommended: square image',
}: {
    label?: string;
    value: File | null;
    existingUrl?: string | null;
    onChange: (f: File | null) => void;
    onClear?: () => void;
    hint?: string;
}) {
    const inputRef = React.useRef<HTMLInputElement | null>(null);
    const [preview, setPreview] = React.useState<string | null>(null);
    const [isOver, setIsOver] = React.useState(false);

    React.useEffect(() => {
        if (!value) {
            setPreview(null);
            return;
        }
        const url = URL.createObjectURL(value);
        setPreview(url);
        return () => URL.revokeObjectURL(url);
    }, [value]);

    const shown = preview || existingUrl || null;

    const pick = () => inputRef.current?.click();

    const onDrop = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsOver(false);
        const f = e.dataTransfer.files?.[0];
        if (f) onChange(f);
    };

    return (
        <div className="space-y-2">
            <Label className="flex items-center gap-2">
                <ImageIcon className="h-4 w-4" />
                {label}
            </Label>

            <button
                type="button"
                className={cn(
                    'group flex w-full items-center gap-4 rounded-2xl border bg-muted/20 p-4 text-left transition',
                    'hover:bg-muted/30',
                    isOver && 'ring-2 ring-primary/30',
                )}
                onClick={pick}
                onDragEnter={(e) => {
                    e.preventDefault();
                    setIsOver(true);
                }}
                onDragOver={(e) => {
                    e.preventDefault();
                    setIsOver(true);
                }}
                onDragLeave={(e) => {
                    e.preventDefault();
                    setIsOver(false);
                }}
                onDrop={onDrop}
            >
                <div className="grid h-14 w-14 place-items-center overflow-hidden rounded-2xl border bg-background">
                    {shown ? (
                        <img
                            src={shown}
                            alt="logo preview"
                            className="h-full w-full object-cover"
                        />
                    ) : (
                        <UploadCloud className="h-6 w-6 text-foreground/70" />
                    )}
                </div>

                <div className="min-w-0 flex-1">
                    <div className="text-sm font-semibold">
                        {value
                            ? value.name
                            : shown
                              ? 'Current logo (click to replace)'
                              : 'Upload company logo'}
                    </div>
                    <div className="mt-1 text-xs text-muted-foreground">
                        {isOver ? 'Drop image to upload' : hint}
                    </div>
                </div>

                <div className="flex items-center gap-2">
                    {value || shown ? (
                        <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            className="h-9 rounded-xl"
                            onClick={(e) => {
                                e.stopPropagation();
                                if (onClear) {
                                    onClear();

                                    return;
                                }

                                onChange(null);
                            }}
                        >
                            Remove
                        </Button>
                    ) : (
                        <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            className="h-9 rounded-xl"
                            onClick={(e) => {
                                e.stopPropagation();
                                pick();
                            }}
                        >
                            Browse
                        </Button>
                    )}
                </div>
            </button>

            <input
                ref={inputRef}
                type="file"
                className="hidden"
                accept=".jpg,.jpeg,.png,.webp,.svg"
                onChange={(e) => onChange(e.target.files?.[0] ?? null)}
            />
        </div>
    );
}

/* --------------------------- Add Company --------------------------- */

/* --------------------------- Company type field --------------------------- */

function CompanyTypeField({
    value,
    onChange,
    error,
    idPrefix,
}: {
    value: CompanyType;
    onChange: (value: CompanyType) => void;
    error?: string;
    idPrefix: string;
}) {
    return (
        <div className="space-y-2">
            <Label>Business type *</Label>

            <div
                role="radiogroup"
                aria-label="Business type"
                className="grid grid-cols-1 gap-3 sm:grid-cols-2"
            >
                {COMPANY_TYPES.map((option) => {
                    const Icon = option.icon;
                    const selected = value === option.value;

                    return (
                        <button
                            key={option.value}
                            id={`${idPrefix}_type_${option.value}`}
                            type="button"
                            role="radio"
                            aria-checked={selected}
                            onClick={() => onChange(option.value)}
                            className={cn(
                                'flex flex-col gap-1.5 rounded-xl border p-3 text-left transition',
                                'hover:border-foreground/25 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                selected
                                    ? 'border-foreground/40 bg-muted/60'
                                    : 'border-border bg-transparent',
                            )}
                        >
                            <span className="flex items-center gap-2">
                                <Icon className="h-4 w-4 text-foreground/70" />
                                <span className="text-sm font-medium">
                                    {option.label}
                                </span>
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {option.description}
                            </span>
                        </button>
                    );
                })}
            </div>

            {error ? <p className="text-sm text-destructive">{error}</p> : null}
        </div>
    );
}

/* --------------------------- Company currency field --------------------------- */

function CompanyCurrencyField({
    value,
    onChange,
    error,
    id,
    hint,
}: {
    value: string;
    onChange: (value: string) => void;
    error?: string;
    id: string;
    hint: string;
}) {
    const currencies = useAvailableCurrencies();

    return (
        <div className="space-y-2">
            <Label htmlFor={id}>Billing currency *</Label>

            <select
                id={id}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                required
                className={cn(
                    'h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs',
                    'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                )}
            >
                {currencies.length === 0 ? (
                    <option value={FALLBACK_CURRENCY_CODE}>
                        {FALLBACK_CURRENCY_CODE}
                    </option>
                ) : null}

                {currencies.map((currency) => (
                    <option key={currency.code} value={currency.code}>
                        {currency.code}
                        {currency.symbol ? ` (${currency.symbol})` : ''}
                    </option>
                ))}
            </select>

            <p className="text-xs text-muted-foreground">{hint}</p>

            {error ? <p className="text-sm text-destructive">{error}</p> : null}
        </div>
    );
}

type AddCompanyModalProps = {
    triggerVariant?: 'default' | 'secondary' | 'outline' | 'ghost' | 'link';
};

function AddCompanyModal({ triggerVariant = 'default' }: AddCompanyModalProps) {
    const [open, setOpen] = React.useState(false);

    const currencies = useAvailableCurrencies();
    const activeCurrency = useActiveCurrency();

    /** The currency the dashboard reports in — everything else converts into it. */
    const displayCode = activeCurrency.code ?? FALLBACK_CURRENCY_CODE;

    const currencyOptions: CurrencyLike[] =
        currencies.length > 0 ? currencies : [{ code: FALLBACK_CURRENCY_CODE }];

    const form = useForm<{
        name: string;
        type: CompanyType;
        currency_code: string;
        email?: string;
        phone?: string;
        tpin?: string;
        address?: string;
        primary_color?: string;
        logo: File | null;
    }>({
        name: '',
        type: DEFAULT_COMPANY_TYPE,
        currency_code:
            activeCurrency.code ??
            currencies[0]?.code ??
            FALLBACK_CURRENCY_CODE,
        email: '',
        phone: '',
        tpin: '',
        address: '',
        primary_color: '',
        logo: null,
    });

    const closeAndReset = () => {
        setOpen(false);
        form.reset();
        form.clearErrors();
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        const toastId = toast.loading('Creating company...');

        form.post('/companies', {
            preserveScroll: true,
            forceFormData: true, // ✅ for logo upload
            headers: getCsrfHeaders(),
            /**
             * A plan refusal redirects back without validation errors, which
             * Inertia reports here rather than in `onError`. Claiming success
             * on that would close the dialog over a company that was never
             * written, so the returned page decides which it was.
             */
            onSuccess: (page) => {
                const notice = (page.props as { limitNotice?: LimitNotice })
                    .limitNotice;

                if (notice) {
                    toast.dismiss(toastId);

                    return;
                }

                toast.success('Company created successfully.', { id: toastId });
                closeAndReset();
            },
            onError: (errors) => {
                toast.error(
                    firstCompanyError(errors) ?? 'Failed to create company.',
                    {
                        id: toastId,
                    },
                );
            },
        });
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(v) => (v ? setOpen(true) : closeAndReset())}
        >
            <DialogTrigger asChild>
                <Button
                    variant={triggerVariant}
                    className="h-9 gap-2 rounded-xl transition-transform hover:scale-[1.02] active:scale-[0.98]"
                >
                    <Plus className="h-4 w-4" />
                    Add company
                </Button>
            </DialogTrigger>

            <DialogContent
                aria-describedby={undefined}
                className="max-h-[88vh] w-fit max-w-[min(48rem,calc(100vw_-_2rem))] min-w-[min(32rem,calc(100vw_-_2rem))] gap-0 overflow-y-auto rounded-2xl p-0 sm:max-w-[min(48rem,calc(100vw_-_2rem))]"
            >
                <form onSubmit={submit} className="min-w-0 p-6">
                    <DialogTitle className="text-base font-semibold">
                        New company
                    </DialogTitle>

                    <FormField label="Logo" className="mt-5">
                        <LogoDropzone
                            value={form.data.logo}
                            onChange={(f) => form.setData('logo', f)}
                        />
                    </FormField>

                    <FormField
                        label="Company name"
                        htmlFor="create_company_name"
                        required
                        className="mt-4"
                    >
                        <input
                            id="create_company_name"
                            className={fieldInputClass}
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            autoFocus
                            required
                        />
                    </FormField>

                    {form.errors.name || form.errors.logo ? (
                        <p className="mt-2 text-xs text-destructive">
                            {form.errors.name ?? form.errors.logo}
                        </p>
                    ) : null}

                    <FormField label="Business type" required className="mt-4">
                        <div
                            role="radiogroup"
                            aria-label="Business type"
                            className="grid grid-cols-2 gap-2"
                        >
                            {COMPANY_TYPES.map((option) => {
                                const Icon = option.icon;
                                const selected =
                                    form.data.type === option.value;

                                return (
                                    <button
                                        key={option.value}
                                        type="button"
                                        role="radio"
                                        aria-checked={selected}
                                        onClick={() =>
                                            form.setData('type', option.value)
                                        }
                                        className={cn(
                                            'flex h-10 items-center justify-center gap-2 rounded-xl border text-sm transition',
                                            selected
                                                ? 'border-primary bg-primary/10 font-medium text-primary'
                                                : 'border-border text-muted-foreground hover:bg-muted/60',
                                        )}
                                    >
                                        <Icon className="h-4 w-4" />
                                        {option.value === 'services'
                                            ? 'Services'
                                            : 'Products'}
                                    </button>
                                );
                            })}
                        </div>
                    </FormField>

                    <FormField
                        label="Billing currency"
                        required
                        className="mt-4"
                    >
                        <div
                            role="radiogroup"
                            aria-label="Billing currency"
                            className="flex max-w-[28rem] flex-wrap gap-2"
                        >
                            {currencyOptions.map((currency) => {
                                const selected =
                                    form.data.currency_code === currency.code;

                                return (
                                    <button
                                        key={currency.code}
                                        type="button"
                                        role="radio"
                                        aria-checked={selected}
                                        onClick={() =>
                                            form.setData(
                                                'currency_code',
                                                currency.code,
                                            )
                                        }
                                        className={cn(
                                            'h-10 rounded-xl border px-4 text-sm transition',
                                            selected
                                                ? 'border-primary bg-primary/10 font-medium text-primary'
                                                : 'border-border text-muted-foreground hover:bg-muted/60',
                                        )}
                                    >
                                        {currency.code}
                                        {currency.symbol
                                            ? ` ${currency.symbol}`
                                            : ''}
                                    </button>
                                );
                            })}
                        </div>

                        {form.data.currency_code === displayCode ? null : (
                            <p className="text-xs text-muted-foreground">
                                {`Invoices bill in ${form.data.currency_code}; dashboard totals convert to ${displayCode} at the latest rate.`}
                            </p>
                        )}
                    </FormField>

                    <div className="mt-4 grid grid-cols-2 gap-3">
                        <FormField label="Email" htmlFor="create_company_email">
                            <input
                                id="create_company_email"
                                type="email"
                                className={fieldInputClass}
                                value={form.data.email ?? ''}
                                onChange={(e) =>
                                    form.setData('email', e.target.value)
                                }
                            />
                        </FormField>

                        <FormField label="Phone" htmlFor="create_company_phone">
                            <input
                                id="create_company_phone"
                                className={fieldInputClass}
                                value={form.data.phone ?? ''}
                                onChange={(e) =>
                                    form.setData('phone', e.target.value)
                                }
                            />
                        </FormField>

                        <FormField label="TPIN" htmlFor="create_company_tpin">
                            <input
                                id="create_company_tpin"
                                className={fieldInputClass}
                                value={form.data.tpin ?? ''}
                                onChange={(e) =>
                                    form.setData('tpin', e.target.value)
                                }
                            />
                        </FormField>

                        <FormField
                            label="Brand color"
                            htmlFor="create_company_color"
                        >
                            <input
                                id="create_company_color"
                                className={fieldInputClass}
                                value={form.data.primary_color ?? ''}
                                onChange={(e) =>
                                    form.setData(
                                        'primary_color',
                                        e.target.value,
                                    )
                                }
                            />
                        </FormField>

                        <FormField
                            label="Address"
                            htmlFor="create_company_address"
                            className="col-span-2"
                        >
                            <input
                                id="create_company_address"
                                className={fieldInputClass}
                                value={form.data.address ?? ''}
                                onChange={(e) =>
                                    form.setData('address', e.target.value)
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
                            Create company
                        </button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

/** Full-width drag-and-drop logo field used by the create dialog. */
function LogoDropzone({
    value,
    onChange,
}: {
    value: File | null;
    onChange: (file: File | null) => void;
}) {
    const inputRef = React.useRef<HTMLInputElement | null>(null);
    const [preview, setPreview] = React.useState<string | null>(null);
    const [isOver, setIsOver] = React.useState(false);

    React.useEffect(() => {
        if (!value) {
            setPreview(null);
            return;
        }

        const url = URL.createObjectURL(value);
        setPreview(url);

        return () => URL.revokeObjectURL(url);
    }, [value]);

    return (
        <div
            onDragOver={(e) => {
                e.preventDefault();
                setIsOver(true);
            }}
            onDragLeave={() => setIsOver(false)}
            onDrop={(e) => {
                e.preventDefault();
                setIsOver(false);
                onChange(e.dataTransfer.files?.[0] ?? null);
            }}
            className={cn(
                'flex w-full items-center gap-3 rounded-2xl border border-dashed p-3 transition',
                isOver
                    ? 'border-primary bg-primary/5'
                    : 'border-border bg-muted/30',
            )}
        >
            <button
                type="button"
                onClick={() => inputRef.current?.click()}
                className="flex min-w-0 flex-1 items-center gap-3 text-left"
            >
                <span className="grid h-12 w-12 shrink-0 place-items-center overflow-hidden rounded-xl border border-border bg-background text-muted-foreground">
                    {preview ? (
                        <img
                            src={preview}
                            alt=""
                            className="h-full w-full object-cover"
                        />
                    ) : (
                        <UploadCloud className="h-5 w-5" />
                    )}
                </span>

                <span className="min-w-0">
                    <span className="block truncate text-sm font-medium">
                        {value ? value.name : 'Upload a logo'}
                    </span>
                    <span className="block text-xs text-muted-foreground">
                        {isOver ? 'Drop to upload' : 'PNG, JPG or SVG'}
                    </span>
                </span>
            </button>

            <button
                type="button"
                onClick={() =>
                    value ? onChange(null) : inputRef.current?.click()
                }
                className="h-9 shrink-0 rounded-xl border border-border bg-background px-3 text-sm text-muted-foreground transition hover:text-foreground"
            >
                {value ? 'Remove' : 'Browse'}
            </button>

            <input
                ref={inputRef}
                type="file"
                className="hidden"
                accept=".jpg,.jpeg,.png,.webp,.svg"
                onChange={(e) => onChange(e.target.files?.[0] ?? null)}
            />
        </div>
    );
}

/* --------------------------- Edit Company --------------------------- */

function EditCompanyModal({ company }: { company: Company }) {
    const [open, setOpen] = React.useState(false);

    const form = useForm<{
        name: string;
        type: CompanyType;
        currency_code: string;
        email?: string;
        phone?: string;
        tpin?: string;
        address?: string;
        primary_color?: string;
        logo: File | null;
        remove_logo: boolean;
    }>({
        name: '',
        type: DEFAULT_COMPANY_TYPE,
        currency_code: FALLBACK_CURRENCY_CODE,
        email: '',
        phone: '',
        tpin: '',
        address: '',
        primary_color: '',
        logo: null,
        remove_logo: false,
    });

    React.useEffect(() => {
        if (!open) return;

        form.setData({
            name: company.name ?? '',
            type: company.type ?? DEFAULT_COMPANY_TYPE,
            currency_code: company.currency_code ?? FALLBACK_CURRENCY_CODE,
            email: company.email ?? '',
            phone: company.phone ?? '',
            tpin: company.tpin ?? '',
            address: company.address ?? '',
            primary_color: company.primary_color ?? '',
            logo: null,
            remove_logo: false,
        });
        form.clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, company.id]);

    const existingLogoUrl = company.logo_url ?? null;
    const showExistingLogo = !form.data.remove_logo;

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        const toastId = toast.loading('Updating company...');

        router.post(
            `/companies/${company.id}`,
            {
                _method: 'PUT', // ✅ spoof PUT
                name: form.data.name,
                type: form.data.type,
                currency_code: form.data.currency_code,
                email: form.data.email ?? '',
                phone: form.data.phone ?? '',
                tpin: form.data.tpin ?? '',
                address: form.data.address ?? '',
                primary_color: form.data.primary_color ?? '',
                logo: form.data.logo, // ✅ File | null
                remove_logo: form.data.remove_logo ? 1 : 0,
            },
            {
                preserveScroll: true,
                forceFormData: true, // ✅ converts payload to FormData
                headers: getCsrfHeaders(),
                onSuccess: () => {
                    toast.success('Company updated.', { id: toastId });
                    setOpen(false);
                },
                onError: (errors) => {
                    toast.error(
                        firstCompanyError(errors) ??
                            'Failed to update company.',
                        { id: toastId },
                    );
                },
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <PillButton variant="ghost" size="sm">
                    <Pencil className="h-3.5 w-3.5" />
                    Edit
                </PillButton>
            </DialogTrigger>

            <DialogContent className="overflow-hidden rounded-2xl p-0 sm:max-w-lg">
                <div className="px-6 pt-6 pb-5">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <span className="grid h-9 w-9 place-items-center overflow-hidden rounded-xl bg-muted">
                                {existingLogoUrl ? (
                                    <img
                                        src={existingLogoUrl}
                                        alt="logo"
                                        className="h-full w-full object-cover"
                                    />
                                ) : (
                                    <Building2 className="h-5 w-5 text-foreground/80" />
                                )}
                            </span>
                            Edit company
                        </DialogTitle>
                        <DialogDescription>
                            Update details and logo. Logo is stored in{' '}
                            <span className="font-medium">logo_path</span>.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={submit} className="mt-6 space-y-4">
                        <LogoUpload
                            label="Company logo"
                            value={form.data.logo}
                            existingUrl={
                                showExistingLogo ? existingLogoUrl : null
                            }
                            onChange={(f) => {
                                form.setData('logo', f);
                                if (f) form.setData('remove_logo', false);
                            }}
                            onClear={() => {
                                if (form.data.logo) {
                                    form.setData('logo', null);

                                    return;
                                }

                                if (existingLogoUrl) {
                                    form.setData('remove_logo', true);
                                }
                            }}
                        />

                        {existingLogoUrl ? (
                            <label className="flex items-center gap-2 text-xs text-muted-foreground">
                                <input
                                    type="checkbox"
                                    checked={form.data.remove_logo}
                                    onChange={(e) =>
                                        form.setData(
                                            'remove_logo',
                                            e.target.checked,
                                        )
                                    }
                                />
                                <Trash2 className="h-3.5 w-3.5" />
                                Remove current logo
                            </label>
                        ) : null}

                        {form.errors.logo ? (
                            <p className="text-sm text-destructive">
                                {form.errors.logo}
                            </p>
                        ) : null}

                        <div className="space-y-2">
                            <Label>Company name *</Label>
                            <Input
                                value={form.data.name}
                                onChange={(e) =>
                                    form.setData('name', e.target.value)
                                }
                                required
                            />
                            {form.errors.name ? (
                                <p className="text-sm text-destructive">
                                    {form.errors.name}
                                </p>
                            ) : null}
                        </div>

                        <CompanyTypeField
                            idPrefix={`edit_company_${company.id}`}
                            value={form.data.type}
                            onChange={(v) => form.setData('type', v)}
                            error={form.errors.type}
                        />

                        <CompanyCurrencyField
                            id={`edit_company_${company.id}_currency`}
                            value={form.data.currency_code}
                            onChange={(v) => form.setData('currency_code', v)}
                            error={form.errors.currency_code}
                            hint="Changing this only affects new invoices and quotations. Existing documents keep the currency they were issued in."
                        />

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label>Email</Label>
                                <Input
                                    type="email"
                                    value={form.data.email ?? ''}
                                    onChange={(e) =>
                                        form.setData('email', e.target.value)
                                    }
                                />
                            </div>

                            <div className="space-y-2">
                                <Label>Phone</Label>
                                <Input
                                    value={form.data.phone ?? ''}
                                    onChange={(e) =>
                                        form.setData('phone', e.target.value)
                                    }
                                />
                            </div>

                            <div className="space-y-2">
                                <Label>TPIN</Label>
                                <Input
                                    value={form.data.tpin ?? ''}
                                    onChange={(e) =>
                                        form.setData('tpin', e.target.value)
                                    }
                                />
                            </div>

                            <div className="space-y-2">
                                <Label>Primary color</Label>
                                <Input
                                    value={form.data.primary_color ?? ''}
                                    onChange={(e) =>
                                        form.setData(
                                            'primary_color',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>

                            <div className="space-y-2 sm:col-span-2">
                                <Label>Address</Label>
                                <Input
                                    value={form.data.address ?? ''}
                                    onChange={(e) =>
                                        form.setData('address', e.target.value)
                                    }
                                />
                            </div>
                        </div>

                        <Separator className="my-4" />

                        <div className="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-end">
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => setOpen(false)}
                                disabled={form.processing}
                                className="h-9 rounded-xl"
                            >
                                <X className="mr-2 h-4 w-4" />
                                Cancel
                            </Button>

                            <Button
                                type="submit"
                                disabled={
                                    form.processing || !form.data.name.trim()
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
                                    <>
                                        <CheckCircle2 className="mr-2 h-4 w-4" />
                                        Save changes
                                    </>
                                )}
                            </Button>
                        </div>
                    </form>
                </div>
            </DialogContent>
        </Dialog>
    );
}
