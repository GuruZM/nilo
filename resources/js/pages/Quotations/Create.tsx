import { Head, Link, useForm } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import {
    ArrowLeft,
    ArrowRight,
    BadgePercent,
    CheckCircle2,
    ClipboardList,
    FileSignature,
    Plus,
    Receipt,
    Trash2,
} from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

import AppLayout from '@/layouts/app-layout';
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
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types/index.d';

type Client = {
    id: number;
    name: string;
    email?: string | null;
    contact_person?: string | null;
};

type Currency = {
    code: string;
    name: string;
    symbol?: string | null;
    precision: number;
};

type ItemInput = {
    description: string;
    unit: string;
    quantity: number;
    unit_price: number;
    discount: number;
    tax: number;
};

type StepKey = 'details' | 'items' | 'review';
type QuotationStatus = 'draft' | 'sent' | 'accepted' | 'expired';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Quotations', href: '/quotations' },
    { title: 'Create', href: '/quotations/create' },
];

const steps: {
    key: StepKey;
    title: string;
    description: string;
    icon: React.ElementType;
}[] = [
    {
        key: 'details',
        title: 'Details',
        description: 'Client, dates, currency, and terms',
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
        description: 'Status, totals, and create',
        icon: CheckCircle2,
    },
];

export default function QuotationsCreate({
    clients,
    defaultCurrencyCode,
    currencies,
    hasActiveCompany = true,
}: {
    clients: Client[];
    defaultCurrencyCode: string;
    currencies?: { all: Currency[]; current: Currency | null };
    hasActiveCompany?: boolean;
}) {
    const currencyList = currencies?.all ?? [];
    const activeCurrency = currencies?.current ?? null;
    const hasClients = clients.length > 0;
    const hasCurrencies = currencyList.length > 0;
    const canCreateQuotation = hasActiveCompany && hasClients && hasCurrencies;
    const initialCurrencyCode =
        currencyList.find((currency) => currency.code === defaultCurrencyCode)
            ?.code ??
        activeCurrency?.code ??
        currencyList[0]?.code ??
        '';

    const [step, setStep] = React.useState<StepKey>('details');

    const form = useForm({
        client_id: '',
        title: '',
        reference: '',
        issue_date: new Date().toISOString().slice(0, 10),
        valid_until: '',
        currency_code: initialCurrencyCode,
        status: 'draft' as QuotationStatus,
        notes: '',
        terms: '',
        quotation_discount: 0,
        items: [
            {
                description: '',
                unit: '',
                quantity: 1,
                unit_price: 0,
                discount: 0,
                tax: 0,
            },
        ] as ItemInput[],
    });

    const precision = React.useMemo(() => {
        const selected = currencyList.find(
            (currency) => currency.code === form.data.currency_code,
        );

        return selected?.precision ?? activeCurrency?.precision ?? 2;
    }, [activeCurrency?.precision, currencyList, form.data.currency_code]);

    const fmt = (value: number) =>
        (Number.isFinite(value) ? value : 0).toFixed(precision);

    const computed = React.useMemo(() => {
        let subtotal = 0;
        let lineDiscount = 0;
        let tax = 0;

        for (const item of form.data.items) {
            const quantity = Number(item.quantity || 0);
            const price = Number(item.unit_price || 0);
            const discount = Number(item.discount || 0);
            const itemTax = Number(item.tax || 0);

            subtotal += quantity * price;
            lineDiscount += discount;
            tax += itemTax;
        }

        const quotationDiscount = Number(form.data.quotation_discount || 0);
        const totalDiscount = lineDiscount + quotationDiscount;
        const total = Math.max(0, subtotal - totalDiscount + tax);

        return {
            subtotal,
            lineDiscount,
            quotationDiscount,
            tax,
            total,
        };
    }, [form.data.items, form.data.quotation_discount]);

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

    const removeItem = (index: number) => {
        const next = form.data.items.filter((_, current) => current !== index);

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

    const updateItem = (
        index: number,
        key: keyof ItemInput,
        value: string | number,
    ) => {
        const next = [...form.data.items];
        next[index] = {
            ...next[index],
            [key]: value,
        };
        form.setData('items', next);
    };

    const validateStep = (currentStep: StepKey) => {
        if (!hasActiveCompany) {
            return (
                toast.error(
                    'Add or select a company before creating a quotation.',
                ),
                false
            );
        }

        if (!hasClients) {
            return (toast.error('Add a client before creating a quotation.'), false);
        }

        if (!hasCurrencies) {
            return (
                toast.error('Add an active currency before creating a quotation.'),
                false
            );
        }

        if (currentStep === 'details') {
            if (!form.data.client_id) {
                return (toast.error('Select a client.'), false);
            }

            if (!form.data.issue_date) {
                return (toast.error('Issue date is required.'), false);
            }

            if (!form.data.currency_code) {
                return (toast.error('Currency is required.'), false);
            }
        }

        if (currentStep === 'items') {
            const hasValidItem = form.data.items.some(
                (item) =>
                    item.description.trim().length > 0 &&
                    Number(item.quantity) > 0 &&
                    Number(item.unit_price) >= 0,
            );

            if (!hasValidItem) {
                return (toast.error('Add at least one valid line item.'), false);
            }
        }

        if (currentStep === 'review') {
            if (Number(form.data.quotation_discount || 0) < 0) {
                return (
                    toast.error('Quotation discount cannot be negative.'),
                    false
                );
            }
        }

        return true;
    };

    const order: StepKey[] = ['details', 'items', 'review'];

    const goNext = () => {
        if (!validateStep(step)) {
            return;
        }

        setStep(order[Math.min(order.indexOf(step) + 1, order.length - 1)]);
    };

    const goBack = () => {
        setStep(order[Math.max(order.indexOf(step) - 1, 0)]);
    };

    const submit = () => {
        if (step !== 'review') {
            toast.error('Finish the review step first.');
            return;
        }

        if (!validateStep('review')) {
            return;
        }

        form.post('/quotations', {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => {
                toast.success('Quotation created.');
            },
            onError: (errors) => {
                const quotationError = (
                    errors as Record<string, string | undefined>
                ).quotation;

                toast.error(
                    quotationError ||
                        errors?.client_id ||
                        errors?.currency_code ||
                        errors?.status ||
                        errors?.items ||
                        'Failed to create quotation.',
                );
            },
        });
    };

    const currentStepIcon =
        steps.find((current) => current.key === step)?.icon ?? ClipboardList;
    const quotationError = (form.errors as Record<string, string | undefined>)
        .quotation;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create quotation" />

            <div className="sticky top-0 z-30 border-b bg-background/80 backdrop-blur">
                <div className="mx-auto flex w-full items-center justify-between gap-3 px-4 py-3 sm:px-6">
                    <div className="flex min-w-0 items-center gap-3">
                        <div className="grid h-10 w-10 place-items-center rounded-2xl bg-muted">
                            {React.createElement(currentStepIcon, {
                                className: 'h-5 w-5 opacity-80',
                            })}
                        </div>
                        <div className="min-w-0">
                            <div className="truncate text-sm font-semibold">
                                Create quotation
                            </div>
                            <div className="truncate text-xs text-muted-foreground">
                                {steps.find((current) => current.key === step)
                                    ?.title}{' '}
                                •{' '}
                                {steps.find((current) => current.key === step)
                                    ?.description}
                            </div>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={goBack}
                            disabled={step === 'details' || form.processing}
                            className="rounded-xl"
                        >
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back
                        </Button>

                        {step !== 'review' ? (
                            <Button
                                type="button"
                                onClick={goNext}
                                disabled={
                                    form.processing || !canCreateQuotation
                                }
                                className="rounded-xl transition-transform hover:scale-[1.02] active:scale-[0.98]"
                            >
                                Next
                                <ArrowRight className="ml-2 h-4 w-4" />
                            </Button>
                        ) : (
                            <Button
                                type="button"
                                onClick={submit}
                                disabled={
                                    form.processing || !canCreateQuotation
                                }
                                className="rounded-xl transition-transform hover:scale-[1.02] active:scale-[0.98]"
                            >
                                Create
                                <CheckCircle2 className="ml-2 h-4 w-4" />
                            </Button>
                        )}
                    </div>
                </div>
            </div>

            <motion.div
                initial={{ opacity: 0, y: 14 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.25, ease: 'easeOut' }}
                className="mx-auto w-full px-4 py-8 sm:px-6"
            >
                <div className="mb-6 flex items-start gap-3">
                    <div className="grid h-11 w-11 place-items-center rounded-2xl bg-muted">
                        <FileSignature className="h-6 w-6 opacity-80" />
                    </div>
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                            Create quotation
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Build a client-ready quotation with the same guided
                            flow as invoices.
                        </p>
                    </div>
                </div>

                {!canCreateQuotation && (
                    <Card className="mb-6 rounded-2xl border-dashed bg-muted/20 p-5 shadow-sm">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <div className="text-sm font-semibold">
                                    {hasActiveCompany
                                        ? 'Finish setup before creating quotations'
                                        : 'Add or select a company before creating quotations'}
                                </div>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {hasActiveCompany
                                        ? 'Quotations stay available even with no data, but you need at least one client and one active currency before you can create one.'
                                        : 'Quotations are available, but you need an active company before clients and quotations can be managed.'}
                                </p>
                            </div>

                            <div className="flex flex-col gap-2 sm:flex-row">
                                {hasActiveCompany ? (
                                    <>
                                        <Button asChild className="rounded-xl">
                                            <Link href="/clients/create">
                                                Add client
                                            </Link>
                                        </Button>
                                        <Button
                                            asChild
                                            variant="outline"
                                            className="rounded-xl"
                                        >
                                            <Link href="/settings/currencies">
                                                Manage currencies
                                            </Link>
                                        </Button>
                                    </>
                                ) : (
                                    <Button asChild className="rounded-xl">
                                        <Link href="/companies">
                                            Manage companies
                                        </Link>
                                    </Button>
                                )}
                            </div>
                        </div>
                    </Card>
                )}

                <div className="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-3">
                    {steps.map((current) => {
                        const Icon = current.icon;
                        const active = current.key === step;
                        const currentIndex = order.indexOf(step);
                        const targetIndex = order.indexOf(current.key);
                        const completed = targetIndex < currentIndex;

                        return (
                            <button
                                key={current.key}
                                type="button"
                                onClick={() => {
                                    if (targetIndex <= currentIndex) {
                                        setStep(current.key);
                                        return;
                                    }

                                    if (!validateStep(step)) {
                                        return;
                                    }

                                    setStep(current.key);
                                }}
                                className={cn(
                                    'rounded-2xl border bg-background p-4 text-left shadow-sm transition',
                                    'hover:bg-muted/20',
                                    active &&
                                        'border-foreground/20 bg-muted/20',
                                )}
                            >
                                <div className="flex items-start gap-3">
                                    <div
                                        className={cn(
                                            'grid h-10 w-10 place-items-center rounded-2xl',
                                            completed
                                                ? 'bg-foreground text-background'
                                                : active
                                                  ? 'bg-foreground/90 text-background'
                                                  : 'bg-muted',
                                        )}
                                    >
                                        <Icon className="h-5 w-5 opacity-90" />
                                    </div>

                                    <div className="min-w-0 flex-1">
                                        <div className="text-sm font-semibold">
                                            {current.title}
                                        </div>
                                        <div className="mt-1 text-xs text-muted-foreground">
                                            {current.description}
                                        </div>
                                    </div>
                                </div>
                            </button>
                        );
                    })}
                </div>

                <Card className="mb-6 rounded-2xl border bg-background p-4 shadow-sm">
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-5">
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
                            label="Quotation discount"
                            value={
                                step === 'review'
                                    ? fmt(computed.quotationDiscount)
                                    : '—'
                            }
                        />
                        <MiniStat
                            label="Currency"
                            value={form.data.currency_code || 'Not set'}
                        />
                    </div>
                </Card>

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        if (step === 'review') {
                            submit();
                            return;
                        }

                        goNext();
                    }}
                    className="space-y-6"
                >
                    <AnimatePresence mode="wait">
                        {step === 'details' && (
                            <motion.div
                                key="details"
                                initial={{ opacity: 0, y: 10 }}
                                animate={{ opacity: 1, y: 0 }}
                                exit={{ opacity: 0, y: 10 }}
                                transition={{ duration: 0.22 }}
                                className="grid grid-cols-1 gap-6 lg:grid-cols-12"
                            >
                                <Card className="rounded-2xl border bg-background p-6 shadow-sm lg:col-span-8">
                                    <SectionTitle
                                        icon={ClipboardList}
                                        title="Quotation details"
                                    />

                                    <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <Field label="Client *">
                                            <Select
                                                value={form.data.client_id}
                                                disabled={!hasClients}
                                                onValueChange={(value) =>
                                                    form.setData(
                                                        'client_id',
                                                        value,
                                                    )
                                                }
                                            >
                                                <SelectTrigger className="rounded-xl">
                                                    <SelectValue placeholder="Select client" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {hasClients ? (
                                                        clients.map((client) => (
                                                            <SelectItem
                                                                key={client.id}
                                                                value={String(
                                                                    client.id,
                                                                )}
                                                            >
                                                                {client.name}
                                                            </SelectItem>
                                                        ))
                                                    ) : (
                                                        <div className="px-2 py-3 text-sm text-muted-foreground">
                                                            No clients yet. Add
                                                            a client to
                                                            continue.
                                                        </div>
                                                    )}
                                                </SelectContent>
                                            </Select>
                                            {form.errors.client_id && (
                                                <p className="mt-1 text-sm text-destructive">
                                                    {form.errors.client_id}
                                                </p>
                                            )}
                                        </Field>

                                        <Field label="Currency *">
                                            <Select
                                                value={form.data.currency_code}
                                                disabled={!hasCurrencies}
                                                onValueChange={(value) =>
                                                    form.setData(
                                                        'currency_code',
                                                        value,
                                                    )
                                                }
                                            >
                                                <SelectTrigger className="rounded-xl">
                                                    <SelectValue placeholder="Select currency" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {hasCurrencies ? (
                                                        currencyList.map(
                                                            (currency) => (
                                                                <SelectItem
                                                                    key={
                                                                        currency.code
                                                                    }
                                                                    value={
                                                                        currency.code
                                                                    }
                                                                >
                                                                    {
                                                                        currency.code
                                                                    }{' '}
                                                                    —{' '}
                                                                    {
                                                                        currency.name
                                                                    }
                                                                </SelectItem>
                                                            ),
                                                        )
                                                    ) : (
                                                        <div className="px-2 py-3 text-sm text-muted-foreground">
                                                            No active
                                                            currencies
                                                            configured yet.
                                                        </div>
                                                    )}
                                                </SelectContent>
                                            </Select>
                                        </Field>

                                        <Field label="Issue date *">
                                            <Input
                                                type="date"
                                                value={form.data.issue_date}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'issue_date',
                                                        event.target.value,
                                                    )
                                                }
                                                className="rounded-xl"
                                            />
                                        </Field>

                                        <Field label="Valid until">
                                            <Input
                                                type="date"
                                                value={form.data.valid_until}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'valid_until',
                                                        event.target.value,
                                                    )
                                                }
                                                className="rounded-xl"
                                            />
                                        </Field>

                                        <Field label="Title">
                                            <Input
                                                value={form.data.title}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'title',
                                                        event.target.value,
                                                    )
                                                }
                                                className="rounded-xl"
                                                placeholder="Optional quotation title"
                                            />
                                        </Field>

                                        <Field label="Reference">
                                            <Input
                                                value={form.data.reference}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'reference',
                                                        event.target.value,
                                                    )
                                                }
                                                className="rounded-xl"
                                                placeholder="Customer reference"
                                            />
                                        </Field>

                                        <Field
                                            label="Notes"
                                            className="sm:col-span-2"
                                        >
                                            <Textarea
                                                value={form.data.notes}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'notes',
                                                        event.target.value,
                                                    )
                                                }
                                                className="min-h-24 rounded-xl"
                                                placeholder="Optional notes for the client"
                                            />
                                        </Field>

                                        <Field
                                            label="Terms"
                                            className="sm:col-span-2"
                                        >
                                            <Textarea
                                                value={form.data.terms}
                                                onChange={(event) =>
                                                    form.setData(
                                                        'terms',
                                                        event.target.value,
                                                    )
                                                }
                                                className="min-h-24 rounded-xl"
                                                placeholder="Optional terms and conditions"
                                            />
                                        </Field>
                                    </div>
                                </Card>

                                <Card className="rounded-2xl border bg-background p-6 shadow-sm lg:col-span-4">
                                    <SectionTitle
                                        icon={BadgePercent}
                                        title="At a glance"
                                    />
                                    <div className="mt-4 space-y-2 rounded-2xl border bg-muted/20 p-4">
                                        <ReviewField
                                            label="Client"
                                            value={clientLabel(
                                                clients,
                                                form.data.client_id,
                                            )}
                                        />
                                        <ReviewField
                                            label="Issue date"
                                            value={form.data.issue_date || '—'}
                                        />
                                        <ReviewField
                                            label="Valid until"
                                            value={form.data.valid_until || '—'}
                                        />
                                        <ReviewField
                                            label="Currency"
                                            value={
                                                form.data.currency_code || '—'
                                            }
                                        />
                                    </div>
                                    <div className="mt-4 text-xs text-muted-foreground">
                                        Move to the items step to price the
                                        quotation and calculate totals.
                                    </div>
                                </Card>
                            </motion.div>
                        )}

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
                                    <div className="flex items-center justify-between gap-3">
                                        <SectionTitle
                                            icon={Receipt}
                                            title="Line items"
                                        />
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={addItem}
                                            className="gap-2 rounded-xl"
                                        >
                                            <Plus className="h-4 w-4" />
                                            Add item
                                        </Button>
                                    </div>

                                    <div className="mt-4 space-y-4">
                                        {form.data.items.map((item, index) => (
                                            <motion.div
                                                key={index}
                                                layout
                                                className="rounded-2xl border p-4"
                                            >
                                                <div className="flex items-center justify-between gap-3">
                                                    <div className="text-sm font-semibold">
                                                        Item {index + 1}
                                                    </div>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() =>
                                                            removeItem(index)
                                                        }
                                                        className="rounded-xl"
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </div>

                                                <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                    <Field
                                                        label="Description"
                                                        className="sm:col-span-2"
                                                    >
                                                        <Input
                                                            value={
                                                                item.description
                                                            }
                                                            onChange={(
                                                                event,
                                                            ) =>
                                                                updateItem(
                                                                    index,
                                                                    'description',
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                            className="rounded-xl"
                                                            placeholder="Service or item description"
                                                        />
                                                    </Field>

                                                    <Field label="Unit">
                                                        <Input
                                                            value={item.unit}
                                                            onChange={(
                                                                event,
                                                            ) =>
                                                                updateItem(
                                                                    index,
                                                                    'unit',
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                            className="rounded-xl"
                                                            placeholder="hrs / pcs"
                                                        />
                                                    </Field>

                                                    <Field label="Quantity">
                                                        <Input
                                                            type="number"
                                                            min={0.01}
                                                            step={0.01}
                                                            value={
                                                                item.quantity
                                                            }
                                                            onChange={(
                                                                event,
                                                            ) =>
                                                                updateItem(
                                                                    index,
                                                                    'quantity',
                                                                    Number(
                                                                        event
                                                                            .target
                                                                            .value,
                                                                    ),
                                                                )
                                                            }
                                                            className="rounded-xl"
                                                        />
                                                    </Field>

                                                    <Field label="Unit price">
                                                        <Input
                                                            type="number"
                                                            min={0}
                                                            step={0.01}
                                                            value={
                                                                item.unit_price
                                                            }
                                                            onChange={(
                                                                event,
                                                            ) =>
                                                                updateItem(
                                                                    index,
                                                                    'unit_price',
                                                                    Number(
                                                                        event
                                                                            .target
                                                                            .value,
                                                                    ),
                                                                )
                                                            }
                                                            className="rounded-xl"
                                                        />
                                                    </Field>

                                                    <Field label="Discount">
                                                        <Input
                                                            type="number"
                                                            min={0}
                                                            step={0.01}
                                                            value={
                                                                item.discount
                                                            }
                                                            onChange={(
                                                                event,
                                                            ) =>
                                                                updateItem(
                                                                    index,
                                                                    'discount',
                                                                    Number(
                                                                        event
                                                                            .target
                                                                            .value,
                                                                    ),
                                                                )
                                                            }
                                                            className="rounded-xl"
                                                        />
                                                    </Field>

                                                    <Field label="Tax">
                                                        <Input
                                                            type="number"
                                                            min={0}
                                                            step={0.01}
                                                            value={item.tax}
                                                            onChange={(
                                                                event,
                                                            ) =>
                                                                updateItem(
                                                                    index,
                                                                    'tax',
                                                                    Number(
                                                                        event
                                                                            .target
                                                                            .value,
                                                                    ),
                                                                )
                                                            }
                                                            className="rounded-xl"
                                                        />
                                                    </Field>
                                                </div>
                                            </motion.div>
                                        ))}
                                    </div>
                                </Card>

                                <Card className="rounded-2xl border bg-background p-6 shadow-sm lg:col-span-4">
                                    <SectionTitle
                                        icon={BadgePercent}
                                        title="Totals preview"
                                    />
                                    <div className="mt-4 space-y-2 rounded-2xl border bg-muted/20 p-4">
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
                                            label="Total"
                                            value={computed.total}
                                            precision={precision}
                                            strong
                                        />
                                    </div>
                                    <div className="mt-4 text-xs text-muted-foreground">
                                        Overall quotation discount is applied on
                                        the review step.
                                    </div>
                                </Card>
                            </motion.div>
                        )}

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
                                    <SectionTitle
                                        icon={CheckCircle2}
                                        title="Review"
                                    />

                                    <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <ReviewField
                                            label="Client"
                                            value={clientLabel(
                                                clients,
                                                form.data.client_id,
                                            )}
                                        />
                                        <ReviewField
                                            label="Currency"
                                            value={
                                                form.data.currency_code ||
                                                'Not set'
                                            }
                                        />
                                        <ReviewField
                                            label="Issue date"
                                            value={form.data.issue_date || '—'}
                                        />
                                        <ReviewField
                                            label="Valid until"
                                            value={form.data.valid_until || '—'}
                                        />
                                        <ReviewField
                                            label="Title"
                                            value={form.data.title || '—'}
                                        />
                                        <ReviewField
                                            label="Reference"
                                            value={form.data.reference || '—'}
                                        />
                                    </div>

                                    <Separator className="my-6" />

                                    <div className="rounded-2xl border p-4">
                                        <div className="flex items-start gap-3">
                                            <div className="grid h-10 w-10 place-items-center rounded-2xl bg-muted">
                                                <BadgePercent className="h-5 w-5 opacity-80" />
                                            </div>
                                            <div className="min-w-0">
                                                <div className="text-sm font-semibold">
                                                    Final adjustments
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    Apply an overall discount
                                                    and set the quotation
                                                    status.
                                                </div>
                                            </div>
                                        </div>

                                        <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                            <Field label="Overall discount">
                                                <Input
                                                    type="number"
                                                    min={0}
                                                    step={0.01}
                                                    value={
                                                        form.data
                                                            .quotation_discount
                                                    }
                                                    onChange={(event) =>
                                                        form.setData(
                                                            'quotation_discount',
                                                            Number(
                                                                event.target
                                                                    .value,
                                                            ),
                                                        )
                                                    }
                                                    className="rounded-xl"
                                                />
                                            </Field>

                                            <div className="space-y-2">
                                                <Label>Status</Label>
                                                <div className="flex flex-wrap gap-2">
                                                    {(
                                                        [
                                                            'draft',
                                                            'sent',
                                                            'accepted',
                                                            'expired',
                                                        ] as QuotationStatus[]
                                                    ).map((status) => (
                                                        <StatusPill
                                                            key={status}
                                                            active={
                                                                form.data
                                                                    .status ===
                                                                status
                                                            }
                                                            label={status}
                                                            onClick={() =>
                                                                form.setData(
                                                                    'status',
                                                                    status,
                                                                )
                                                            }
                                                        />
                                                    ))}
                                                </div>
                                                {form.errors.status && (
                                                    <p className="text-sm text-destructive">
                                                        {form.errors.status}
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                    </div>

                                    <Separator className="my-6" />

                                    <div className="text-sm font-semibold">
                                        Items
                                    </div>
                                    <div className="mt-3 rounded-2xl border">
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
                                                (item, index) => {
                                                    const quantity = Number(
                                                        item.quantity || 0,
                                                    );
                                                    const price = Number(
                                                        item.unit_price || 0,
                                                    );
                                                    const discount = Number(
                                                        item.discount || 0,
                                                    );
                                                    const tax = Number(
                                                        item.tax || 0,
                                                    );
                                                    const line = Math.max(
                                                        0,
                                                        quantity * price -
                                                            discount +
                                                            tax,
                                                    );

                                                    return (
                                                        <div
                                                            key={index}
                                                            className="grid grid-cols-12 gap-2 px-4 py-3 text-sm"
                                                        >
                                                            <div className="col-span-6 min-w-0">
                                                                <div className="truncate font-medium">
                                                                    {item.description ||
                                                                        '—'}
                                                                </div>
                                                            </div>
                                                            <div className="col-span-2 text-right">
                                                                {fmt(
                                                                    quantity,
                                                                )}
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
                                </Card>

                                <Card className="rounded-2xl border bg-background p-6 shadow-sm lg:col-span-4">
                                    <SectionTitle
                                        icon={BadgePercent}
                                        title="Final totals"
                                    />
                                    <div className="mt-4 space-y-2 rounded-2xl border bg-muted/20 p-4">
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
                                            label="Quotation discount"
                                            value={computed.quotationDiscount}
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

                                    {quotationError && (
                                        <p className="mt-4 text-sm text-destructive">
                                            {quotationError}
                                        </p>
                                    )}

                                    <Button
                                        type="button"
                                        onClick={submit}
                                        disabled={
                                            form.processing || !canCreateQuotation
                                        }
                                        className="mt-4 w-full gap-2 rounded-xl transition-transform hover:scale-[1.02] active:scale-[0.98]"
                                    >
                                        Create quotation
                                        <CheckCircle2 className="h-4 w-4" />
                                    </Button>
                                </Card>
                            </motion.div>
                        )}
                    </AnimatePresence>
                </form>
            </motion.div>
        </AppLayout>
    );
}

function SectionTitle({
    icon: Icon,
    title,
}: {
    icon: React.ElementType;
    title: string;
}) {
    return (
        <div className="flex items-center gap-2">
            <Icon className="h-5 w-5 opacity-80" />
            <div className="text-sm font-semibold">{title}</div>
        </div>
    );
}

function Field({
    label,
    children,
    className,
}: {
    label: string;
    children: React.ReactNode;
    className?: string;
}) {
    return (
        <div className={cn('space-y-2', className)}>
            <Label>{label}</Label>
            {children}
        </div>
    );
}

function MiniStat({
    label,
    value,
}: {
    label: string;
    value: string;
}) {
    return (
        <div className="rounded-2xl border bg-muted/20 px-4 py-3">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-1 text-sm font-semibold">{value}</div>
        </div>
    );
}

function Row({
    label,
    value,
    precision,
    strong = false,
}: {
    label: string;
    value: number;
    precision: number;
    strong?: boolean;
}) {
    return (
        <div className="flex items-center justify-between gap-3 text-sm">
            <div className={cn('text-muted-foreground', strong && 'font-medium text-foreground')}>
                {label}
            </div>
            <div className={cn('font-medium', strong && 'text-base font-semibold')}>
                {value.toFixed(precision)}
            </div>
        </div>
    );
}

function ReviewField({
    label,
    value,
}: {
    label: string;
    value: string;
}) {
    return (
        <div className="rounded-2xl border bg-muted/20 p-4">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-1 text-sm font-semibold">{value}</div>
        </div>
    );
}

function StatusPill({
    active,
    label,
    onClick,
}: {
    active: boolean;
    label: string;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                'rounded-xl border px-3 py-2 text-sm font-medium capitalize transition',
                active
                    ? 'border-foreground bg-foreground text-background'
                    : 'border-border bg-background hover:bg-muted/40',
            )}
        >
            {label}
        </button>
    );
}

function clientLabel(clients: Client[], clientId: string) {
    return (
        clients.find((client) => String(client.id) === clientId)?.name ??
        'Not selected'
    );
}
