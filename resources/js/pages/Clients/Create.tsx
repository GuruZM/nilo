import AppLayout from '@/layouts/app-layout';
import { Head, useForm } from '@inertiajs/react';
import { motion } from 'framer-motion';
import {
    ArrowLeft,
    Building2,
    IdCard,
    Loader2,
    Mail,
    MapPin,
    Phone,
    StickyNote,
    UserPlus,
    Users,
} from 'lucide-react';
import React from 'react';
import { toast } from 'sonner';

// shadcn
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import { type BreadcrumbItem } from '@/types/index.d';

const pageEnter = {
    hidden: { opacity: 0, y: 14 },
    show: { opacity: 1, y: 0, transition: { duration: 0.35, ease: 'easeOut' } },
};

const sectionEnter = (delay = 0) => ({
    hidden: { opacity: 0, y: 10 },
    show: {
        opacity: 1,
        y: 0,
        transition: { duration: 0.3, ease: 'easeOut', delay },
    },
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clients', href: '/clients' },
    { title: 'Create', href: '/clients/create' },
];

export default function ClientsCreate() {
    const form = useForm({
        name: '',
        contact_person: '',
        email: '',
        phone: '',
        tpin: '',
        address: '',
        city: '',
        country: '',
        notes: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        form.post('/clients', {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Client created.');
                form.reset();
            },
            onError: (errors) => {
                toast.error(
                    errors?.name ||
                        errors?.email ||
                        errors?.phone ||
                        errors?.tpin ||
                        'Failed to create client.',
                );
            },
        });
    };

    const goBack = () => window.history.back();

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Client" />

            <motion.div
                // variants={pageEnter}
                initial="hidden"
                animate="show"
                className="mx-auto w-full max-w-5xl px-4 py-10 sm:px-6"
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
                                    Create client
                                </h1>
                                <Badge
                                    variant="secondary"
                                    className="hidden sm:inline-flex"
                                >
                                    Active company scoped
                                </Badge>
                            </div>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Add a client to your active company. You’ll be
                                able to attach this client to invoices,
                                quotations and delivery notes.
                            </p>
                        </div>
                    </div>

                    <div className="flex gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={goBack}
                            className="rounded-xl transition-transform hover:scale-[1.02] active:scale-[0.98]"
                        >
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back
                        </Button>
                        <Button
                            type="button"
                            onClick={() => {
                                const el =
                                    document.getElementById('client-form');
                                el?.scrollIntoView({
                                    behavior: 'smooth',
                                    block: 'start',
                                });
                            }}
                            className="rounded-xl transition-transform hover:scale-[1.02] active:scale-[0.98]"
                        >
                            <UserPlus className="mr-2 h-4 w-4" />
                            New client
                        </Button>
                    </div>
                </div>

                <Separator className="mb-6" />

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-12">
                    {/* Left: Form */}
                    <motion.div
                        // variants={sectionEnter(0.05)}
                        initial="hidden"
                        animate="show"
                        className="lg:col-span-8"
                        id="client-form"
                    >
                        <Card className="rounded-2xl border bg-background p-6 shadow-sm">
                            <div className="mb-5 flex items-center justify-between gap-3">
                                <div className="flex items-center gap-2">
                                    <div className="grid h-9 w-9 place-items-center rounded-xl bg-muted">
                                        <UserPlus className="h-5 w-5 text-foreground/80" />
                                    </div>
                                    <div>
                                        <div className="text-sm font-semibold">
                                            Client information
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            Keep it accurate for clean invoices.
                                        </div>
                                    </div>
                                </div>

                                <Badge
                                    variant="outline"
                                    className="hidden sm:inline-flex"
                                >
                                    Fields marked * are required
                                </Badge>
                            </div>

                            <form onSubmit={submit} className="space-y-6">
                                {/* Name + Contact person */}
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
                                        <Label htmlFor="contact_person">
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

                                {/* Email + Phone */}
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

                                {/* TPIN + Address */}
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

                                {/* City + Country */}
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
                                        {form.errors.city && (
                                            <p className="text-sm text-destructive">
                                                {form.errors.city}
                                            </p>
                                        )}
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
                                        {form.errors.country && (
                                            <p className="text-sm text-destructive">
                                                {form.errors.country}
                                            </p>
                                        )}
                                    </div>
                                </div>

                                {/* Notes */}
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
                                        placeholder="Any extra details (delivery instructions, preferred contact time, etc.)"
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

                                {/* Actions */}
                                <div className="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-end">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        onClick={goBack}
                                        disabled={form.processing}
                                        className="rounded-xl"
                                    >
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
                                                Create client
                                            </>
                                        )}
                                    </Button>
                                </div>
                            </form>
                        </Card>
                    </motion.div>

                    {/* Right: Preview / Tips */}
                    <motion.div
                        // variants={sectionEnter(0.12)}
                        initial="hidden"
                        animate="show"
                        className="lg:col-span-4"
                    >
                        <div className="space-y-6">
                            <Card className="rounded-2xl border bg-background p-6 shadow-sm">
                                <div className="flex items-center gap-2">
                                    <div className="grid h-9 w-9 place-items-center rounded-xl bg-muted">
                                        <Building2 className="h-5 w-5 text-foreground/80" />
                                    </div>
                                    <div>
                                        <div className="text-sm font-semibold">
                                            Quick preview
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            What will appear on invoices.
                                        </div>
                                    </div>
                                </div>

                                <div className="mt-5 space-y-3 text-sm">
                                    <div className="flex items-start justify-between gap-3">
                                        <span className="text-muted-foreground">
                                            Client
                                        </span>
                                        <span className="max-w-[60%] truncate text-right font-medium">
                                            {form.data.name?.trim() || '—'}
                                        </span>
                                    </div>

                                    <div className="flex items-start justify-between gap-3">
                                        <span className="text-muted-foreground">
                                            Contact
                                        </span>
                                        <span className="max-w-[60%] truncate text-right font-medium">
                                            {form.data.contact_person?.trim() ||
                                                '—'}
                                        </span>
                                    </div>

                                    <div className="flex items-start justify-between gap-3">
                                        <span className="text-muted-foreground">
                                            Email
                                        </span>
                                        <span className="max-w-[60%] truncate text-right font-medium">
                                            {form.data.email?.trim() || '—'}
                                        </span>
                                    </div>

                                    <div className="flex items-start justify-between gap-3">
                                        <span className="text-muted-foreground">
                                            Phone
                                        </span>
                                        <span className="max-w-[60%] truncate text-right font-medium">
                                            {form.data.phone?.trim() || '—'}
                                        </span>
                                    </div>

                                    <div className="flex items-start justify-between gap-3">
                                        <span className="text-muted-foreground">
                                            TPIN
                                        </span>
                                        <span className="max-w-[60%] truncate text-right font-medium">
                                            {form.data.tpin?.trim() || '—'}
                                        </span>
                                    </div>
                                </div>
                            </Card>

                            <Card className="rounded-2xl border bg-background p-6 shadow-sm">
                                <div className="text-sm font-semibold">
                                    Tips
                                </div>
                                <ul className="mt-3 space-y-2 text-sm text-muted-foreground">
                                    <li>
                                        • Add an email to send invoices
                                        directly.
                                    </li>
                                    <li>
                                        • TPIN helps with compliance and clean
                                        receipts.
                                    </li>
                                    <li>
                                        • Use notes for delivery instructions or
                                        billing rules.
                                    </li>
                                </ul>
                            </Card>
                        </div>
                    </motion.div>
                </div>
            </motion.div>
        </AppLayout>
    );
}
