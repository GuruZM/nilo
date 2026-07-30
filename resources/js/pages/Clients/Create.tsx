import AppLayout from '@/layouts/app-layout';
import { Head, useForm } from '@inertiajs/react';
import { motion } from 'framer-motion';
import {
    ArrowLeft,
    Building2,
    IdCard,
    Lightbulb,
    Mail,
    MapPin,
    Phone,
    UserPlus,
} from 'lucide-react';
import React from 'react';
import { toast } from 'sonner';

import {
    Chip,
    FormField,
    Panel,
    PanelHeader,
    PillButton,
    SoftTile,
    TotalRow,
    fieldInputClass,
} from '@/components/dashboard/primitives';
import NiloSpinner from '@/components/nilo-spinner';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types/index.d';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clients', href: '/clients' },
    { title: 'Create', href: '/clients/create' },
];

const TIPS = [
    'Add an email to send invoices directly.',
    'TPIN helps with compliance and clean receipts.',
    'Use notes for delivery instructions or billing rules.',
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

    /** Repeated per field so the error line sits inside the labelled wrapper. */
    const fieldError = (message?: string) =>
        message ? <p className="text-xs text-destructive">{message}</p> : null;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Client" />

            <motion.div
                initial={{ opacity: 0, y: 14 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.3, ease: 'easeOut' }}
                className="flex w-full flex-col gap-4 py-6"
            >
                <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
                    <div className="lg:col-span-8">
                        <Panel>
                            <PanelHeader
                                icon={UserPlus}
                                title="Client information"
                                subtitle="Keep it accurate for clean invoices."
                                action={
                                    <Chip interactive={false}>
                                        Fields marked * are required
                                    </Chip>
                                }
                            />

                            <form
                                onSubmit={submit}
                                className="flex flex-col gap-4"
                            >
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <FormField
                                        label="Client name"
                                        htmlFor="name"
                                        required
                                    >
                                        <input
                                            id="name"
                                            placeholder="e.g. Cozyhouse Interiors"
                                            value={form.data.name}
                                            onChange={(e) =>
                                                form.setData(
                                                    'name',
                                                    e.target.value,
                                                )
                                            }
                                            className={fieldInputClass}
                                            required
                                        />
                                        {fieldError(form.errors.name)}
                                    </FormField>

                                    <FormField
                                        label="Contact person"
                                        htmlFor="contact_person"
                                    >
                                        <input
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
                                            className={fieldInputClass}
                                        />
                                        {fieldError(form.errors.contact_person)}
                                    </FormField>

                                    <FormField label="Email" htmlFor="email">
                                        <div className="relative">
                                            <Mail className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                            <input
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
                                                className={cn(
                                                    fieldInputClass,
                                                    'pl-9',
                                                )}
                                            />
                                        </div>
                                        {fieldError(form.errors.email)}
                                    </FormField>

                                    <FormField label="Phone" htmlFor="phone">
                                        <div className="relative">
                                            <Phone className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                            <input
                                                id="phone"
                                                placeholder="+260…"
                                                value={form.data.phone ?? ''}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'phone',
                                                        e.target.value,
                                                    )
                                                }
                                                className={cn(
                                                    fieldInputClass,
                                                    'pl-9',
                                                )}
                                            />
                                        </div>
                                        {fieldError(form.errors.phone)}
                                    </FormField>

                                    <FormField label="TPIN" htmlFor="tpin">
                                        <div className="relative">
                                            <IdCard className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                            <input
                                                id="tpin"
                                                placeholder="e.g. 100XXXXXXX"
                                                value={form.data.tpin ?? ''}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'tpin',
                                                        e.target.value,
                                                    )
                                                }
                                                className={cn(
                                                    fieldInputClass,
                                                    'pl-9',
                                                )}
                                            />
                                        </div>
                                        {fieldError(form.errors.tpin)}
                                    </FormField>

                                    <FormField
                                        label="Address"
                                        htmlFor="address"
                                    >
                                        <div className="relative">
                                            <MapPin className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                            <input
                                                id="address"
                                                placeholder="Street, Area"
                                                value={form.data.address ?? ''}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'address',
                                                        e.target.value,
                                                    )
                                                }
                                                className={cn(
                                                    fieldInputClass,
                                                    'pl-9',
                                                )}
                                            />
                                        </div>
                                        {fieldError(form.errors.address)}
                                    </FormField>

                                    <FormField label="City" htmlFor="city">
                                        <input
                                            id="city"
                                            placeholder="e.g. Lusaka"
                                            value={form.data.city ?? ''}
                                            onChange={(e) =>
                                                form.setData(
                                                    'city',
                                                    e.target.value,
                                                )
                                            }
                                            className={fieldInputClass}
                                        />
                                        {fieldError(form.errors.city)}
                                    </FormField>

                                    <FormField
                                        label="Country"
                                        htmlFor="country"
                                    >
                                        <input
                                            id="country"
                                            placeholder="e.g. Zambia"
                                            value={form.data.country ?? ''}
                                            onChange={(e) =>
                                                form.setData(
                                                    'country',
                                                    e.target.value,
                                                )
                                            }
                                            className={fieldInputClass}
                                        />
                                        {fieldError(form.errors.country)}
                                    </FormField>
                                </div>

                                <FormField label="Notes" htmlFor="notes">
                                    <textarea
                                        id="notes"
                                        rows={4}
                                        placeholder="Any extra details (delivery instructions, preferred contact time, etc.)"
                                        value={form.data.notes ?? ''}
                                        onChange={(e) =>
                                            form.setData(
                                                'notes',
                                                e.target.value,
                                            )
                                        }
                                        className={cn(
                                            fieldInputClass,
                                            'h-auto py-2',
                                        )}
                                    />
                                    {fieldError(form.errors.notes)}
                                </FormField>

                                <div className="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-end">
                                    <PillButton
                                        variant="ghost"
                                        onClick={goBack}
                                        disabled={form.processing}
                                    >
                                        <ArrowLeft className="h-4 w-4" />
                                        Cancel
                                    </PillButton>

                                    <PillButton
                                        type="submit"
                                        disabled={
                                            form.processing ||
                                            !form.data.name.trim()
                                        }
                                    >
                                        {form.processing ? (
                                            <>
                                                <NiloSpinner size={16} />
                                                Saving…
                                            </>
                                        ) : (
                                            <>
                                                <UserPlus className="h-4 w-4" />
                                                Create client
                                            </>
                                        )}
                                    </PillButton>
                                </div>
                            </form>
                        </Panel>
                    </div>

                    <div className="flex flex-col gap-4 lg:col-span-4">
                        <Panel>
                            <PanelHeader
                                icon={Building2}
                                title="Quick preview"
                                subtitle="What will appear on invoices."
                            />

                            <SoftTile className="flex flex-col gap-2">
                                <TotalRow
                                    label="Client"
                                    value={form.data.name?.trim() || '—'}
                                />
                                <TotalRow
                                    label="Contact"
                                    value={
                                        form.data.contact_person?.trim() || '—'
                                    }
                                />
                                <TotalRow
                                    label="Email"
                                    value={form.data.email?.trim() || '—'}
                                />
                                <TotalRow
                                    label="Phone"
                                    value={form.data.phone?.trim() || '—'}
                                />
                                <TotalRow
                                    label="TPIN"
                                    value={form.data.tpin?.trim() || '—'}
                                />
                            </SoftTile>
                        </Panel>

                        <Panel>
                            <PanelHeader icon={Lightbulb} title="Tips" />

                            <ul className="flex flex-col gap-2">
                                {TIPS.map((tip) => (
                                    <li
                                        key={tip}
                                        className="flex items-start gap-2 text-sm text-muted-foreground"
                                    >
                                        <span
                                            aria-hidden
                                            className="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-300 dark:bg-brand-500"
                                        />
                                        {tip}
                                    </li>
                                ))}
                            </ul>
                        </Panel>
                    </div>
                </div>
            </motion.div>
        </AppLayout>
    );
}
