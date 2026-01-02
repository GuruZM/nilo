import AppLayout from '@/layouts/app-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import {
    Contact,
    IdCard,
    Loader2,
    Mail,
    MapPin,
    Pencil,
    Phone,
    Search,
    StickyNote,
    Trash2,
    UserPlus,
    Users,
    X,
} from 'lucide-react';
import React, { useMemo, useState } from 'react';
import { toast } from 'sonner';

import type { PageProps } from '../../types';

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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';

interface Client {
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
    created_at: string;
}

interface ClientsIndexProps extends PageProps {
    clients: Client[];
}

const pageEnter = {
    hidden: { opacity: 0, y: 14 },
    show: { opacity: 1, y: 0, transition: { duration: 0.35, ease: 'easeOut' } },
};

const staggerRow = (i: number) => ({
    hidden: { opacity: 0, y: 10 },
    show: {
        opacity: 1,
        y: 0,
        transition: { duration: 0.22, delay: Math.min(i * 0.03, 0.18) },
    },
});

const fmtDate = (iso: string) => {
    try {
        return new Date(iso).toLocaleDateString();
    } catch {
        return iso;
    }
};

const ClientsIndex: React.FC<ClientsIndexProps> = ({ clients }) => {
    const [q, setQ] = useState('');

    const filtered = useMemo(() => {
        const needle = q.trim().toLowerCase();
        if (!needle) return clients;

        return clients.filter((c) => {
            const hay = [
                c.name,
                c.contact_person ?? '',
                c.email ?? '',
                c.phone ?? '',
                c.tpin ?? '',
                c.city ?? '',
                c.country ?? '',
            ]
                .join(' ')
                .toLowerCase();

            return hay.includes(needle);
        });
    }, [clients, q]);

    return (
        <AppLayout>
            <Head title="Clients" />

            <motion.div
                // variants={pageEnter}
                initial="hidden"
                animate="show"
                className="mx-auto w-full px-4 py-10 sm:px-6"
            >
                {/* Header */}
                <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div className="flex items-start gap-3">
                        <div className="grid h-11 w-11 place-items-center rounded-2xl bg-muted">
                            <Users className="h-6 w-6 text-foreground/80" />
                        </div>

                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                                    Clients
                                </h1>
                                <Badge
                                    variant="secondary"
                                    className="hidden sm:inline-flex"
                                >
                                    One place. Full control.
                                </Badge>
                            </div>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Create and manage clients for your active
                                company. Attach them to invoices and quotations.
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <AddOrEditClientModal mode="create" />
                    </div>
                </div>

                <Separator className="mb-6" />

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-12">
                    {/* Main list */}
                    <Card className="overflow-hidden rounded-2xl border bg-background shadow-sm lg:col-span-8">
                        <div className="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex items-center gap-2">
                                <div className="grid h-9 w-9 place-items-center rounded-xl bg-muted">
                                    <Users className="h-5 w-5 text-foreground/80" />
                                </div>
                                <div>
                                    <div className="text-sm font-semibold">
                                        Client list
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {filtered.length} shown •{' '}
                                        {clients.length} total
                                    </div>
                                </div>
                            </div>

                            <div className="relative w-full sm:w-80">
                                <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    value={q}
                                    onChange={(e) => setQ(e.target.value)}
                                    placeholder="Search name, contact, email, phone, TPIN..."
                                    className="pl-9"
                                />
                            </div>
                        </div>

                        <div className="w-full overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-[38%]">
                                            Client
                                        </TableHead>
                                        <TableHead>Email</TableHead>
                                        <TableHead>Phone</TableHead>
                                        <TableHead className="text-right">
                                            Actions
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>

                                <TableBody>
                                    {filtered.length === 0 ? (
                                        <TableRow>
                                            <TableCell
                                                colSpan={4}
                                                className="py-14 text-center"
                                            >
                                                <div className="mx-auto max-w-sm">
                                                    <div className="mx-auto mb-3 grid h-12 w-12 place-items-center rounded-2xl bg-muted">
                                                        <Users className="h-6 w-6 text-foreground/70" />
                                                    </div>
                                                    <div className="text-sm font-medium">
                                                        No matching clients
                                                    </div>
                                                    <div className="mt-1 text-sm text-muted-foreground">
                                                        Try a different search,
                                                        or add a new client.
                                                    </div>
                                                    <div className="mt-4">
                                                        <AddOrEditClientModal
                                                            mode="create"
                                                            triggerVariant="outline"
                                                        />
                                                    </div>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        filtered.map((client, i) => (
                                            <motion.tr
                                                key={client.id}
                                                variants={staggerRow(i)}
                                                initial="hidden"
                                                animate="show"
                                                className="group"
                                            >
                                                <TableCell className="py-4">
                                                    <div className="flex items-center gap-3">
                                                        <div className="grid h-9 w-9 place-items-center rounded-xl border bg-background">
                                                            <Users className="h-4.5 w-4.5 text-foreground/70" />
                                                        </div>

                                                        <div className="min-w-0">
                                                            <div className="truncate text-sm font-semibold">
                                                                {client.name}
                                                            </div>

                                                            {client.contact_person ? (
                                                                <div className="mt-0.5 truncate text-xs text-muted-foreground">
                                                                    Contact:{' '}
                                                                    {
                                                                        client.contact_person
                                                                    }
                                                                </div>
                                                            ) : (
                                                                <div className="mt-0.5 text-xs text-muted-foreground/60">
                                                                    Contact: —
                                                                </div>
                                                            )}

                                                            <div className="mt-0.5 text-xs text-muted-foreground">
                                                                Added:{' '}
                                                                {fmtDate(
                                                                    client.created_at,
                                                                )}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </TableCell>

                                                <TableCell className="py-4">
                                                    {client.email ? (
                                                        <span className="text-sm">
                                                            {client.email}
                                                        </span>
                                                    ) : (
                                                        <span className="text-sm text-muted-foreground">
                                                            —
                                                        </span>
                                                    )}
                                                </TableCell>

                                                <TableCell className="py-4">
                                                    {client.phone ? (
                                                        <span className="text-sm">
                                                            {client.phone}
                                                        </span>
                                                    ) : (
                                                        <span className="text-sm text-muted-foreground">
                                                            —
                                                        </span>
                                                    )}
                                                </TableCell>

                                                <TableCell className="py-4 text-right">
                                                    <div className="flex justify-end gap-2">
                                                        <AddOrEditClientModal
                                                            mode="edit"
                                                            client={client}
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

                                                        <DeleteClientButton
                                                            client={client}
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

                    {/* Side panel */}
                    <div className="space-y-6 lg:col-span-4">
                        <Card className="rounded-2xl border bg-background p-6 shadow-sm">
                            <div className="flex items-center justify-between gap-2">
                                <div className="text-sm font-semibold">
                                    Quick stats
                                </div>
                                <Badge variant="outline">Clients</Badge>
                            </div>

                            <div className="mt-4 grid grid-cols-2 gap-3">
                                <div className="rounded-xl border bg-background p-4">
                                    <div className="text-xs text-muted-foreground">
                                        Total
                                    </div>
                                    <div className="mt-1 text-2xl font-semibold">
                                        {clients.length}
                                    </div>
                                </div>
                                <div className="rounded-xl border bg-background p-4">
                                    <div className="text-xs text-muted-foreground">
                                        Shown
                                    </div>
                                    <div className="mt-1 text-2xl font-semibold">
                                        {filtered.length}
                                    </div>
                                </div>
                            </div>

                            <Separator className="my-5" />

                            <div className="text-sm font-semibold">Tips</div>
                            <ul className="mt-3 space-y-2 text-sm text-muted-foreground">
                                <li>• Add email to send invoices directly.</li>
                                <li>
                                    • TPIN keeps your invoices clean &
                                    compliant.
                                </li>
                                <li>• Use notes for billing instructions.</li>
                            </ul>
                        </Card>

                        <Card className="rounded-2xl border bg-background p-6 shadow-sm">
                            <div className="text-sm font-semibold">
                                Why contact person?
                            </div>
                            <p className="mt-2 text-sm text-muted-foreground">
                                Makes follow-ups easy when you’re chasing
                                approvals or payments. No more “who do I talk
                                to?” moments 😄
                            </p>
                        </Card>
                    </div>
                </div>
            </motion.div>
        </AppLayout>
    );
};

function AddOrEditClientModal({
    mode,
    client,
    triggerVariant = 'default',
    trigger,
}: {
    mode: 'create' | 'edit';
    client?: Client;
    triggerVariant?: 'default' | 'secondary' | 'outline' | 'ghost' | 'link';
    trigger?: React.ReactNode;
}) {
    const [open, setOpen] = useState(false);

    const form = useForm({
        name: client?.name ?? '',
        contact_person: client?.contact_person ?? '',
        email: client?.email ?? '',
        phone: client?.phone ?? '',
        tpin: client?.tpin ?? '',
        address: client?.address ?? '',
        city: client?.city ?? '',
        country: client?.country ?? '',
        notes: client?.notes ?? '',
    });

    const closeAndReset = () => {
        setOpen(false);
        form.clearErrors();
        if (mode === 'create') form.reset();
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        if (mode === 'create') {
            form.post('/clients', {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Client created.');
                    closeAndReset();
                },
                onError: (errors) => {
                    toast.error(
                        errors?.name ||
                            errors?.contact_person ||
                            errors?.email ||
                            errors?.phone ||
                            errors?.tpin ||
                            'Failed to create client.',
                    );
                },
            });
            return;
        }

        form.put(`/clients/${client!.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Client updated.');
                closeAndReset();
            },
            onError: (errors) => {
                toast.error(
                    errors?.name ||
                        errors?.contact_person ||
                        errors?.email ||
                        errors?.phone ||
                        errors?.tpin ||
                        'Failed to update client.',
                );
            },
        });
    };

    const Title = mode === 'create' ? 'Add client' : 'Edit client';
    const Desc =
        mode === 'create'
            ? 'Create a client for the active company.'
            : 'Update client details.';

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
                        <UserPlus className="h-4 w-4" />
                        Add client
                    </Button>
                )}
            </DialogTrigger>

            <DialogContent className="overflow-hidden rounded-2xl p-0 sm:max-w-2xl">
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
                                        {mode === 'create' ? (
                                            <UserPlus className="h-5 w-5 text-foreground/80" />
                                        ) : (
                                            <Users className="h-5 w-5 text-foreground/80" />
                                        )}
                                    </span>
                                    {Title}
                                </DialogTitle>
                                <DialogDescription>{Desc}</DialogDescription>
                            </DialogHeader>

                            <form onSubmit={submit} className="mt-6 space-y-5">
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="name">
                                            Client name *
                                        </Label>
                                        <Input
                                            id="name"
                                            placeholder="e.g. Cozyhouse Interiors"
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
                                        <Label
                                            htmlFor="contact_person"
                                            className="flex items-center gap-2"
                                        >
                                            <Contact className="h-4 w-4 text-muted-foreground" />
                                            Contact person
                                        </Label>
                                        <Input
                                            id="contact_person"
                                            placeholder="e.g. Mary Zulu"
                                            value={
                                                form.data.contact_person ?? ''
                                            }
                                            onChange={(e) =>
                                                form.setData(
                                                    'contact_person',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        {form.errors.contact_person && (
                                            <p className="text-sm text-destructive">
                                                {form.errors.contact_person}
                                            </p>
                                        )}
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label
                                            htmlFor="email"
                                            className="flex items-center gap-2"
                                        >
                                            <Mail className="h-4 w-4 text-muted-foreground" />
                                            Email
                                        </Label>
                                        <Input
                                            id="email"
                                            type="email"
                                            placeholder="billing@client.com"
                                            value={form.data.email ?? ''}
                                            onChange={(e) =>
                                                form.setData(
                                                    'email',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        {form.errors.email && (
                                            <p className="text-sm text-destructive">
                                                {form.errors.email}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label
                                            htmlFor="phone"
                                            className="flex items-center gap-2"
                                        >
                                            <Phone className="h-4 w-4 text-muted-foreground" />
                                            Phone
                                        </Label>
                                        <Input
                                            id="phone"
                                            placeholder="+260..."
                                            value={form.data.phone ?? ''}
                                            onChange={(e) =>
                                                form.setData(
                                                    'phone',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        {form.errors.phone && (
                                            <p className="text-sm text-destructive">
                                                {form.errors.phone}
                                            </p>
                                        )}
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label
                                            htmlFor="tpin"
                                            className="flex items-center gap-2"
                                        >
                                            <IdCard className="h-4 w-4 text-muted-foreground" />
                                            TPIN
                                        </Label>
                                        <Input
                                            id="tpin"
                                            placeholder="e.g. 100XXXXXXX"
                                            value={form.data.tpin ?? ''}
                                            onChange={(e) =>
                                                form.setData(
                                                    'tpin',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        {form.errors.tpin && (
                                            <p className="text-sm text-destructive">
                                                {form.errors.tpin}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label
                                            htmlFor="address"
                                            className="flex items-center gap-2"
                                        >
                                            <MapPin className="h-4 w-4 text-muted-foreground" />
                                            Address
                                        </Label>
                                        <Input
                                            id="address"
                                            placeholder="Street, Area"
                                            value={form.data.address ?? ''}
                                            onChange={(e) =>
                                                form.setData(
                                                    'address',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        {form.errors.address && (
                                            <p className="text-sm text-destructive">
                                                {form.errors.address}
                                            </p>
                                        )}
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="city">City</Label>
                                        <Input
                                            id="city"
                                            placeholder="e.g. Lusaka"
                                            value={form.data.city ?? ''}
                                            onChange={(e) =>
                                                form.setData(
                                                    'city',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="country">Country</Label>
                                        <Input
                                            id="country"
                                            placeholder="e.g. Zambia"
                                            value={form.data.country ?? ''}
                                            onChange={(e) =>
                                                form.setData(
                                                    'country',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <Label
                                        htmlFor="notes"
                                        className="flex items-center gap-2"
                                    >
                                        <StickyNote className="h-4 w-4 text-muted-foreground" />
                                        Notes
                                    </Label>
                                    <Textarea
                                        id="notes"
                                        placeholder="Billing instructions, delivery notes, etc."
                                        value={form.data.notes ?? ''}
                                        onChange={(e) =>
                                            form.setData(
                                                'notes',
                                                e.target.value,
                                            )
                                        }
                                        rows={4}
                                    />
                                    {form.errors.notes && (
                                        <p className="text-sm text-destructive">
                                            {form.errors.notes}
                                        </p>
                                    )}
                                </div>

                                <Separator />

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
                                        className="rounded-xl transition-transform hover:scale-[1.02] active:scale-[0.98]"
                                    >
                                        {form.processing ? (
                                            <>
                                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                Saving...
                                            </>
                                        ) : (
                                            <>
                                                <UserPlus className="mr-2 h-4 w-4" />
                                                Save client
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

function DeleteClientButton({ client }: { client: Client }) {
    const [open, setOpen] = useState(false);

    const del = () => {
        router.delete(`/clients/${client.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Client deleted.');
                setOpen(false);
            },
            onError: (errors) => {
                toast.error(errors?.client || 'Failed to delete client.');
            },
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
                    <AlertDialogTitle>Delete client?</AlertDialogTitle>
                    <AlertDialogDescription>
                        This will permanently remove{' '}
                        <span className="font-medium">{client.name}</span>.
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

export default ClientsIndex;
