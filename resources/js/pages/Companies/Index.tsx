// resources/js/Pages/Companies/Index.tsx
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import {
    Building2,
    CheckCircle2,
    Image as ImageIcon,
    Loader2,
    Pencil,
    Plus,
    Trash2,
    UploadCloud,
    X,
} from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';
import type { PageProps } from '../../types';

// shadcn/ui
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types/index.d';

interface Company {
    id: number;
    name: string;

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
}

interface CompaniesIndexProps extends PageProps {
    companies: Company[];
    active_company_id: number | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Companies', href: '/companies' },
];

const getCsrfHeaders = (): Record<string, string> => {
    const token = (
        document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement
    )?.content;

    if (! token) {
        return {};
    }

    return {
        'X-CSRF-TOKEN': token,
    };
};

export default function CompaniesIndex({
    companies,
    active_company_id,
}: CompaniesIndexProps) {
    const activeCompany = React.useMemo(
        () => companies.find((c) => c.id === active_company_id) || null,
        [companies, active_company_id],
    );

    const money = (n: any) => {
        const v = Number(n ?? 0);
        return `ZMW ${Number.isFinite(v) ? v.toFixed(2) : '0.00'}`;
    };

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

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Companies" />

            <div className="mx-auto w-full px-4 py-10 sm:px-6">
                {/* Header */}
                <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="min-w-0">
                        <motion.h1
                            initial={{ opacity: 0, y: 8 }}
                            animate={{ opacity: 1, y: 0 }}
                            transition={{ duration: 0.35, ease: 'easeOut' }}
                            className="text-2xl font-semibold tracking-tight sm:text-3xl"
                        >
                            Companies
                        </motion.h1>

                        <motion.p
                            initial={{ opacity: 0, y: 8 }}
                            animate={{ opacity: 1, y: 0 }}
                            transition={{
                                duration: 0.35,
                                delay: 0.05,
                                ease: 'easeOut',
                            }}
                            className="mt-1 text-sm text-muted-foreground"
                        >
                            Manage businesses on your account. Upload a logo,
                            brand color, and switch active company anytime.
                        </motion.p>

                        {activeCompany ? (
                            <div className="mt-3 flex items-center gap-2">
                                <Badge variant="secondary" className="gap-1">
                                    <CheckCircle2 className="h-3.5 w-3.5" />
                                    Active:{' '}
                                    <span className="font-medium">
                                        {activeCompany.name}
                                    </span>
                                </Badge>
                            </div>
                        ) : (
                            <div className="mt-3 text-sm text-muted-foreground">
                                No active company selected.
                            </div>
                        )}
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            asChild
                            variant="outline"
                            className="h-9 rounded-xl"
                        >
                            <Link href="/dashboard">Back to dashboard</Link>
                        </Button>
                        <AddCompanyModal />
                    </div>
                </div>

                {/* ✅ Summary cards (ACTIVE company) */}
                <div className="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
                    <SummaryStat
                        title={
                            activeCompany
                                ? `Paid revenue • ${activeCompany.name}`
                                : 'Paid revenue'
                        }
                        value={money(activeTotals.paid)}
                        tone="emerald"
                        sub={
                            activeCompany
                                ? `All companies: ${money(allTotals.paid)}`
                                : undefined
                        }
                    />
                    <SummaryStat
                        title={
                            activeCompany
                                ? `Pending revenue • ${activeCompany.name}`
                                : 'Pending revenue'
                        }
                        value={money(activeTotals.pending)}
                        tone="amber"
                        sub={
                            activeCompany
                                ? `All companies: ${money(allTotals.pending)}`
                                : undefined
                        }
                    />
                    <SummaryStat
                        title={
                            activeCompany
                                ? `Clients • ${activeCompany.name}`
                                : 'Clients'
                        }
                        value={`${activeTotals.clients}`}
                        tone="slate"
                        sub={
                            activeCompany
                                ? `Invoices: ${activeTotals.invoices} • All companies: ${allTotals.clients} clients`
                                : `All companies: ${allTotals.clients} clients • ${allTotals.invoices} invoices`
                        }
                    />
                </div>

                <Separator className="mb-6" />

                {/* Table */}
                <Card className="overflow-hidden rounded-2xl border bg-background shadow-sm">
                    <div className="flex items-center justify-between gap-3 px-5 py-4">
                        <div className="flex items-center gap-2">
                            <div className="grid h-9 w-9 place-items-center rounded-xl bg-muted">
                                <Building2 className="h-5 w-5 text-foreground/80" />
                            </div>
                            <div className="min-w-0">
                                <div className="text-sm font-semibold">
                                    Your companies
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    {companies.length} total
                                </div>
                            </div>
                        </div>

                        <Button
                            asChild
                            variant="secondary"
                            className="hidden h-9 rounded-xl sm:inline-flex"
                        >
                            <Link href="/companies">Refresh</Link>
                        </Button>
                    </div>

                    <div className="w-full overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="min-w-[340px]">
                                        Company
                                    </TableHead>
                                    <TableHead className="min-w-[130px]">
                                        Clients
                                    </TableHead>
                                    <TableHead className="min-w-[170px]">
                                        Paid revenue
                                    </TableHead>
                                    <TableHead className="min-w-[170px]">
                                        Pending revenue
                                    </TableHead>
                                    <TableHead className="min-w-[120px]">
                                        Status
                                    </TableHead>
                                    <TableHead className="min-w-[280px] text-right">
                                        Actions
                                    </TableHead>
                                </TableRow>
                            </TableHeader>

                            <TableBody>
                                {companies.length === 0 ? (
                                    <TableRow>
                                        <TableCell
                                            colSpan={6}
                                            className="py-14 text-center"
                                        >
                                            <div className="mx-auto max-w-sm">
                                                <div className="mx-auto mb-3 grid h-12 w-12 place-items-center rounded-2xl bg-muted">
                                                    <Building2 className="h-6 w-6 text-foreground/70" />
                                                </div>
                                                <div className="text-sm font-medium">
                                                    No companies yet
                                                </div>
                                                <div className="mt-1 text-sm text-muted-foreground">
                                                    Create your first company to
                                                    start issuing invoices and
                                                    tracking revenue.
                                                </div>
                                                <div className="mt-4">
                                                    <AddCompanyModal triggerVariant="outline" />
                                                </div>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    companies.map((company, index) => {
                                        const isActive =
                                            company.id === active_company_id;
                                        const logoUrl =
                                            company.logo_url ?? null;

                                        return (
                                            <motion.tr
                                                key={company.id}
                                                initial={{ opacity: 0, y: 10 }}
                                                animate={{ opacity: 1, y: 0 }}
                                                transition={{
                                                    duration: 0.25,
                                                    delay: Math.min(
                                                        0.03 * index,
                                                        0.18,
                                                    ),
                                                }}
                                                className={cn(
                                                    'group',
                                                    isActive && 'bg-muted/60',
                                                )}
                                            >
                                                {/* Company */}
                                                <TableCell className="py-4">
                                                    <div className="flex items-center gap-3">
                                                        <div
                                                            className={cn(
                                                                'grid h-11 w-11 place-items-center overflow-hidden rounded-2xl border bg-background',
                                                                isActive &&
                                                                    'border-primary/30 bg-primary/5',
                                                            )}
                                                        >
                                                            {logoUrl ? (
                                                                <img
                                                                    src={
                                                                        logoUrl
                                                                    }
                                                                    className="h-full w-full object-cover"
                                                                    alt={`${company.name} logo`}
                                                                />
                                                            ) : (
                                                                <Building2
                                                                    className={cn(
                                                                        'h-5 w-5 text-foreground/70',
                                                                        isActive &&
                                                                            'text-primary',
                                                                    )}
                                                                />
                                                            )}
                                                        </div>

                                                        <div className="min-w-0">
                                                            <div className="truncate text-sm font-semibold">
                                                                {company.name}
                                                            </div>
                                                            <div className="mt-1 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                                                <span>
                                                                    ID:{' '}
                                                                    {company.id}
                                                                </span>
                                                                {company.primary_color ? (
                                                                    <span className="inline-flex items-center gap-1 rounded-full border bg-background px-2 py-0.5">
                                                                        <span
                                                                            className="h-2.5 w-2.5 rounded-full"
                                                                            style={{
                                                                                backgroundColor:
                                                                                    company.primary_color ??
                                                                                    undefined,
                                                                            }}
                                                                        />
                                                                        {
                                                                            company.primary_color
                                                                        }
                                                                    </span>
                                                                ) : null}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </TableCell>

                                                {/* Clients */}
                                                <TableCell className="py-4">
                                                    <div className="text-sm font-semibold">
                                                        {company.clients_count ??
                                                            0}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {company.total_invoices ??
                                                            0}{' '}
                                                        invoices
                                                    </div>
                                                </TableCell>

                                                {/* Paid */}
                                                <TableCell className="py-4">
                                                    <div className="text-sm font-semibold text-emerald-700">
                                                        {money(
                                                            company.paid_revenue,
                                                        )}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        Collected
                                                    </div>
                                                </TableCell>

                                                {/* Pending */}
                                                <TableCell className="py-4">
                                                    <div className="text-sm font-semibold text-amber-700">
                                                        {money(
                                                            company.pending_revenue,
                                                        )}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        Outstanding
                                                    </div>
                                                </TableCell>

                                                {/* Status */}
                                                <TableCell className="py-4">
                                                    {isActive ? (
                                                        <Badge
                                                            className="gap-1"
                                                            variant="default"
                                                        >
                                                            <CheckCircle2 className="h-3.5 w-3.5" />
                                                            Active
                                                        </Badge>
                                                    ) : (
                                                        <Badge variant="secondary">
                                                            Inactive
                                                        </Badge>
                                                    )}
                                                </TableCell>

                                                {/* Actions */}
                                                <TableCell className="py-4 text-right">
                                                    <div className="flex justify-end gap-2">
                                                        <EditCompanyModal
                                                            company={company}
                                                        />

                                                        {isActive ? (
                                                            <Button
                                                                variant="secondary"
                                                                size="sm"
                                                                disabled
                                                                className="h-9 rounded-xl"
                                                            >
                                                                Current
                                                            </Button>
                                                        ) : (
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                className="h-9 rounded-xl transition-transform hover:scale-[1.02] active:scale-[0.98]"
                                                                onClick={() => {
                                                                    router.post(
                                                                        '/companies/switch',
                                                                        {
                                                                            company_id:
                                                                                company.id,
                                                                        },
                                                                        {
                                                                            preserveScroll: true,
                                                                            onSuccess:
                                                                                () =>
                                                                                    toast.success(
                                                                                        'Company switched.',
                                                                                    ),
                                                                            onError:
                                                                                (
                                                                                    errors,
                                                                                ) => {
                                                                                    const msg =
                                                                                        (
                                                                                            errors as any
                                                                                        )
                                                                                            ?.company_id ||
                                                                                        'Failed to switch company.';
                                                                                    toast.error(
                                                                                        msg,
                                                                                    );
                                                                                },
                                                                        },
                                                                    );
                                                                }}
                                                            >
                                                                Set active
                                                            </Button>
                                                        )}
                                                    </div>
                                                </TableCell>
                                            </motion.tr>
                                        );
                                    })
                                )}
                            </TableBody>
                        </Table>
                    </div>
                </Card>
            </div>
        </AppLayout>
    );
}

/* --------------------------- Summary Card --------------------------- */

function SummaryStat({
    title,
    value,
    tone,
    sub,
}: {
    title: string;
    value: string;
    tone: 'emerald' | 'amber' | 'slate';
    sub?: string;
}) {
    const toneCls =
        tone === 'emerald'
            ? 'bg-emerald-500/10 text-emerald-700'
            : tone === 'amber'
              ? 'bg-amber-500/10 text-amber-700'
              : 'bg-slate-500/10 text-slate-700';

    const Icon =
        tone === 'emerald'
            ? CheckCircle2
            : tone === 'amber'
              ? ImageIcon
              : Building2;

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
                    {sub ? (
                        <div className="mt-1 text-xs text-muted-foreground">
                            {sub}
                        </div>
                    ) : null}
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

type AddCompanyModalProps = {
    triggerVariant?: 'default' | 'secondary' | 'outline' | 'ghost' | 'link';
};

function AddCompanyModal({ triggerVariant = 'default' }: AddCompanyModalProps) {
    const [open, setOpen] = React.useState(false);

    const form = useForm<{
        name: string;
        email?: string;
        phone?: string;
        tpin?: string;
        address?: string;
        primary_color?: string;
        logo: File | null;
    }>({
        name: '',
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
            onSuccess: () => {
                toast.success('Company created successfully.', { id: toastId });
                closeAndReset();
            },
            onError: (errors) => {
                const firstError =
                    (errors as any)?.name ||
                    (errors as any)?.email ||
                    (errors as any)?.phone ||
                    (errors as any)?.tpin ||
                    (errors as any)?.address ||
                    (errors as any)?.primary_color ||
                    (errors as any)?.logo;

                toast.error(firstError ?? 'Failed to create company.', {
                    id: toastId,
                });
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

            <DialogContent className="overflow-hidden rounded-2xl p-0 sm:max-w-lg">
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
                                        <Building2 className="h-5 w-5 text-foreground/80" />
                                    </span>
                                    Create company
                                </DialogTitle>
                                <DialogDescription>
                                    Add a company and upload a logo to make it
                                    feel branded from day one.
                                </DialogDescription>
                            </DialogHeader>

                            <form onSubmit={submit} className="mt-6 space-y-4">
                                <LogoUpload
                                    label="Company logo (optional)"
                                    value={form.data.logo}
                                    onChange={(f) => form.setData('logo', f)}
                                    onClear={() => form.setData('logo', null)}
                                />
                                {form.errors.logo ? (
                                    <p className="text-sm text-destructive">
                                        {form.errors.logo}
                                    </p>
                                ) : null}

                                <div className="space-y-2">
                                    <Label htmlFor="company_name">
                                        Company name *
                                    </Label>
                                    <Input
                                        id="company_name"
                                        placeholder="e.g. Resonant Technologies"
                                        value={form.data.name}
                                        onChange={(e) =>
                                            form.setData('name', e.target.value)
                                        }
                                        autoFocus
                                        required
                                    />
                                    {form.errors.name ? (
                                        <p className="text-sm text-destructive">
                                            {form.errors.name}
                                        </p>
                                    ) : null}
                                </div>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label>Email (optional)</Label>
                                        <Input
                                            type="email"
                                            placeholder="billing@company.com"
                                            value={form.data.email ?? ''}
                                            onChange={(e) =>
                                                form.setData(
                                                    'email',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>

                                    <div className="space-y-2">
                                        <Label>Phone (optional)</Label>
                                        <Input
                                            placeholder="+260..."
                                            value={form.data.phone ?? ''}
                                            onChange={(e) =>
                                                form.setData(
                                                    'phone',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>

                                    <div className="space-y-2">
                                        <Label>TPIN (optional)</Label>
                                        <Input
                                            placeholder="e.g. 100XXXXXXX"
                                            value={form.data.tpin ?? ''}
                                            onChange={(e) =>
                                                form.setData(
                                                    'tpin',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>

                                    <div className="space-y-2">
                                        <Label>Primary color (optional)</Label>
                                        <Input
                                            placeholder="#00417d"
                                            value={
                                                form.data.primary_color ?? ''
                                            }
                                            onChange={(e) =>
                                                form.setData(
                                                    'primary_color',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>

                                    <div className="space-y-2 sm:col-span-2">
                                        <Label>Address (optional)</Label>
                                        <Input
                                            placeholder="Lusaka, Zambia"
                                            value={form.data.address ?? ''}
                                            onChange={(e) =>
                                                form.setData(
                                                    'address',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                </div>

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
                                            !form.data.name.trim()
                                        }
                                        className="h-9 rounded-xl"
                                    >
                                        {form.processing ? (
                                            <>
                                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                Creating...
                                            </>
                                        ) : (
                                            <>
                                                <Plus className="mr-2 h-4 w-4" />
                                                Create company
                                            </>
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

/* --------------------------- Edit Company --------------------------- */

function EditCompanyModal({ company }: { company: Company }) {
    const [open, setOpen] = React.useState(false);

    const form = useForm<{
        name: string;
        email?: string;
        phone?: string;
        tpin?: string;
        address?: string;
        primary_color?: string;
        logo: File | null;
        remove_logo: boolean;
    }>({
        name: '',
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
    const showExistingLogo = ! form.data.remove_logo;

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        const toastId = toast.loading('Updating company...');

        router.post(
            `/companies/${company.id}`,
            {
                _method: 'PUT', // ✅ spoof PUT
                name: form.data.name,
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
                    const first =
                        (errors as any)?.name ||
                        (errors as any)?.email ||
                        (errors as any)?.phone ||
                        (errors as any)?.tpin ||
                        (errors as any)?.address ||
                        (errors as any)?.primary_color ||
                        (errors as any)?.logo ||
                        (errors as any)?.company;

                    toast.error(first ?? 'Failed to update company.', {
                        id: toastId,
                    });
                },
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant="secondary"
                    size="sm"
                    className="h-9 gap-2 rounded-xl"
                >
                    <Pencil className="h-4 w-4" />
                    Edit
                </Button>
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
                                    placeholder="#00417d"
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
                                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
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
