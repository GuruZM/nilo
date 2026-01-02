import { Head, useForm } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import {
    ArrowLeft,
    ArrowRight,
    BadgePercent,
    CalendarClock,
    CheckCircle2,
    ClipboardList,
    Eye,
    FileText,
    Plus,
    Receipt,
    Trash2,
} from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types/index.d';

// shadcn
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';

type Client = {
    id: number;
    name: string;
    email?: string | null;
    contact_person?: string | null;
};
type Template = { id: number; name: string; is_default: boolean };
type Currency = {
    code: string;
    name: string;
    symbol?: string | null;
    precision: number;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Invoices', href: '/invoices' },
    { title: 'Create', href: '/invoices/create' },
];

type StepKey = 'details' | 'items' | 'review';

const steps: { key: StepKey; title: string; description: string; icon: any }[] =
    [
        {
            key: 'details',
            title: 'Details',
            description: 'Client, template, dates, currency, recurrence',
            icon: ClipboardList,
        },
        {
            key: 'items',
            title: 'Items',
            description: 'Line items and pricing',
            icon: Receipt,
        },
        {
            key: 'review',
            title: 'Review',
            description: 'Discount, status, preview & create',
            icon: CheckCircle2,
        },
    ];

type InvoiceStatus = 'pending' | 'paid';

export default function InvoicesCreate({
    clients,
    templates,
    defaultCurrencyCode,
    currencies,
}: {
    clients: Client[];
    templates: Template[];
    defaultCurrencyCode: string;
    currencies?: { all: Currency[]; current: Currency | null };
}) {
    const currencyList = currencies?.all ?? [];
    const activeCurrency = currencies?.current ?? null;

    const [step, setStep] = React.useState<StepKey>('details');

    const form = useForm({
        // details
        client_id: '',
        invoice_template_id:
            templates.find((t) => t.is_default)?.id?.toString() ?? '',
        title: '',
        reference: '',
        issue_date: new Date().toISOString().slice(0, 10),
        due_date: '',
        currency_code: defaultCurrencyCode ?? activeCurrency?.code ?? 'ZMW',
        has_delivery_note: false,

        // ✅ status: default pending, optional paid
        status: 'pending' as InvoiceStatus,

        // recurrence
        is_recurring: false,
        recurrence_frequency: 'monthly',
        recurrence_interval: 1,
        recurrence_start_date: '',
        recurrence_end_date: '',

        // notes
        notes: '',
        terms: '',

        // ✅ moved to Review step (still in payload)
        invoice_discount: 0,

        // items
        items: [
            {
                description: '',
                unit: '',
                quantity: 1,
                unit_price: 0,
                discount: 0,
                tax: 0,
            },
        ],
    });

    const precision = React.useMemo(() => {
        const selected = currencyList.find(
            (c) => c.code === form.data.currency_code,
        );
        return selected?.precision ?? activeCurrency?.precision ?? 2;
    }, [currencyList, form.data.currency_code, activeCurrency?.precision]);

    const fmt = (v: number) => (Number.isFinite(v) ? v : 0).toFixed(precision);

    const addItem = () => {
        form.setData('items', [
            ...form.data.items,
            {
                description: '',
                unit: '',
                quantity: 1,
                unit_price: 0,
                discount: 0,
                tax: 0,
            },
        ]);
    };

    const removeItem = (idx: number) => {
        const next = form.data.items.filter((_, i) => i !== idx);
        form.setData(
            'items',
            next.length
                ? next
                : [
                      {
                          description: '',
                          unit: '',
                          quantity: 1,
                          unit_price: 0,
                          discount: 0,
                          tax: 0,
                      },
                  ],
        );
    };

    const updateItem = (idx: number, key: string, value: any) => {
        const next = [...form.data.items];
        (next[idx] as any)[key] = value;
        form.setData('items', next);
    };

    const computed = React.useMemo(() => {
        let subtotal = 0;
        let lineDiscount = 0;
        let tax = 0;

        for (const it of form.data.items) {
            const qty = Number(it.quantity || 0);
            const price = Number(it.unit_price || 0);
            const disc = Number(it.discount || 0);
            const t = Number(it.tax || 0);

            subtotal += qty * price;
            lineDiscount += disc;
            tax += t;
        }

        const invoiceDiscount = Number(form.data.invoice_discount || 0);
        const totalDiscount = lineDiscount + invoiceDiscount;

        const total = Math.max(0, subtotal - totalDiscount + tax);

        return {
            subtotal,
            lineDiscount,
            invoiceDiscount,
            totalDiscount,
            tax,
            total,
        };
    }, [form.data.items, form.data.invoice_discount]);

    const validateStep = () => {
        if (step === 'details') {
            if (!form.data.client_id) {
                toast.error('Please select a client.');
                return false;
            }
            if (!form.data.issue_date) {
                toast.error('Issue date is required.');
                return false;
            }
            if (!form.data.currency_code) {
                toast.error('Currency is required.');
                return false;
            }
            if (form.data.is_recurring) {
                if (!form.data.recurrence_frequency) {
                    toast.error('Select a recurrence frequency.');
                    return false;
                }
                if (
                    !form.data.recurrence_interval ||
                    form.data.recurrence_interval < 1
                ) {
                    toast.error('Recurrence interval must be at least 1.');
                    return false;
                }
            }
        }

        if (step === 'items') {
            const hasValidItem = form.data.items.some(
                (it) =>
                    (it.description || '').trim().length > 0 &&
                    Number(it.quantity) > 0 &&
                    Number(it.unit_price) >= 0,
            );
            if (!hasValidItem) {
                toast.error('Add at least one valid line item.');
                return false;
            }
        }

        if (step === 'review') {
            const d = Number(form.data.invoice_discount || 0);
            if (d < 0) {
                toast.error('Invoice discount cannot be negative.');
                return false;
            }
        }

        return true;
    };

    const goNext = () => {
        if (!validateStep()) return;
        const order: StepKey[] = ['details', 'items', 'review'];
        const idx = order.indexOf(step);
        setStep(order[Math.min(idx + 1, order.length - 1)]);
    };

    const goBack = () => {
        const order: StepKey[] = ['details', 'items', 'review'];
        const idx = order.indexOf(step);
        setStep(order[Math.max(idx - 1, 0)]);
    };

    const previewTemplate = () => {
        if (!form.data.client_id) {
            toast.error('Select a client first.');
            return;
        }
        if (!form.data.invoice_template_id) {
            toast.error('Select a template first.');
            return;
        }

        // ✅ Simple approach: open a preview route in a new tab
        // Create a GET route like: /invoices/preview?client_id=&invoice_template_id=&...
        // For now, we pass only what we need for a visual preview.
        const qs = new URLSearchParams({
            client_id: form.data.client_id,
            invoice_template_id: form.data.invoice_template_id,
            currency_code: form.data.currency_code,
        }).toString();

        window.open(`/invoices/preview?${qs}`, '_blank', 'noopener,noreferrer');
    };

    const submit = () => {
        // ensure current step valid
        if (step !== 'review') {
            toast.error('Please complete the review step before creating.');
            return;
        }
        if (!validateStep()) return;

        form.post('/invoices', {
            preserveScroll: true,
            // ✅ crucial: makes Inertia re-fetch shared props + page props after redirect
            preserveState: false,
            // ✅ If your controller redirects to /invoices after store,
            // the UI will update naturally, and onSuccess will fire.
            onSuccess: () => toast.success('Invoice created.'),
            onError: (errors) =>
                toast.error(
                    errors?.client_id ||
                        errors?.currency_code ||
                        errors?.invoice_template_id ||
                        errors?.status ||
                        errors?.invoice_discount ||
                        errors?.items ||
                        errors?.invoice ||
                        'Failed to create invoice.',
                ),
        });
    };

    const StepIcon = steps.find((s) => s.key === step)?.icon ?? ClipboardList;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create invoice" />

            <motion.div
                initial={{ opacity: 0, y: 14 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.3, ease: 'easeOut' }}
                className="mx-auto w-full px-4 py-10 sm:px-6"
            >
                {/* Header */}
                <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="flex items-start gap-3">
                        <div className="grid h-11 w-11 place-items-center rounded-2xl bg-muted">
                            <FileText className="h-6 w-6 text-foreground/80" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                                Create invoice
                            </h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Step-by-step flow: details → items → review.
                                Recurring is ready for cron jobs later.
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        {step !== 'details' && (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={goBack}
                                className="gap-2 rounded-xl"
                                disabled={form.processing}
                            >
                                <ArrowLeft className="h-4 w-4" />
                                Back
                            </Button>
                        )}

                        {step !== 'review' ? (
                            <Button
                                type="button"
                                onClick={goNext}
                                className="gap-2 rounded-xl transition-transform hover:scale-[1.02] active:scale-[0.98]"
                                disabled={form.processing}
                            >
                                Next
                                <ArrowRight className="h-4 w-4" />
                            </Button>
                        ) : (
                            <Button
                                type="button"
                                onClick={submit}
                                className="gap-2 rounded-xl transition-transform hover:scale-[1.02] active:scale-[0.98]"
                                disabled={form.processing}
                            >
                                Create invoice
                                <CheckCircle2 className="h-4 w-4" />
                            </Button>
                        )}
                    </div>
                </div>

                {/* Stepper */}
                <Card className="mb-6 rounded-2xl border bg-background p-4 shadow-sm">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-center gap-3">
                            <div className="grid h-10 w-10 place-items-center rounded-2xl bg-muted">
                                <StepIcon className="h-5 w-5 text-foreground/80" />
                            </div>
                            <div className="min-w-0">
                                <div className="text-sm font-semibold">
                                    {steps.find((s) => s.key === step)?.title}
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    {
                                        steps.find((s) => s.key === step)
                                            ?.description
                                    }
                                </div>
                            </div>
                        </div>

                        <div className="flex items-center gap-2">
                            {steps.map((s, i) => {
                                const active = s.key === step;
                                const done =
                                    (step === 'items' && s.key === 'details') ||
                                    (step === 'review' &&
                                        (s.key === 'details' ||
                                            s.key === 'items'));

                                return (
                                    <button
                                        key={s.key}
                                        type="button"
                                        onClick={() => {
                                            const order: StepKey[] = [
                                                'details',
                                                'items',
                                                'review',
                                            ];
                                            const cur = order.indexOf(step);
                                            const target = order.indexOf(s.key);

                                            // backward freely
                                            if (target <= cur) {
                                                setStep(s.key);
                                                return;
                                            }

                                            // forward requires current step valid
                                            if (!validateStep()) return;
                                            setStep(s.key);
                                        }}
                                        className={cn(
                                            'flex items-center gap-2 rounded-xl border px-3 py-2 text-sm transition',
                                            'hover:bg-muted/40',
                                            active && 'bg-muted/60',
                                        )}
                                    >
                                        <span
                                            className={cn(
                                                'grid h-6 w-6 place-items-center rounded-lg text-xs font-semibold',
                                                done
                                                    ? 'bg-foreground text-background'
                                                    : active
                                                      ? 'bg-foreground/90 text-background'
                                                      : 'bg-muted text-foreground/70',
                                            )}
                                        >
                                            {done ? (
                                                <CheckCircle2 className="h-4 w-4" />
                                            ) : (
                                                i + 1
                                            )}
                                        </span>
                                        <span className="hidden sm:inline">
                                            {s.title}
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                    </div>

                    <Separator className="my-4" />

                    {/* Totals preview (always visible) */}
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-6">
                        <MiniStat
                            label="Subtotal"
                            value={fmt(computed.subtotal)}
                        />
                        <MiniStat
                            label="Line discount"
                            value={fmt(computed.lineDiscount)}
                        />
                        <MiniStat label="Tax" value={fmt(computed.tax)} />
                        <MiniStat
                            label="Invoice discount"
                            value={
                                step === 'review'
                                    ? fmt(computed.invoiceDiscount)
                                    : '—'
                            }
                        />
                        <MiniStat
                            label="Total"
                            value={fmt(computed.total)}
                            strong
                        />
                        <MiniStat
                            label="Currency"
                            value={form.data.currency_code}
                        />
                    </div>
                </Card>

                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        if (step === 'review') submit();
                        else goNext();
                    }}
                    className="grid grid-cols-1 gap-6"
                >
                    <AnimatePresence mode="wait">
                        {/* STEP 1: DETAILS */}
                        {step === 'details' && (
                            <motion.div
                                key="details"
                                initial={{ opacity: 0, y: 10 }}
                                animate={{ opacity: 1, y: 0 }}
                                exit={{ opacity: 0, y: 10 }}
                                transition={{ duration: 0.22 }}
                                className="grid grid-cols-1 gap-6 lg:grid-cols-12"
                            >
                                <Card className="rounded-2xl border bg-background p-6 shadow-sm lg:col-span-7">
                                    <div className="mb-4 flex items-center gap-2">
                                        <ClipboardList className="h-5 w-5 opacity-80" />
                                        <div className="text-sm font-semibold">
                                            Invoice details
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label>Client *</Label>
                                            <Select
                                                value={form.data.client_id}
                                                onValueChange={(v) =>
                                                    form.setData('client_id', v)
                                                }
                                            >
                                                <SelectTrigger className="rounded-xl">
                                                    <SelectValue placeholder="Select client" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {clients.map((c) => (
                                                        <SelectItem
                                                            key={c.id}
                                                            value={String(c.id)}
                                                        >
                                                            {c.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            {form.errors.client_id && (
                                                <p className="text-sm text-destructive">
                                                    {form.errors.client_id}
                                                </p>
                                            )}
                                        </div>

                                        <div className="space-y-2">
                                            <Label>Template</Label>
                                            <Select
                                                value={
                                                    form.data
                                                        .invoice_template_id
                                                }
                                                onValueChange={(v) =>
                                                    form.setData(
                                                        'invoice_template_id',
                                                        v,
                                                    )
                                                }
                                            >
                                                <SelectTrigger className="rounded-xl">
                                                    <SelectValue placeholder="Select template" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {templates.map((t) => (
                                                        <SelectItem
                                                            key={t.id}
                                                            value={String(t.id)}
                                                        >
                                                            {t.name}
                                                            {t.is_default
                                                                ? ' (default)'
                                                                : ''}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            {form.errors
                                                .invoice_template_id && (
                                                <p className="text-sm text-destructive">
                                                    {
                                                        form.errors
                                                            .invoice_template_id
                                                    }
                                                </p>
                                            )}
                                        </div>

                                        <div className="space-y-2">
                                            <Label>Issue date *</Label>
                                            <Input
                                                type="date"
                                                value={form.data.issue_date}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'issue_date',
                                                        e.target.value,
                                                    )
                                                }
                                                className="rounded-xl"
                                                required
                                            />
                                        </div>

                                        <div className="space-y-2">
                                            <Label>Due date</Label>
                                            <Input
                                                type="date"
                                                value={form.data.due_date}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'due_date',
                                                        e.target.value,
                                                    )
                                                }
                                                className="rounded-xl"
                                            />
                                        </div>

                                        <div className="space-y-2 sm:col-span-2">
                                            <Label>Currency *</Label>
                                            <Select
                                                value={form.data.currency_code}
                                                onValueChange={(v) =>
                                                    form.setData(
                                                        'currency_code',
                                                        v,
                                                    )
                                                }
                                            >
                                                <SelectTrigger className="rounded-xl">
                                                    <SelectValue placeholder="Select currency" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {currencyList.map((c) => (
                                                        <SelectItem
                                                            key={c.code}
                                                            value={c.code}
                                                        >
                                                            {c.code} — {c.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <div className="space-y-2">
                                            <Label>Title</Label>
                                            <Input
                                                value={form.data.title}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'title',
                                                        e.target.value,
                                                    )
                                                }
                                                className="rounded-xl"
                                                placeholder="Optional invoice title"
                                            />
                                        </div>

                                        <div className="space-y-2">
                                            <Label>Reference</Label>
                                            <Input
                                                value={form.data.reference}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'reference',
                                                        e.target.value,
                                                    )
                                                }
                                                className="rounded-xl"
                                                placeholder="PO / Reference"
                                            />
                                        </div>
                                    </div>

                                    <Separator className="my-6" />

                                    <div className="flex items-center justify-between rounded-xl border p-4">
                                        <div>
                                            <div className="text-sm font-semibold">
                                                Delivery note
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                Enable if you want a delivery
                                                note option later.
                                            </div>
                                        </div>
                                        <Switch
                                            checked={
                                                !!form.data.has_delivery_note
                                            }
                                            onCheckedChange={(v) =>
                                                form.setData(
                                                    'has_delivery_note',
                                                    !!v,
                                                )
                                            }
                                        />
                                    </div>

                                    <Separator className="my-6" />

                                    <div className="grid grid-cols-1 gap-4">
                                        <div className="space-y-2">
                                            <Label>Notes</Label>
                                            <Textarea
                                                value={form.data.notes}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'notes',
                                                        e.target.value,
                                                    )
                                                }
                                                className="min-h-[90px] rounded-xl"
                                            />
                                        </div>

                                        <div className="space-y-2">
                                            <Label>Terms</Label>
                                            <Textarea
                                                value={form.data.terms}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'terms',
                                                        e.target.value,
                                                    )
                                                }
                                                className="min-h-[90px] rounded-xl"
                                            />
                                        </div>
                                    </div>
                                </Card>

                                <Card className="rounded-2xl border bg-background p-6 shadow-sm lg:col-span-5">
                                    <div className="mb-4 flex items-center gap-2">
                                        <CalendarClock className="h-5 w-5 opacity-80" />
                                        <div className="text-sm font-semibold">
                                            Recurrence
                                        </div>
                                    </div>

                                    <div className="flex items-center justify-between rounded-xl border p-4">
                                        <div>
                                            <div className="text-sm font-semibold">
                                                Recurring invoice
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                Leave room for cron jobs later.
                                            </div>
                                        </div>
                                        <Switch
                                            checked={!!form.data.is_recurring}
                                            onCheckedChange={(v) =>
                                                form.setData(
                                                    'is_recurring',
                                                    !!v,
                                                )
                                            }
                                        />
                                    </div>

                                    {form.data.is_recurring && (
                                        <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                            <div className="space-y-2 sm:col-span-2">
                                                <Label>Frequency *</Label>
                                                <Select
                                                    value={
                                                        form.data
                                                            .recurrence_frequency
                                                    }
                                                    onValueChange={(v) =>
                                                        form.setData(
                                                            'recurrence_frequency',
                                                            v,
                                                        )
                                                    }
                                                >
                                                    <SelectTrigger className="rounded-xl">
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="daily">
                                                            Daily
                                                        </SelectItem>
                                                        <SelectItem value="weekly">
                                                            Weekly
                                                        </SelectItem>
                                                        <SelectItem value="monthly">
                                                            Monthly
                                                        </SelectItem>
                                                        <SelectItem value="yearly">
                                                            Yearly
                                                        </SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>

                                            <div className="space-y-2">
                                                <Label>Interval *</Label>
                                                <Input
                                                    type="number"
                                                    min={1}
                                                    value={
                                                        form.data
                                                            .recurrence_interval
                                                    }
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'recurrence_interval',
                                                            Number(
                                                                e.target.value,
                                                            ),
                                                        )
                                                    }
                                                    className="rounded-xl"
                                                />
                                            </div>

                                            <div className="space-y-2">
                                                <Label>Start date</Label>
                                                <Input
                                                    type="date"
                                                    value={
                                                        form.data
                                                            .recurrence_start_date
                                                    }
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'recurrence_start_date',
                                                            e.target.value,
                                                        )
                                                    }
                                                    className="rounded-xl"
                                                />
                                            </div>

                                            <div className="space-y-2">
                                                <Label>End date</Label>
                                                <Input
                                                    type="date"
                                                    value={
                                                        form.data
                                                            .recurrence_end_date
                                                    }
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'recurrence_end_date',
                                                            e.target.value,
                                                        )
                                                    }
                                                    className="rounded-xl"
                                                />
                                            </div>
                                        </div>
                                    )}
                                </Card>
                            </motion.div>
                        )}

                        {/* STEP 2: ITEMS */}
                        {step === 'items' && (
                            <motion.div
                                key="items"
                                initial={{ opacity: 0, y: 10 }}
                                animate={{ opacity: 1, y: 0 }}
                                exit={{ opacity: 0, y: 10 }}
                                transition={{ duration: 0.22 }}
                                className="grid grid-cols-1 gap-6 lg:grid-cols-12"
                            >
                                <Card className="rounded-2xl border bg-background p-6 shadow-sm lg:col-span-8">
                                    <div className="mb-4 flex items-center justify-between gap-3">
                                        <div className="flex items-center gap-2">
                                            <Receipt className="h-5 w-5 opacity-80" />
                                            <div className="text-sm font-semibold">
                                                Line items
                                            </div>
                                        </div>

                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={addItem}
                                            className="rounded-xl transition-transform hover:scale-[1.02] active:scale-[0.98]"
                                        >
                                            <Plus className="mr-2 h-4 w-4" />
                                            Add item
                                        </Button>
                                    </div>

                                    <div className="space-y-4">
                                        {form.data.items.map((it, idx) => (
                                            <motion.div
                                                key={idx}
                                                initial={{ opacity: 0, y: 10 }}
                                                animate={{ opacity: 1, y: 0 }}
                                                transition={{ duration: 0.2 }}
                                                className="rounded-2xl border p-4"
                                            >
                                                <div className="flex items-start justify-between gap-2">
                                                    <div className="text-sm font-semibold">
                                                        Item {idx + 1}
                                                    </div>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            removeItem(idx)
                                                        }
                                                        className="rounded-xl"
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </div>

                                                <div className="mt-3 space-y-3">
                                                    <div className="space-y-2">
                                                        <Label>
                                                            Description *
                                                        </Label>
                                                        <Input
                                                            value={
                                                                it.description
                                                            }
                                                            onChange={(e) =>
                                                                updateItem(
                                                                    idx,
                                                                    'description',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            className="rounded-xl"
                                                            required
                                                        />
                                                    </div>

                                                    <div className="grid grid-cols-2 gap-3">
                                                        <div className="space-y-2">
                                                            <Label>Qty *</Label>
                                                            <Input
                                                                type="number"
                                                                min={0.01}
                                                                step={0.01}
                                                                value={
                                                                    it.quantity
                                                                }
                                                                onChange={(e) =>
                                                                    updateItem(
                                                                        idx,
                                                                        'quantity',
                                                                        Number(
                                                                            e
                                                                                .target
                                                                                .value,
                                                                        ),
                                                                    )
                                                                }
                                                                className="rounded-xl"
                                                            />
                                                        </div>

                                                        <div className="space-y-2">
                                                            <Label>
                                                                Unit price *
                                                            </Label>
                                                            <Input
                                                                type="number"
                                                                min={0}
                                                                step={0.01}
                                                                value={
                                                                    it.unit_price
                                                                }
                                                                onChange={(e) =>
                                                                    updateItem(
                                                                        idx,
                                                                        'unit_price',
                                                                        Number(
                                                                            e
                                                                                .target
                                                                                .value,
                                                                        ),
                                                                    )
                                                                }
                                                                className="rounded-xl"
                                                            />
                                                        </div>

                                                        <div className="space-y-2">
                                                            <Label>
                                                                Discount
                                                            </Label>
                                                            <Input
                                                                type="number"
                                                                min={0}
                                                                step={0.01}
                                                                value={
                                                                    it.discount
                                                                }
                                                                onChange={(e) =>
                                                                    updateItem(
                                                                        idx,
                                                                        'discount',
                                                                        Number(
                                                                            e
                                                                                .target
                                                                                .value,
                                                                        ),
                                                                    )
                                                                }
                                                                className="rounded-xl"
                                                            />
                                                        </div>

                                                        <div className="space-y-2">
                                                            <Label>Tax</Label>
                                                            <Input
                                                                type="number"
                                                                min={0}
                                                                step={0.01}
                                                                value={it.tax}
                                                                onChange={(e) =>
                                                                    updateItem(
                                                                        idx,
                                                                        'tax',
                                                                        Number(
                                                                            e
                                                                                .target
                                                                                .value,
                                                                        ),
                                                                    )
                                                                }
                                                                className="rounded-xl"
                                                            />
                                                        </div>
                                                    </div>
                                                </div>
                                            </motion.div>
                                        ))}
                                    </div>
                                </Card>

                                <Card className="rounded-2xl border bg-background p-6 shadow-sm lg:col-span-4">
                                    <div className="mb-2 text-sm font-semibold">
                                        Totals
                                    </div>

                                    <div className="space-y-2 rounded-2xl border bg-muted/20 p-4">
                                        <Row
                                            label="Subtotal"
                                            value={computed.subtotal}
                                            precision={precision}
                                        />
                                        <Row
                                            label="Line discount"
                                            value={computed.lineDiscount}
                                            precision={precision}
                                        />
                                        <Row
                                            label="Tax"
                                            value={computed.tax}
                                            precision={precision}
                                        />
                                        <Separator className="my-2" />
                                        <Row
                                            label="Total (preview)"
                                            value={computed.total}
                                            precision={precision}
                                            strong
                                        />
                                    </div>

                                    <div className="mt-4 text-xs text-muted-foreground">
                                        Overall invoice discount is applied on
                                        the review step.
                                    </div>
                                </Card>
                            </motion.div>
                        )}

                        {/* STEP 3: REVIEW */}
                        {step === 'review' && (
                            <motion.div
                                key="review"
                                initial={{ opacity: 0, y: 10 }}
                                animate={{ opacity: 1, y: 0 }}
                                exit={{ opacity: 0, y: 10 }}
                                transition={{ duration: 0.22 }}
                                className="grid grid-cols-1 gap-6 lg:grid-cols-12"
                            >
                                <Card className="rounded-2xl border bg-background p-6 shadow-sm lg:col-span-8">
                                    <div className="mb-4 flex items-center justify-between gap-3">
                                        <div className="flex items-center gap-2">
                                            <CheckCircle2 className="h-5 w-5 opacity-80" />
                                            <div className="text-sm font-semibold">
                                                Review
                                            </div>
                                        </div>

                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={previewTemplate}
                                            className="gap-2 rounded-xl transition-transform hover:scale-[1.02] active:scale-[0.98]"
                                        >
                                            <Eye className="h-4 w-4" />
                                            View template preview
                                        </Button>
                                    </div>

                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <ReviewField
                                            label="Client"
                                            value={clientLabel(
                                                clients,
                                                form.data.client_id,
                                            )}
                                        />
                                        <ReviewField
                                            label="Template"
                                            value={templateLabel(
                                                templates,
                                                form.data.invoice_template_id,
                                            )}
                                        />
                                        <ReviewField
                                            label="Issue date"
                                            value={form.data.issue_date || '—'}
                                        />
                                        <ReviewField
                                            label="Due date"
                                            value={form.data.due_date || '—'}
                                        />
                                        <ReviewField
                                            label="Currency"
                                            value={form.data.currency_code}
                                        />
                                        <ReviewField
                                            label="Delivery note"
                                            value={
                                                form.data.has_delivery_note
                                                    ? 'Yes'
                                                    : 'No'
                                            }
                                        />
                                        <ReviewField
                                            label="Recurring"
                                            value={
                                                form.data.is_recurring
                                                    ? 'Yes'
                                                    : 'No'
                                            }
                                        />
                                        <ReviewField
                                            label="Status"
                                            value={form.data.status}
                                        />
                                    </div>

                                    <Separator className="my-6" />

                                    {/* ✅ Overall discount now on review */}
                                    <div className="rounded-2xl border p-4">
                                        <div className="mb-3 flex items-start gap-3">
                                            <div className="grid h-10 w-10 place-items-center rounded-2xl bg-muted">
                                                <BadgePercent className="h-5 w-5 opacity-80" />
                                            </div>
                                            <div className="min-w-0">
                                                <div className="text-sm font-semibold">
                                                    Overall invoice discount
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    Added on top of line-item
                                                    discounts.
                                                </div>
                                            </div>
                                        </div>

                                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                            <div className="space-y-2">
                                                <Label>Discount amount</Label>
                                                <Input
                                                    type="number"
                                                    min={0}
                                                    step={0.01}
                                                    value={
                                                        form.data
                                                            .invoice_discount
                                                    }
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'invoice_discount',
                                                            Number(
                                                                e.target.value,
                                                            ),
                                                        )
                                                    }
                                                    className="rounded-xl"
                                                />
                                            </div>

                                            <div className="space-y-2">
                                                <Label>Invoice status</Label>
                                                <Select
                                                    value={form.data.status}
                                                    onValueChange={(v) =>
                                                        form.setData(
                                                            'status',
                                                            v as InvoiceStatus,
                                                        )
                                                    }
                                                >
                                                    <SelectTrigger className="rounded-xl">
                                                        <SelectValue placeholder="Select status" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="pending">
                                                            Pending (default)
                                                        </SelectItem>
                                                        <SelectItem value="paid">
                                                            Paid
                                                        </SelectItem>
                                                    </SelectContent>
                                                </Select>
                                                {form.errors.status && (
                                                    <p className="text-sm text-destructive">
                                                        {form.errors.status}
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                    </div>

                                    <Separator className="my-6" />

                                    <div className="space-y-3">
                                        <div className="text-sm font-semibold">
                                            Items
                                        </div>

                                        <div className="rounded-2xl border">
                                            <div className="grid grid-cols-12 gap-2 border-b bg-muted/40 px-4 py-3 text-xs font-semibold text-muted-foreground">
                                                <div className="col-span-6">
                                                    Description
                                                </div>
                                                <div className="col-span-2 text-right">
                                                    Qty
                                                </div>
                                                <div className="col-span-2 text-right">
                                                    Price
                                                </div>
                                                <div className="col-span-2 text-right">
                                                    Line
                                                </div>
                                            </div>

                                            <div className="divide-y">
                                                {form.data.items.map(
                                                    (it, idx) => {
                                                        const qty = Number(
                                                            it.quantity || 0,
                                                        );
                                                        const price = Number(
                                                            it.unit_price || 0,
                                                        );
                                                        const disc = Number(
                                                            it.discount || 0,
                                                        );
                                                        const tax = Number(
                                                            it.tax || 0,
                                                        );
                                                        const base =
                                                            qty * price;
                                                        const line = Math.max(
                                                            0,
                                                            base - disc + tax,
                                                        );

                                                        return (
                                                            <div
                                                                key={idx}
                                                                className="grid grid-cols-12 gap-2 px-4 py-3 text-sm"
                                                            >
                                                                <div className="col-span-6 min-w-0">
                                                                    <div className="truncate font-medium">
                                                                        {it.description ||
                                                                            '—'}
                                                                    </div>
                                                                </div>
                                                                <div className="col-span-2 text-right">
                                                                    {fmt(qty)}
                                                                </div>
                                                                <div className="col-span-2 text-right">
                                                                    {fmt(price)}
                                                                </div>
                                                                <div className="col-span-2 text-right font-semibold">
                                                                    {fmt(line)}
                                                                </div>
                                                            </div>
                                                        );
                                                    },
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                </Card>

                                <Card className="rounded-2xl border bg-background p-6 shadow-sm lg:col-span-4">
                                    <div className="mb-4 flex items-center gap-2">
                                        <BadgePercent className="h-5 w-5 opacity-80" />
                                        <div className="text-sm font-semibold">
                                            Final totals
                                        </div>
                                    </div>

                                    <div className="space-y-2 rounded-2xl border bg-muted/20 p-4">
                                        <Row
                                            label="Subtotal"
                                            value={computed.subtotal}
                                            precision={precision}
                                        />
                                        <Row
                                            label="Line discount"
                                            value={computed.lineDiscount}
                                            precision={precision}
                                        />
                                        <Row
                                            label="Invoice discount"
                                            value={computed.invoiceDiscount}
                                            precision={precision}
                                        />
                                        <Row
                                            label="Tax"
                                            value={computed.tax}
                                            precision={precision}
                                        />
                                        <Separator className="my-2" />
                                        <Row
                                            label="Total"
                                            value={computed.total}
                                            precision={precision}
                                            strong
                                        />
                                    </div>

                                    <Button
                                        type="button"
                                        onClick={submit}
                                        disabled={form.processing}
                                        className={cn(
                                            'mt-4 w-full gap-2 rounded-xl transition-transform hover:scale-[1.02] active:scale-[0.98]',
                                        )}
                                    >
                                        Create invoice
                                        <CheckCircle2 className="h-4 w-4" />
                                    </Button>

                                    <div className="mt-3 text-xs text-muted-foreground">
                                        If you’re not seeing success feedback/UI
                                        updates: make sure your{' '}
                                        <span className="font-medium">
                                            store()
                                        </span>{' '}
                                        returns an Inertia redirect (not JSON).
                                    </div>
                                </Card>
                            </motion.div>
                        )}
                    </AnimatePresence>

                    {/* Mobile nav */}
                    <div className="flex items-center justify-between gap-2 lg:hidden">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={goBack}
                            disabled={step === 'details' || form.processing}
                            className="gap-2 rounded-xl"
                        >
                            <ArrowLeft className="h-4 w-4" />
                            Back
                        </Button>

                        {step !== 'review' ? (
                            <Button
                                type="button"
                                onClick={goNext}
                                disabled={form.processing}
                                className="gap-2 rounded-xl"
                            >
                                Next
                                <ArrowRight className="h-4 w-4" />
                            </Button>
                        ) : (
                            <Button
                                type="button"
                                onClick={submit}
                                disabled={form.processing}
                                className="gap-2 rounded-xl"
                            >
                                Create
                                <CheckCircle2 className="h-4 w-4" />
                            </Button>
                        )}
                    </div>
                </form>
            </motion.div>
        </AppLayout>
    );
}

function MiniStat({
    label,
    value,
    strong,
}: {
    label: string;
    value: string;
    strong?: boolean;
}) {
    return (
        <div className="rounded-xl border bg-background p-3">
            <div className="text-[11px] text-muted-foreground">{label}</div>
            <div
                className={cn(
                    'mt-1 text-sm font-semibold',
                    strong && 'text-base',
                )}
            >
                {value}
            </div>
        </div>
    );
}

function Row({
    label,
    value,
    precision,
    strong,
}: {
    label: string;
    value: number;
    precision: number;
    strong?: boolean;
}) {
    return (
        <div className="flex items-center justify-between">
            <span className="text-xs text-muted-foreground">{label}</span>
            <span
                className={cn('text-sm', strong && 'text-base font-semibold')}
            >
                {Number.isFinite(value)
                    ? value.toFixed(precision)
                    : (0).toFixed(precision)}
            </span>
        </div>
    );
}

function ReviewField({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-2xl border p-4">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-1 text-sm font-semibold capitalize">{value}</div>
        </div>
    );
}

function clientLabel(clients: Client[], id: string) {
    const c = clients.find((x) => String(x.id) === String(id));
    return c ? c.name : '—';
}

function templateLabel(templates: Template[], id: string) {
    const t = templates.find((x) => String(x.id) === String(id));
    return t ? t.name : '—';
}
