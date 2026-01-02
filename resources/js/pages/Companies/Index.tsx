import AppLayout from '@/layouts/app-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { Building2, CheckCircle2, Loader2, Plus, X } from 'lucide-react';
import React, { useMemo, useState } from 'react';
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

interface Company {
    id: number;
    name: string;
}

interface CompaniesIndexProps extends PageProps {
    companies: Company[];
    active_company_id: number | null;
}

const CompaniesIndex: React.FC<CompaniesIndexProps> = ({
    companies,
    active_company_id,
}) => {
    const activeCompany = useMemo(
        () => companies.find((c) => c.id === active_company_id) || null,
        [companies, active_company_id],
    );

    console.log('active company', activeCompany);

    return (
        <AppLayout>
            <Head title="Companies" />

            <div className="mx-auto w-full px-4 py-10 sm:px-6">
                {/* Header */}
                <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
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
                            Manage the businesses on your Nilo account. Switch
                            active company anytime.
                        </motion.p>
                    </div>

                    <div className="flex items-center gap-2">
                        {activeCompany && (
                            <motion.div
                                initial={{ opacity: 0, scale: 0.98 }}
                                animate={{ opacity: 1, scale: 1 }}
                                transition={{ duration: 0.25 }}
                                className="hidden sm:block"
                            >
                                <Badge variant="secondary" className="gap-1">
                                    <CheckCircle2 className="h-3.5 w-3.5" />
                                    Active:{' '}
                                    <span className="font-medium">
                                        {activeCompany.name}
                                    </span>
                                </Badge>
                            </motion.div>
                        )}
                        <AddCompanyModal />
                    </div>
                </div>

                <Separator className="mb-6" />

                {/* Content */}
                <motion.div
                    initial={{ opacity: 0, y: 10 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ duration: 0.35, ease: 'easeOut' }}
                >
                    <Card className="overflow-hidden rounded-2xl border bg-background shadow-sm">
                        <div className="flex items-center justify-between gap-3 px-5 py-4">
                            <div className="flex items-center gap-2">
                                <div className="grid h-9 w-9 place-items-center rounded-xl bg-muted">
                                    <Building2 className="h-5 w-5 text-foreground/80" />
                                </div>
                                <div>
                                    <div className="text-sm font-semibold">
                                        Your companies
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {companies.length} total
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="w-full overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-[70%]">
                                            Name
                                        </TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">
                                            Action
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>

                                <TableBody>
                                    {companies.length === 0 ? (
                                        <TableRow>
                                            <TableCell
                                                colSpan={3}
                                                className="py-14 text-center"
                                            >
                                                <motion.div
                                                    initial={{ opacity: 0 }}
                                                    animate={{ opacity: 1 }}
                                                    className="mx-auto max-w-sm"
                                                >
                                                    <div className="mx-auto mb-3 grid h-12 w-12 place-items-center rounded-2xl bg-muted">
                                                        <Building2 className="h-6 w-6 text-foreground/70" />
                                                    </div>
                                                    <div className="text-sm font-medium">
                                                        No companies yet
                                                    </div>
                                                    <div className="mt-1 text-sm text-muted-foreground">
                                                        Create your first
                                                        company to start issuing
                                                        invoices, quotations and
                                                        delivery notes.
                                                    </div>
                                                    <div className="mt-4">
                                                        <AddCompanyModal triggerVariant="outline" />
                                                    </div>
                                                </motion.div>
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        companies.map((company, index) => {
                                            const isActive =
                                                company.id ===
                                                active_company_id;

                                            return (
                                                <motion.tr
                                                    key={company.id}
                                                    initial={{
                                                        opacity: 0,
                                                        y: 10,
                                                    }}
                                                    animate={{
                                                        opacity: 1,
                                                        y: 0,
                                                    }}
                                                    transition={{
                                                        duration: 0.25,
                                                        delay: Math.min(
                                                            0.03 * index,
                                                            0.18,
                                                        ),
                                                    }}
                                                    className={cn(
                                                        'group',
                                                        isActive &&
                                                            'bg-muted/60',
                                                    )}
                                                >
                                                    <TableCell className="py-4">
                                                        <div className="flex items-center gap-3">
                                                            <div
                                                                className={cn(
                                                                    'grid h-9 w-9 place-items-center rounded-xl border bg-background',
                                                                    isActive &&
                                                                        'border-primary/30 bg-primary/5',
                                                                )}
                                                            >
                                                                <Building2
                                                                    className={cn(
                                                                        'h-4.5 w-4.5 text-foreground/70',
                                                                        isActive &&
                                                                            'text-primary',
                                                                    )}
                                                                />
                                                            </div>
                                                            <div className="min-w-0">
                                                                <div className="truncate text-sm font-semibold">
                                                                    {
                                                                        company.name
                                                                    }
                                                                </div>
                                                                <div className="truncate text-xs text-muted-foreground">
                                                                    ID:{' '}
                                                                    {company.id}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </TableCell>

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

                                                    <TableCell className="py-4 text-right">
                                                        {isActive ? (
                                                            <Button
                                                                variant="secondary"
                                                                size="sm"
                                                                disabled
                                                                className="cursor-not-allowed"
                                                            >
                                                                Current
                                                            </Button>
                                                        ) : (
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                className="transition-transform hover:scale-[1.02] active:scale-[0.98]"
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
                                                                                    console.log(
                                                                                        errors,
                                                                                    );
                                                                                    toast.error(
                                                                                        'helo',
                                                                                    );
                                                                                    const msg =
                                                                                        errors?.company_id ||
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
                                                    </TableCell>
                                                </motion.tr>
                                            );
                                        })
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                    </Card>
                </motion.div>
            </div>
        </AppLayout>
    );
};

type AddCompanyModalProps = {
    triggerVariant?: 'default' | 'secondary' | 'outline' | 'ghost' | 'link';
};

function AddCompanyModal({ triggerVariant = 'default' }: AddCompanyModalProps) {
    const [open, setOpen] = useState(false);

    // Inertia form
    const form = useForm<{
        name: string;
        email?: string;
        phone?: string;
        tpin?: string;
        address?: string;
    }>({
        name: '',
        email: '',
        phone: '',
        tpin: '',
        address: '',
    });

    const closeAndReset = () => {
        setOpen(false);
        form.reset();
        form.clearErrors();
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        // Optional: instant feedback
        const toastId = toast.loading('Creating company...');

        form.post('/companies', {
            preserveScroll: true,

            onSuccess: () => {
                toast.success('Company created successfully.', { id: toastId });
                closeAndReset();
            },

            onError: (errors) => {
                // 422 validation errors -> show first error
                const firstError =
                    errors?.name ||
                    errors?.email ||
                    errors?.phone ||
                    errors?.tpin ||
                    errors?.address;

                if (firstError) {
                    toast.warning(firstError, { id: toastId });
                    return;
                }

                // Non-validation errors
                toast.error('Failed to create company. Please try again.', {
                    id: toastId,
                });
            },

            onFinish: () => {
                // If something weird happens and toast is still loading,
                // Sonner will replace it via id above anyway.
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
                    className="gap-2 rounded-xl transition-transform hover:scale-[1.02] active:scale-[0.98]"
                >
                    <Plus className="h-4 w-4" />
                    Add company
                </Button>
            </DialogTrigger>

            <DialogContent className="overflow-hidden rounded-2xl p-0 sm:max-w-lg">
                {/* Animated wrapper inside DialogContent */}
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
                                    Add a company so you can manage clients,
                                    invoices, quotations, delivery notes and
                                    templates.
                                </DialogDescription>
                            </DialogHeader>

                            <form onSubmit={submit} className="mt-6 space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="company_name">
                                        Company name
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
                                    <AnimatePresence>
                                        {form.errors.name ? (
                                            <motion.p
                                                initial={{ opacity: 0, y: -6 }}
                                                animate={{ opacity: 1, y: 0 }}
                                                exit={{ opacity: 0, y: -6 }}
                                                className="text-sm text-destructive"
                                            >
                                                {form.errors.name}
                                            </motion.p>
                                        ) : null}
                                    </AnimatePresence>
                                </div>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="company_email">
                                            Email (optional)
                                        </Label>
                                        <Input
                                            id="company_email"
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
                                        <AnimatePresence>
                                            {form.errors.email ? (
                                                <motion.p
                                                    initial={{
                                                        opacity: 0,
                                                        y: -6,
                                                    }}
                                                    animate={{
                                                        opacity: 1,
                                                        y: 0,
                                                    }}
                                                    exit={{ opacity: 0, y: -6 }}
                                                    className="text-sm text-destructive"
                                                >
                                                    {form.errors.email}
                                                </motion.p>
                                            ) : null}
                                        </AnimatePresence>
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="company_phone">
                                            Phone (optional)
                                        </Label>
                                        <Input
                                            id="company_phone"
                                            placeholder="+260..."
                                            value={form.data.phone ?? ''}
                                            onChange={(e) =>
                                                form.setData(
                                                    'phone',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <AnimatePresence>
                                            {form.errors.phone ? (
                                                <motion.p
                                                    initial={{
                                                        opacity: 0,
                                                        y: -6,
                                                    }}
                                                    animate={{
                                                        opacity: 1,
                                                        y: 0,
                                                    }}
                                                    exit={{ opacity: 0, y: -6 }}
                                                    className="text-sm text-destructive"
                                                >
                                                    {form.errors.phone}
                                                </motion.p>
                                            ) : null}
                                        </AnimatePresence>
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="company_tpin">
                                            TPIN (optional)
                                        </Label>
                                        <Input
                                            id="company_tpin"
                                            placeholder="e.g. 100XXXXXXX"
                                            value={form.data.tpin ?? ''}
                                            onChange={(e) =>
                                                form.setData(
                                                    'tpin',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <AnimatePresence>
                                            {form.errors.tpin ? (
                                                <motion.p
                                                    initial={{
                                                        opacity: 0,
                                                        y: -6,
                                                    }}
                                                    animate={{
                                                        opacity: 1,
                                                        y: 0,
                                                    }}
                                                    exit={{ opacity: 0, y: -6 }}
                                                    className="text-sm text-destructive"
                                                >
                                                    {form.errors.tpin}
                                                </motion.p>
                                            ) : null}
                                        </AnimatePresence>
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="company_address">
                                            Address (optional)
                                        </Label>
                                        <Input
                                            id="company_address"
                                            placeholder="Lusaka, Zambia"
                                            value={form.data.address ?? ''}
                                            onChange={(e) =>
                                                form.setData(
                                                    'address',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <AnimatePresence>
                                            {form.errors.address ? (
                                                <motion.p
                                                    initial={{
                                                        opacity: 0,
                                                        y: -6,
                                                    }}
                                                    animate={{
                                                        opacity: 1,
                                                        y: 0,
                                                    }}
                                                    exit={{ opacity: 0, y: -6 }}
                                                    className="text-sm text-destructive"
                                                >
                                                    {form.errors.address}
                                                </motion.p>
                                            ) : null}
                                        </AnimatePresence>
                                    </div>
                                </div>

                                <div className="pt-2">
                                    <Separator className="my-4" />
                                    <div className="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-end">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            onClick={closeAndReset}
                                            disabled={form.processing}
                                            className="rounded-xl"
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
                                            className="rounded-xl"
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

                                    <AnimatePresence>
                                        {Object.keys(form.errors).length > 0 &&
                                        !form.errors.name &&
                                        !form.errors.email &&
                                        !form.errors.phone &&
                                        !form.errors.tpin &&
                                        !form.errors.address ? (
                                            <motion.p
                                                initial={{ opacity: 0, y: 6 }}
                                                animate={{ opacity: 1, y: 0 }}
                                                exit={{ opacity: 0, y: 6 }}
                                                className="mt-3 text-sm text-destructive"
                                            >
                                                Please review the form fields
                                                and try again.
                                            </motion.p>
                                        ) : null}
                                    </AnimatePresence>
                                </div>
                            </form>
                        </div>
                    </motion.div>
                </AnimatePresence>
            </DialogContent>
        </Dialog>
    );
}

export default CompaniesIndex;
