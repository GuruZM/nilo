import { Head, router, useForm } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { Coins, Loader2, Pencil, Plus, Search, Trash2, X } from 'lucide-react';
import React, { useMemo, useState } from 'react';
import { toast } from 'sonner';

import HeadingSmall from '@/components/heading-small';
import { type BreadcrumbItem } from '@/types/index.d';

import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';

// shadcn
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
import { Switch } from '@/components/ui/switch';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
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

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Currencies',
        href: '/settings/currencies',
    },
];

export default function Currencies({ currencies }: { currencies: Currency[] }) {
    const [q, setQ] = useState('');

    const filtered = useMemo(() => {
        const needle = q.trim().toLowerCase();
        if (!needle) return currencies;

        return currencies.filter((c) =>
            `${c.code} ${c.name} ${c.symbol ?? ''}`
                .toLowerCase()
                .includes(needle),
        );
    }, [currencies, q]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Currencies" />

            <SettingsLayout>
                <div className="w-full space-y-6">
                    <HeadingSmall
                        title="Currencies"
                        description="Manage your system currencies (add, edit, activate/deactivate, delete)"
                    />

                    <motion.div
                        initial={{ opacity: 0, y: 10 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ duration: 0.25, ease: 'easeOut' }}
                        className="space-y-4"
                    >
                        <Card className="rounded-2xl bg-background p-4 shadow-sm">
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div className="flex items-center gap-2">
                                    <div className="grid h-9 w-9 place-items-center rounded-xl bg-muted">
                                        <Coins className="h-5 w-5 text-foreground/80" />
                                    </div>
                                    <div>
                                        <div className="text-sm font-semibold">
                                            Currency list
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {filtered.length} shown •{' '}
                                            {currencies.length} total
                                        </div>
                                    </div>
                                </div>

                                <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-center">
                                    <div className="relative w-full sm:w-72">
                                        <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                        <Input
                                            value={q}
                                            onChange={(e) =>
                                                setQ(e.target.value)
                                            }
                                            placeholder="Search code, name, symbol..."
                                            className="pl-9"
                                        />
                                    </div>

                                    <CurrencyModal mode="create" />
                                </div>
                            </div>

                            <Separator className="my-4" />

                            <div className="w-full overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="w-[18%]">
                                                Code
                                            </TableHead>
                                            <TableHead>Name</TableHead>
                                            <TableHead className="w-[12%]">
                                                Symbol
                                            </TableHead>
                                            <TableHead className="w-[14%]">
                                                Precision
                                            </TableHead>
                                            <TableHead className="w-[14%]">
                                                Status
                                            </TableHead>
                                            <TableHead className="w-[22%] text-right">
                                                Actions
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>

                                    <TableBody>
                                        {filtered.length === 0 ? (
                                            <TableRow>
                                                <TableCell
                                                    colSpan={6}
                                                    className="py-12 text-center"
                                                >
                                                    <div className="mx-auto max-w-sm">
                                                        <div className="mx-auto mb-3 grid h-12 w-12 place-items-center rounded-2xl bg-muted">
                                                            <Coins className="h-6 w-6 text-foreground/70" />
                                                        </div>
                                                        <div className="text-sm font-medium">
                                                            No currencies found
                                                        </div>
                                                        <div className="mt-1 text-sm text-muted-foreground">
                                                            Try a different
                                                            search or add one.
                                                        </div>
                                                        <div className="mt-4 flex justify-center">
                                                            <CurrencyModal
                                                                mode="create"
                                                                triggerVariant="outline"
                                                            />
                                                        </div>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        ) : (
                                            filtered.map((c, i) => (
                                                <motion.tr
                                                    key={c.id}
                                                    initial={{
                                                        opacity: 0,
                                                        y: 10,
                                                    }}
                                                    animate={{
                                                        opacity: 1,
                                                        y: 0,
                                                    }}
                                                    transition={{
                                                        duration: 0.22,
                                                        delay: Math.min(
                                                            i * 0.02,
                                                            0.15,
                                                        ),
                                                    }}
                                                    className="group"
                                                >
                                                    <TableCell className="py-4">
                                                        <Badge
                                                            variant="outline"
                                                            className="rounded-xl"
                                                        >
                                                            {c.code}
                                                        </Badge>
                                                    </TableCell>

                                                    <TableCell className="py-4">
                                                        <div className="text-sm font-semibold">
                                                            {c.name}
                                                        </div>
                                                    </TableCell>

                                                    <TableCell className="py-4">
                                                        <span className="text-sm">
                                                            {c.symbol || '—'}
                                                        </span>
                                                    </TableCell>

                                                    <TableCell className="py-4">
                                                        <span className="text-sm">
                                                            {c.precision}
                                                        </span>
                                                    </TableCell>

                                                    <TableCell className="py-4">
                                                        <Badge
                                                            variant={
                                                                c.is_active
                                                                    ? 'secondary'
                                                                    : 'outline'
                                                            }
                                                            className="rounded-xl"
                                                        >
                                                            {c.is_active
                                                                ? 'Active'
                                                                : 'Inactive'}
                                                        </Badge>
                                                    </TableCell>

                                                    <TableCell className="py-4 text-right">
                                                        <div className="flex justify-end gap-2">
                                                            <CurrencyModal
                                                                mode="edit"
                                                                currency={c}
                                                                trigger={
                                                                    <Button
                                                                        variant="outline"
                                                                        size="sm"
                                                                        className="rounded-xl transition-transform hover:scale-[1.02] active:scale-[0.98]"
                                                                    >
                                                                        <Pencil className="mr-2 h-4 w-4" />
                                                                        Edit
                                                                    </Button>
                                                                }
                                                            />

                                                            <DeleteCurrencyButton
                                                                currency={c}
                                                            />
                                                        </div>
                                                    </TableCell>
                                                </motion.tr>
                                            ))
                                        )}
                                    </TableBody>
                                </Table>
                            </div>
                        </Card>
                    </motion.div>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}

function CurrencyModal({
    mode,
    currency,
    triggerVariant = 'default',
    trigger,
}: {
    mode: 'create' | 'edit';
    currency?: Currency;
    triggerVariant?: 'default' | 'secondary' | 'outline' | 'ghost' | 'link';
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
                    <Button
                        variant={triggerVariant}
                        className="gap-2 rounded-xl transition-transform hover:scale-[1.02] active:scale-[0.98]"
                    >
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
                                <DialogTitle>
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
                                            placeholder="ZMW"
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
                                            placeholder="Zambian Kwacha"
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
                                            placeholder="K"
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

                                <div className="flex items-center justify-between rounded-xl border p-3">
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

                                <Separator />

                                <div className="flex justify-end gap-2">
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
                                            !form.data.code.trim() ||
                                            !form.data.name.trim()
                                        }
                                        className="rounded-xl transition-transform hover:scale-[1.02] active:scale-[0.98]"
                                    >
                                        {form.processing ? (
                                            <>
                                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
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
                <Button
                    variant="destructive"
                    size="sm"
                    className="rounded-xl transition-transform hover:scale-[1.02] active:scale-[0.98]"
                >
                    <Trash2 className="mr-2 h-4 w-4" />
                    Delete
                </Button>
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
