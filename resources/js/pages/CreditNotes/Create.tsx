import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import {
    AlertTriangle,
    ArrowLeft,
    ArrowRight,
    BadgePercent,
    CheckCircle2,
    ClipboardList,
    FileText,
    Plus,
    Receipt,
    Trash2,
} from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

import NiloSpinner from '@/components/nilo-spinner';
import RequiredHand from '@/components/required-hand';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types/index.d';

import {
    Chip,
    FormField,
    Panel,
    PanelHeader,
    PillButton,
    SoftTile,
    TotalRow,
    fieldInputClass,
    pillButtonClass,
} from '@/components/dashboard/primitives';
import { Money } from '@/components/money';

// shadcn
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';

/**
 * An invoice that can be credited. `balance_due` is what the invoice still owes
 * after payments and already-issued credits, computed server-side when this
 * page was rendered.
 */
type CreditableInvoice = {
    id: number;
    number: string | null;
    client_name?: string | null;
    currency_code: string;
    total: number;
    balance_due: number;
};

type Currency = {
    code: string;
    name: string;
    symbol?: string | null;
    precision: number;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Credit notes', href: '/credit-notes' },
    { title: 'Create', href: '/credit-notes/create' },
];

type StepKey = 'details' | 'items' | 'review';

const steps: {
    key: StepKey;
    title: string;
    description: string;
    icon: any;
}[] = [
    {
        key: 'details',
        title: 'Details',
        description: 'Invoice, reason and dates',
        icon: ClipboardList,
    },
    {
        key: 'items',
        title: 'Items',
        description: 'What is being credited',
        icon: Receipt,
    },
    {
        key: 'review',
        title: 'Review',
        description: 'Discount, status and final check',
        icon: CheckCircle2,
    },
];

type CreditNoteStatus = 'draft' | 'issued';

/**
 * A credit note is written and then applied. `void` withdraws one that already
 * exists, so it is set from the credit note itself and never at creation.
 */
const CREATABLE_STATUSES: CreditNoteStatus[] = ['draft', 'issued'];

/** What is blocking a step, and the field to point the hand at. */
type StepIssue = { field: string | null; message: string };

/** Standard VAT rate; editable per credit note on the review step. */
const DEFAULT_TAX_PERCENT = 16;

/** Today as `YYYY-MM-DD`, the format the date inputs expect. */
function todayAsDateInputValue(): string {
    return new Date().toISOString().slice(0, 10);
}

export default function CreditNotesCreate({
    invoices,
    defaultCurrencyCode,
    currencies,
    hasActiveCompany = true,
}: {
    invoices: CreditableInvoice[];
    defaultCurrencyCode: string;
    currencies?: { all: Currency[]; current: Currency | null };
    hasActiveCompany?: boolean;
}) {
    const currencyList = currencies?.all ?? [];
    const activeCurrency = currencies?.current ?? null;
    const hasInvoices = invoices.length > 0;

    /**
     * The only real prerequisite. The invoice being credited already carries a
     * client, a currency and a company, and the controller provisions the
     * template — so nothing else needs to exist first.
     */
    const canCreateCreditNote = hasActiveCompany && hasInvoices;

    const [step, setStep] = React.useState<StepKey>('details');

    /**
     * Some refusals come back as a flash rather than validation errors, so
     * without this the page would bounce the user back with no explanation.
     */
    const { flash } = usePage<{
        flash?: { error?: string | null; info?: string | null };
    }>().props;

    React.useEffect(() => {
        if (flash?.error) toast.error(flash.error);
        if (flash?.info) toast.message(flash.info);
    }, [flash?.error, flash?.info]);

    /** Which field the pointing hand is currently calling out, if any. */
    const [blockedField, setBlockedField] = React.useState<string | null>(null);

    React.useEffect(() => setBlockedField(null), [step]);

    const today = todayAsDateInputValue();

    /**
     * No `currency_code` and no template id. A credit note is denominated by the
     * invoice it credits and rendered through a template the controller
     * provisions, so neither is the form's to send.
     */
    const form = useForm({
        invoice_id: '',
        title: '',
        reference: '',
        reason: '',
        issue_date: today,

        status: 'draft' as CreditNoteStatus,

        notes: '',
        terms: '',

        /** Overall discount, set on review. */
        credit_note_discount: 0,

        /** Whole-note tax rate, applied after discounts. */
        tax_percent: DEFAULT_TAX_PERCENT,

        items: [
            {
                description: '',
                unit: '',
                quantity: 1,
                unit_price: 0,
                discount: 0,
            },
        ],
    });

    const selectedInvoice = React.useMemo(
        () =>
            invoices.find(
                (i) => String(i.id) === String(form.data.invoice_id),
            ) ?? null,
        [invoices, form.data.invoice_id],
    );

    /**
     * The credited invoice fixes the currency — never the company default, which
     * can differ. Until an invoice is picked there is nothing to fix it to, so
     * the company default stands in as a placeholder only.
     */
    const currencyCode = selectedInvoice?.currency_code ?? defaultCurrencyCode;

    const invoiceCurrency = React.useMemo(
        () =>
            currencyList.find((c) => c.code === currencyCode) ??
            activeCurrency ??
            null,
        [currencyList, currencyCode, activeCurrency],
    );

    const precision = invoiceCurrency?.precision ?? 2;

    const money = (amount: number): React.ReactNode => (
        <Money
            amount={amount}
            code={currencyCode}
            currency={invoiceCurrency ?? undefined}
        />
    );

    /**
     * Mirrors CreditNoteController::computeTotals. Prices are tax-inclusive, so
     * the tax is carved out of the gross rather than added on top of it.
     */
    const computed = React.useMemo(() => {
        const round2 = (v: number) => Math.round(v * 100) / 100;

        let itemsGross = 0;
        let lineDiscount = 0;

        for (const it of form.data.items) {
            itemsGross += Number(it.quantity || 0) * Number(it.unit_price || 0);
            lineDiscount += Number(it.discount || 0);
        }

        const creditNoteDiscount = Number(form.data.credit_note_discount || 0);
        const taxPercent = Number(form.data.tax_percent || 0);

        const total = round2(
            Math.max(0, itemsGross - lineDiscount - creditNoteDiscount),
        );
        const subtotal = round2(total / (1 + taxPercent / 100));

        return {
            itemsGross,
            lineDiscount,
            creditNoteDiscount,
            subtotal,
            taxPercent,
            tax: round2(total - subtotal),
            total,
        };
    }, [
        form.data.items,
        form.data.credit_note_discount,
        form.data.tax_percent,
    ]);

    /**
     * A credit about to be applied may not exceed what the invoice still owes.
     * The same epsilon as the server guard, so the two agree on the boundary.
     *
     * `balance_due` was computed when this page rendered. A payment landing
     * elsewhere in the meantime makes it stale, which is why the server re-checks
     * and its `items` error is surfaced below rather than trusted away here.
     */
    const balanceDue = selectedInvoice
        ? Number(selectedInvoice.balance_due ?? 0)
        : null;

    const exceedsBalance =
        balanceDue !== null && computed.total > balanceDue + 0.001;

    /** Drafts may exceed the balance; only issuing one is refused. */
    const blockedByBalance = exceedsBalance && form.data.status === 'issued';

    const addItem = () => {
        form.setData('items', [
            ...form.data.items,
            {
                description: '',
                unit: '',
                quantity: 1,
                unit_price: 0,
                discount: 0,
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
                      },
                  ],
        );
    };

    const updateItem = (idx: number, key: string, value: any) => {
        const next = [...form.data.items];
        (next[idx] as any)[key] = value;
        form.setData('items', next);
    };

    /**
     * The first thing blocking a step, and which field it belongs to, so the
     * pointing hand can be shown against that field rather than only toasted.
     * A null field means the problem is not on this form at all.
     */
    const stepIssue = (s: StepKey): StepIssue | null => {
        if (!hasActiveCompany) {
            return {
                field: null,
                message:
                    'Add or select a company before creating a credit note.',
            };
        }

        if (!hasInvoices) {
            return {
                field: null,
                message: 'There is no invoice to credit yet.',
            };
        }

        if (s === 'details') {
            if (!form.data.invoice_id) {
                return {
                    field: 'invoice_id',
                    message: 'Select the invoice being credited.',
                };
            }
            if (!form.data.issue_date) {
                return {
                    field: 'issue_date',
                    message: 'Issue date is required.',
                };
            }
        }

        if (s === 'items') {
            const hasValidItem = form.data.items.some(
                (it) =>
                    (it.description || '').trim().length > 0 &&
                    Number(it.quantity) > 0 &&
                    Number(it.unit_price) >= 0,
            );

            if (!hasValidItem) {
                return {
                    field: 'item_description',
                    message: 'Add at least one valid line item.',
                };
            }
        }

        if (s === 'review') {
            if (Number(form.data.credit_note_discount || 0) < 0) {
                return {
                    field: 'credit_note_discount',
                    message: 'A credit note discount cannot be negative.',
                };
            }

            if (blockedByBalance) {
                return {
                    field: 'status',
                    message:
                        'An issued credit cannot be more than the invoice still owes. Save it as a draft, or lower the amount.',
                };
            }
        }

        return null;
    };

    /**
     * Drop the hand the moment the field it is pointing at stops being the
     * problem, rather than making the user click Next again to find out.
     */
    React.useEffect(() => {
        if (!blockedField) {
            return;
        }

        if (stepIssue(step)?.field !== blockedField) {
            setBlockedField(null);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [form.data, blockedByBalance, blockedField, step]);

    const validateStep = (s: StepKey) => {
        const issue = stepIssue(s);

        setBlockedField(issue?.field ?? null);

        if (!issue) {
            return true;
        }

        toast.error(issue.message);

        if (issue.field) {
            /** Let the hand render before scrolling it into view. */
            window.requestAnimationFrame(() => {
                const el = document.getElementById(`field-${issue.field}`);
                el?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                el?.focus({ preventScroll: true });
            });
        }

        return false;
    };

    const order: StepKey[] = ['details', 'items', 'review'];

    const goNext = () => {
        if (!validateStep(step)) return;
        setStep(order[Math.min(order.indexOf(step) + 1, order.length - 1)]);
    };

    const goBack = () => {
        setStep(order[Math.max(order.indexOf(step) - 1, 0)]);
    };

    const submit = () => {
        if (step !== 'review') return toast.error('Finish review first.');
        if (!validateStep('review')) return;

        form.post('/credit-notes', {
            preserveScroll: true,
            /**
             * Success navigates away to the new credit note, so this only matters
             * when the server refuses: keep the wizard where it was instead of
             * remounting back to step one and losing everything typed.
             */
            preserveState: true,
            /**
             * `items` is where the server puts an over-the-balance refusal. It is
             * reachable even with the client-side guard above, because the
             * balance this page rendered with may be stale — so it is toasted
             * here and rendered inline on the review step.
             */
            onError: (errors) =>
                toast.error(
                    errors?.company_id ||
                        errors?.invoice_id ||
                        errors?.status ||
                        errors?.credit_note_discount ||
                        errors?.items ||
                        errors?.creditNote ||
                        'Failed to create credit note.',
                ),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create credit note" />

            <div className="mx-auto w-full py-3">
                {!canCreateCreditNote && (
                    <Panel className="mb-4">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <div className="text-sm font-semibold">
                                    {hasActiveCompany
                                        ? 'There is no invoice to credit yet'
                                        : 'Add or select a company before creating credit notes'}
                                </div>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {hasActiveCompany
                                        ? 'A credit note is always raised against an invoice. Create and issue an invoice first, then come back here to credit it.'
                                        : 'Credit notes are available, but you need an active company before invoices and credits can be managed.'}
                                </p>
                            </div>

                            <div className="flex flex-col gap-2 sm:flex-row">
                                <Link
                                    href={
                                        hasActiveCompany
                                            ? '/invoices/create'
                                            : '/companies'
                                    }
                                    className={pillButtonClass('solid', 'sm')}
                                >
                                    {hasActiveCompany
                                        ? 'Create invoice'
                                        : 'Manage companies'}
                                </Link>
                            </div>
                        </div>
                    </Panel>
                )}

                <form
                    onSubmit={(e) => {
                        e.preventDefault();

                        /**
                         * Only act on this form's own submit. Anything portalled
                         * out of the DOM still bubbles through React, so a nested
                         * form must not create the credit note.
                         */
                        if (e.target !== e.currentTarget) {
                            return;
                        }

                        step === 'review' ? submit() : goNext();
                    }}
                >
                    <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
                        {/* ============ The credit note ============ */}
                        <div className="lg:col-span-8">
                            <AnimatePresence mode="wait">
                                {step === 'details' && (
                                    <motion.div
                                        key="details"
                                        initial={{ opacity: 0, y: 10 }}
                                        animate={{ opacity: 1, y: 0 }}
                                        exit={{ opacity: 0, y: 10 }}
                                        transition={{ duration: 0.22 }}
                                    >
                                        <Panel>
                                            <SectionTitle
                                                icon={ClipboardList}
                                                title="Credit note details"
                                            />

                                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                <FormField
                                                    label="Invoice *"
                                                    className="sm:col-span-2"
                                                >
                                                    <RequiredHand
                                                        show={
                                                            blockedField ===
                                                            'invoice_id'
                                                        }
                                                        message="Pick the invoice this credit is raised against."
                                                    />
                                                    <Select
                                                        value={
                                                            form.data.invoice_id
                                                        }
                                                        disabled={!hasInvoices}
                                                        onValueChange={(v) =>
                                                            form.setData(
                                                                'invoice_id',
                                                                v,
                                                            )
                                                        }
                                                    >
                                                        <SelectTrigger
                                                            id="field-invoice_id"
                                                            className={
                                                                fieldInputClass
                                                            }
                                                        >
                                                            <SelectValue placeholder="Select invoice" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {hasInvoices ? (
                                                                invoices.map(
                                                                    (i) => (
                                                                        <SelectItem
                                                                            key={
                                                                                i.id
                                                                            }
                                                                            value={String(
                                                                                i.id,
                                                                            )}
                                                                        >
                                                                            {i.number ??
                                                                                `Invoice #${i.id}`}{' '}
                                                                            —{' '}
                                                                            {i.client_name ??
                                                                                'No client'}{' '}
                                                                            (
                                                                            {
                                                                                i.currency_code
                                                                            }{' '}
                                                                            {Number(
                                                                                i.balance_due ??
                                                                                    0,
                                                                            ).toFixed(
                                                                                2,
                                                                            )}{' '}
                                                                            due)
                                                                        </SelectItem>
                                                                    ),
                                                                )
                                                            ) : (
                                                                <div className="px-2 py-3 text-sm text-muted-foreground">
                                                                    No invoices
                                                                    to credit
                                                                    yet.
                                                                </div>
                                                            )}
                                                        </SelectContent>
                                                    </Select>
                                                    {form.errors.invoice_id && (
                                                        <p className="mt-1 text-sm text-destructive">
                                                            {
                                                                form.errors
                                                                    .invoice_id
                                                            }
                                                        </p>
                                                    )}
                                                </FormField>

                                                {/*
                                                 * Both are decided by the invoice,
                                                 * so they are shown, not asked for.
                                                 */}
                                                <ReadOnlyField
                                                    label="Client"
                                                    value={
                                                        selectedInvoice?.client_name ??
                                                        'Set by the invoice'
                                                    }
                                                />
                                                <ReadOnlyField
                                                    label="Currency"
                                                    value={
                                                        selectedInvoice
                                                            ? selectedInvoice.currency_code
                                                            : 'Set by the invoice'
                                                    }
                                                />

                                                <FormField label="Issue date *">
                                                    <RequiredHand
                                                        show={
                                                            blockedField ===
                                                            'issue_date'
                                                        }
                                                        message="An issue date is required."
                                                    />
                                                    <Input
                                                        id="field-issue_date"
                                                        className={
                                                            fieldInputClass
                                                        }
                                                        type="date"
                                                        value={
                                                            form.data.issue_date
                                                        }
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'issue_date',
                                                                e.target.value,
                                                            )
                                                        }
                                                        required
                                                    />
                                                </FormField>

                                                <FormField label="Reason">
                                                    <Input
                                                        id="field-reason"
                                                        className={
                                                            fieldInputClass
                                                        }
                                                        maxLength={190}
                                                        value={form.data.reason}
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'reason',
                                                                e.target.value,
                                                            )
                                                        }
                                                    />
                                                    {form.errors.reason && (
                                                        <p className="mt-1 text-sm text-destructive">
                                                            {form.errors.reason}
                                                        </p>
                                                    )}
                                                </FormField>

                                                <FormField label="Title">
                                                    <Input
                                                        className={
                                                            fieldInputClass
                                                        }
                                                        value={form.data.title}
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'title',
                                                                e.target.value,
                                                            )
                                                        }
                                                    />
                                                </FormField>

                                                <FormField label="Reference">
                                                    <Input
                                                        className={
                                                            fieldInputClass
                                                        }
                                                        value={
                                                            form.data.reference
                                                        }
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'reference',
                                                                e.target.value,
                                                            )
                                                        }
                                                    />
                                                </FormField>
                                            </div>

                                            <div className="mt-4 grid grid-cols-1 gap-4">
                                                <FormField label="Notes">
                                                    <Textarea
                                                        className={cn(
                                                            fieldInputClass,
                                                            'h-auto min-h-24 py-2',
                                                        )}
                                                        value={form.data.notes}
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'notes',
                                                                e.target.value,
                                                            )
                                                        }
                                                    />
                                                </FormField>
                                                <FormField label="Terms">
                                                    <Textarea
                                                        className={cn(
                                                            fieldInputClass,
                                                            'h-auto min-h-24 py-2',
                                                        )}
                                                        value={form.data.terms}
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'terms',
                                                                e.target.value,
                                                            )
                                                        }
                                                    />
                                                </FormField>
                                            </div>
                                        </Panel>
                                    </motion.div>
                                )}

                                {step === 'items' && (
                                    <motion.div
                                        key="items"
                                        initial={{ opacity: 0, y: 10 }}
                                        animate={{ opacity: 1, y: 0 }}
                                        exit={{ opacity: 0, y: 10 }}
                                        transition={{ duration: 0.22 }}
                                    >
                                        <Panel>
                                            <div className="flex items-center justify-between gap-3">
                                                <SectionTitle
                                                    icon={Receipt}
                                                    title="Line items"
                                                />
                                                <PillButton
                                                    variant="soft"
                                                    size="sm"
                                                    onClick={addItem}
                                                >
                                                    <Plus className="h-4 w-4" />
                                                    Add item
                                                </PillButton>
                                            </div>

                                            <div className="space-y-3">
                                                {form.data.items.map(
                                                    (it, idx) => (
                                                        <motion.div
                                                            key={idx}
                                                            initial={{
                                                                opacity: 0,
                                                                y: 10,
                                                            }}
                                                            animate={{
                                                                opacity: 1,
                                                                y: 0,
                                                            }}
                                                            transition={{
                                                                duration: 0.18,
                                                            }}
                                                            className="rounded-2xl bg-muted/50 p-4 dark:bg-white/5"
                                                        >
                                                            <div className="flex items-start justify-between gap-2">
                                                                <div className="text-sm font-semibold">
                                                                    Item{' '}
                                                                    {idx + 1}
                                                                </div>
                                                                <PillButton
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() =>
                                                                        removeItem(
                                                                            idx,
                                                                        )
                                                                    }
                                                                    aria-label={`Remove item ${idx + 1}`}
                                                                >
                                                                    <Trash2 className="h-4 w-4" />
                                                                </PillButton>
                                                            </div>

                                                            <div className="mt-3 space-y-3">
                                                                <FormField label="Description *">
                                                                    <RequiredHand
                                                                        show={
                                                                            blockedField ===
                                                                                'item_description' &&
                                                                            idx ===
                                                                                0
                                                                        }
                                                                        message="Describe what is being credited."
                                                                    />
                                                                    <Input
                                                                        id={
                                                                            idx ===
                                                                            0
                                                                                ? 'field-item_description'
                                                                                : undefined
                                                                        }
                                                                        className={
                                                                            fieldInputClass
                                                                        }
                                                                        value={
                                                                            it.description
                                                                        }
                                                                        onChange={(
                                                                            e,
                                                                        ) =>
                                                                            updateItem(
                                                                                idx,
                                                                                'description',
                                                                                e
                                                                                    .target
                                                                                    .value,
                                                                            )
                                                                        }
                                                                        required
                                                                    />
                                                                </FormField>

                                                                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                                                    <FormField label="Unit">
                                                                        <Input
                                                                            className={
                                                                                fieldInputClass
                                                                            }
                                                                            value={
                                                                                it.unit
                                                                            }
                                                                            onChange={(
                                                                                e,
                                                                            ) =>
                                                                                updateItem(
                                                                                    idx,
                                                                                    'unit',
                                                                                    e
                                                                                        .target
                                                                                        .value,
                                                                                )
                                                                            }
                                                                        />
                                                                    </FormField>

                                                                    <FormField label="Qty *">
                                                                        <Input
                                                                            className={
                                                                                fieldInputClass
                                                                            }
                                                                            type="number"
                                                                            min={
                                                                                0.01
                                                                            }
                                                                            step={
                                                                                0.01
                                                                            }
                                                                            value={
                                                                                it.quantity
                                                                            }
                                                                            onChange={(
                                                                                e,
                                                                            ) =>
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
                                                                        />
                                                                    </FormField>

                                                                    <FormField label="Unit price *">
                                                                        <Input
                                                                            className={
                                                                                fieldInputClass
                                                                            }
                                                                            type="number"
                                                                            min={
                                                                                0
                                                                            }
                                                                            step={
                                                                                0.01
                                                                            }
                                                                            value={
                                                                                it.unit_price
                                                                            }
                                                                            onChange={(
                                                                                e,
                                                                            ) =>
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
                                                                        />
                                                                    </FormField>

                                                                    <FormField label="Discount">
                                                                        <Input
                                                                            className={
                                                                                fieldInputClass
                                                                            }
                                                                            type="number"
                                                                            min={
                                                                                0
                                                                            }
                                                                            step={
                                                                                0.01
                                                                            }
                                                                            value={
                                                                                it.discount
                                                                            }
                                                                            onChange={(
                                                                                e,
                                                                            ) =>
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
                                                                        />
                                                                    </FormField>
                                                                </div>
                                                            </div>
                                                        </motion.div>
                                                    ),
                                                )}
                                            </div>
                                        </Panel>
                                    </motion.div>
                                )}

                                {step === 'review' && (
                                    <motion.div
                                        key="review"
                                        initial={{ opacity: 0, y: 10 }}
                                        animate={{ opacity: 1, y: 0 }}
                                        exit={{ opacity: 0, y: 10 }}
                                        transition={{ duration: 0.22 }}
                                    >
                                        <Panel>
                                            <SectionTitle
                                                icon={CheckCircle2}
                                                title="Review"
                                            />

                                            <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                                <ReviewField
                                                    label="Invoice"
                                                    value={
                                                        selectedInvoice?.number ??
                                                        '—'
                                                    }
                                                />
                                                <ReviewField
                                                    label="Client"
                                                    value={
                                                        selectedInvoice?.client_name ??
                                                        '—'
                                                    }
                                                />
                                                <ReviewField
                                                    label="Issue date"
                                                    value={
                                                        form.data.issue_date ||
                                                        '—'
                                                    }
                                                />
                                                <ReviewField
                                                    label="Currency"
                                                    value={
                                                        selectedInvoice?.currency_code ??
                                                        '—'
                                                    }
                                                />
                                                <ReviewField
                                                    label="Reason"
                                                    value={
                                                        form.data.reason || '—'
                                                    }
                                                />
                                                <ReviewField
                                                    label="Reference"
                                                    value={
                                                        form.data.reference ||
                                                        '—'
                                                    }
                                                />
                                                <ReviewField
                                                    label="Tax rate"
                                                    value={`${computed.taxPercent}%`}
                                                />
                                                <ReviewField
                                                    label="Outstanding on invoice"
                                                    value={
                                                        balanceDue === null
                                                            ? '—'
                                                            : `${currencyCode} ${balanceDue.toFixed(precision)}`
                                                    }
                                                />
                                            </div>

                                            <div className="my-5 h-px bg-border/70 dark:bg-white/10" />

                                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                <FormField label="Overall discount">
                                                    <RequiredHand
                                                        show={
                                                            blockedField ===
                                                            'credit_note_discount'
                                                        }
                                                        message="A discount cannot be negative."
                                                    />
                                                    <Input
                                                        id="field-credit_note_discount"
                                                        className={
                                                            fieldInputClass
                                                        }
                                                        type="number"
                                                        min={0}
                                                        step={0.01}
                                                        value={
                                                            form.data
                                                                .credit_note_discount
                                                        }
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'credit_note_discount',
                                                                Number(
                                                                    e.target
                                                                        .value,
                                                                ),
                                                            )
                                                        }
                                                    />
                                                    {form.errors
                                                        .credit_note_discount && (
                                                        <p className="mt-1 text-sm text-destructive">
                                                            {
                                                                form.errors
                                                                    .credit_note_discount
                                                            }
                                                        </p>
                                                    )}
                                                </FormField>

                                                <FormField label="Tax rate (%)">
                                                    <Input
                                                        className={
                                                            fieldInputClass
                                                        }
                                                        type="number"
                                                        min={0}
                                                        max={100}
                                                        step={0.01}
                                                        value={
                                                            form.data
                                                                .tax_percent
                                                        }
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'tax_percent',
                                                                Number(
                                                                    e.target
                                                                        .value,
                                                                ),
                                                            )
                                                        }
                                                    />
                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                        Item prices already
                                                        include tax; this rate
                                                        carves it out.
                                                    </p>
                                                    {form.errors
                                                        .tax_percent && (
                                                        <p className="mt-1 text-sm text-destructive">
                                                            {
                                                                form.errors
                                                                    .tax_percent
                                                            }
                                                        </p>
                                                    )}
                                                </FormField>

                                                <FormField
                                                    label="Status"
                                                    className="sm:col-span-2"
                                                >
                                                    <RequiredHand
                                                        show={
                                                            blockedField ===
                                                            'status'
                                                        }
                                                        message="An issued credit cannot exceed what the invoice still owes."
                                                    />
                                                    <div
                                                        id="field-status"
                                                        tabIndex={-1}
                                                        className="flex gap-2 outline-none"
                                                    >
                                                        {CREATABLE_STATUSES.map(
                                                            (value) => (
                                                                <Chip
                                                                    key={value}
                                                                    active={
                                                                        form
                                                                            .data
                                                                            .status ===
                                                                        value
                                                                    }
                                                                    onClick={() =>
                                                                        form.setData(
                                                                            'status',
                                                                            value,
                                                                        )
                                                                    }
                                                                >
                                                                    {value}
                                                                </Chip>
                                                            ),
                                                        )}
                                                    </div>
                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                        A draft moves nothing.
                                                        Issuing reduces what the
                                                        invoice still owes. Void
                                                        is set later, from the
                                                        credit note itself.
                                                    </p>
                                                    {form.errors.status && (
                                                        <p className="text-sm text-destructive">
                                                            {form.errors.status}
                                                        </p>
                                                    )}
                                                </FormField>
                                            </div>

                                            {/*
                                             * The server keys its over-the-balance
                                             * refusal on `items`, and it can arrive
                                             * even when the guard above was happy —
                                             * the balance this page rendered with
                                             * may already be out of date.
                                             */}
                                            {form.errors.items && (
                                                <p className="mt-4 text-sm text-destructive">
                                                    {form.errors.items}
                                                </p>
                                            )}

                                            <div className="my-5 h-px bg-border/70 dark:bg-white/10" />

                                            <div className="text-sm font-semibold">
                                                Items
                                            </div>
                                            <div className="-mx-1 mt-3 overflow-x-auto px-1">
                                                <table className="w-full min-w-[30rem] border-separate border-spacing-y-1.5 text-sm">
                                                    <thead>
                                                        <tr className="text-left text-xs text-muted-foreground">
                                                            <th className="px-3 pb-1 font-medium">
                                                                Description
                                                            </th>
                                                            <th className="px-3 pb-1 text-right font-medium">
                                                                Qty
                                                            </th>
                                                            <th className="px-3 pb-1 text-right font-medium">
                                                                Price
                                                            </th>
                                                            <th className="px-3 pb-1 text-right font-medium">
                                                                Line
                                                            </th>
                                                        </tr>
                                                    </thead>

                                                    <tbody>
                                                        {form.data.items.map(
                                                            (it, idx) => {
                                                                const qty =
                                                                    Number(
                                                                        it.quantity ||
                                                                            0,
                                                                    );
                                                                const price =
                                                                    Number(
                                                                        it.unit_price ||
                                                                            0,
                                                                    );
                                                                const disc =
                                                                    Number(
                                                                        it.discount ||
                                                                            0,
                                                                    );
                                                                const line =
                                                                    Math.max(
                                                                        0,
                                                                        qty *
                                                                            price -
                                                                            disc,
                                                                    );

                                                                return (
                                                                    <tr
                                                                        key={
                                                                            idx
                                                                        }
                                                                        className="group"
                                                                    >
                                                                        <td
                                                                            className={cn(
                                                                                reviewCellClass,
                                                                                'rounded-l-2xl',
                                                                            )}
                                                                        >
                                                                            <div className="truncate font-medium">
                                                                                {it.description ||
                                                                                    '—'}
                                                                            </div>
                                                                        </td>
                                                                        <td
                                                                            className={cn(
                                                                                reviewCellClass,
                                                                                'text-right tabular-nums',
                                                                            )}
                                                                        >
                                                                            {qty.toFixed(
                                                                                precision,
                                                                            )}
                                                                        </td>
                                                                        <td
                                                                            className={cn(
                                                                                reviewCellClass,
                                                                                'text-right tabular-nums',
                                                                            )}
                                                                        >
                                                                            {money(
                                                                                price,
                                                                            )}
                                                                        </td>
                                                                        <td
                                                                            className={cn(
                                                                                reviewCellClass,
                                                                                'rounded-r-2xl text-right font-semibold tabular-nums',
                                                                            )}
                                                                        >
                                                                            {money(
                                                                                line,
                                                                            )}
                                                                        </td>
                                                                    </tr>
                                                                );
                                                            },
                                                        )}
                                                    </tbody>
                                                </table>
                                            </div>
                                        </Panel>
                                    </motion.div>
                                )}
                            </AnimatePresence>
                        </div>

                        {/* ============ Sidebar ============ */}
                        <div className="lg:col-span-4">
                            <div className="flex flex-col gap-4 lg:sticky lg:top-4 lg:max-h-[calc(100vh-2rem)] lg:overflow-y-auto lg:pb-1">
                                {/* Navigation */}
                                <Panel>
                                    <div className="flex items-center gap-2">
                                        <PillButton
                                            variant="ghost"
                                            size="sm"
                                            onClick={goBack}
                                            disabled={
                                                step === 'details' ||
                                                form.processing
                                            }
                                            className="flex-1"
                                        >
                                            <ArrowLeft className="h-4 w-4" />
                                            Back
                                        </PillButton>

                                        {step !== 'review' ? (
                                            <PillButton
                                                size="sm"
                                                onClick={goNext}
                                                disabled={
                                                    form.processing ||
                                                    !canCreateCreditNote
                                                }
                                                className="flex-1"
                                            >
                                                Next
                                                <ArrowRight className="h-4 w-4" />
                                            </PillButton>
                                        ) : (
                                            <PillButton
                                                size="sm"
                                                onClick={submit}
                                                disabled={
                                                    form.processing ||
                                                    !canCreateCreditNote ||
                                                    blockedByBalance
                                                }
                                                className="flex-1"
                                            >
                                                {form.processing ? (
                                                    <>
                                                        <NiloSpinner
                                                            size={16}
                                                        />
                                                        Creating…
                                                    </>
                                                ) : (
                                                    <>
                                                        Create credit note
                                                        <CheckCircle2 className="h-4 w-4" />
                                                    </>
                                                )}
                                            </PillButton>
                                        )}
                                    </div>

                                    {/*
                                     * A disabled button has to say why, or it
                                     * reads as broken rather than refused.
                                     */}
                                    {blockedByBalance && (
                                        <p className="mt-2 flex items-start gap-1.5 text-xs text-destructive">
                                            <AlertTriangle className="mt-0.5 h-3.5 w-3.5 shrink-0" />
                                            <span>
                                                This credit is more than the{' '}
                                                {currencyCode}{' '}
                                                {(balanceDue ?? 0).toFixed(
                                                    precision,
                                                )}{' '}
                                                still outstanding. Save it as a
                                                draft, or lower the amount.
                                            </span>
                                        </p>
                                    )}
                                </Panel>

                                {/* Steps */}
                                <Panel>
                                    <PanelHeader
                                        icon={ClipboardList}
                                        title="Steps"
                                        subtitle={`Step ${order.indexOf(step) + 1} of ${order.length}`}
                                    />

                                    <div className="flex flex-col gap-2">
                                        {steps.map((s) => {
                                            const Icon = s.icon;
                                            const active = s.key === step;
                                            const cur = order.indexOf(step);
                                            const target = order.indexOf(s.key);
                                            const done = target < cur;

                                            return (
                                                <button
                                                    key={s.key}
                                                    type="button"
                                                    onClick={() => {
                                                        // back freely
                                                        if (target <= cur)
                                                            return setStep(
                                                                s.key,
                                                            );
                                                        // forward requires current step valid
                                                        if (!validateStep(step))
                                                            return;
                                                        setStep(s.key);
                                                    }}
                                                    className={cn(
                                                        'rounded-2xl p-3 text-left transition',
                                                        active
                                                            ? 'bg-brand-50 dark:bg-brand-500/15'
                                                            : 'bg-muted/50 hover:bg-muted dark:bg-white/5 dark:hover:bg-white/10',
                                                    )}
                                                >
                                                    <div className="flex items-center gap-3">
                                                        <span
                                                            className={cn(
                                                                'grid h-8 w-8 shrink-0 place-items-center rounded-xl',
                                                                done || active
                                                                    ? 'bg-brand text-brand-foreground'
                                                                    : 'bg-muted text-muted-foreground dark:bg-white/10',
                                                            )}
                                                        >
                                                            {done ? (
                                                                <CheckCircle2 className="h-4 w-4" />
                                                            ) : (
                                                                <Icon className="h-4 w-4" />
                                                            )}
                                                        </span>

                                                        <div className="min-w-0">
                                                            <div
                                                                className={cn(
                                                                    'text-sm font-semibold',
                                                                    active &&
                                                                        'text-brand-700 dark:text-brand-200',
                                                                )}
                                                            >
                                                                {s.title}
                                                            </div>
                                                            <div className="truncate text-xs text-muted-foreground">
                                                                {s.description}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </button>
                                            );
                                        })}
                                    </div>
                                </Panel>

                                {/* Totals */}
                                <Panel>
                                    <PanelHeader
                                        icon={BadgePercent}
                                        title="Totals"
                                        subtitle={
                                            selectedInvoice
                                                ? `${currencyCode} • from invoice ${selectedInvoice.number ?? `#${selectedInvoice.id}`}`
                                                : 'Pick an invoice to fix the currency'
                                        }
                                    />

                                    <SoftTile className="space-y-2 p-4">
                                        <Row
                                            label="Items (incl. tax)"
                                            value={computed.itemsGross}
                                            money={money}
                                        />
                                        <Row
                                            label="Line discount"
                                            value={computed.lineDiscount}
                                            money={money}
                                        />
                                        <Row
                                            label="Credit note discount"
                                            value={computed.creditNoteDiscount}
                                            money={money}
                                        />

                                        <div className="my-2 h-px bg-border/70 dark:bg-white/10" />

                                        <Row
                                            label="Subtotal (excl. tax)"
                                            value={computed.subtotal}
                                            money={money}
                                        />
                                        <Row
                                            label={`Tax (${computed.taxPercent}%)`}
                                            value={computed.tax}
                                            money={money}
                                        />

                                        <div className="my-2 h-px bg-border/70 dark:bg-white/10" />

                                        <Row
                                            label="Total credited"
                                            value={computed.total}
                                            money={money}
                                            strong
                                        />
                                    </SoftTile>

                                    {/*
                                     * The ceiling the running total is measured
                                     * against, sat right beside it.
                                     */}
                                    <SoftTile
                                        className={cn(
                                            'mt-3 space-y-2 p-4',
                                            exceedsBalance &&
                                                'bg-destructive/10 dark:bg-destructive/15',
                                        )}
                                    >
                                        <TotalRow
                                            label="Outstanding on invoice"
                                            value={
                                                balanceDue === null
                                                    ? '—'
                                                    : money(balanceDue)
                                            }
                                        />
                                        <TotalRow
                                            label="Remaining after credit"
                                            value={
                                                balanceDue === null
                                                    ? '—'
                                                    : money(
                                                          balanceDue -
                                                              computed.total,
                                                      )
                                            }
                                            strong
                                        />

                                        {selectedInvoice ? (
                                            <Link
                                                href={`/invoices/${selectedInvoice.id}`}
                                                className={cn(
                                                    pillButtonClass(
                                                        'ghost',
                                                        'sm',
                                                    ),
                                                    'mt-1 w-full',
                                                )}
                                            >
                                                <FileText className="h-4 w-4" />
                                                Open invoice
                                            </Link>
                                        ) : null}
                                    </SoftTile>
                                </Panel>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}

/* ---------- UI helpers ---------- */

/** Rows read as tinted tiles rather than ruled lines, matching the panels. */
const reviewCellClass = cn(
    'bg-muted/40 px-3 py-3 align-middle transition dark:bg-white/5',
    'group-hover:bg-brand-50/70 dark:group-hover:bg-brand-500/10',
);

/**
 * The wizard's label/value shapes. Each is a thin arrangement over a shared
 * primitive, so the builder wears the same surfaces as the rest of the app.
 */
function SectionTitle({ icon: Icon, title }: { icon: any; title: string }) {
    return <PanelHeader icon={Icon} title={title} />;
}

function Row({
    label,
    value,
    money,
    strong,
}: {
    label: string;
    value: number;
    money: (amount: number) => React.ReactNode;
    strong?: boolean;
}) {
    return (
        <TotalRow
            label={label}
            value={money(Number.isFinite(value) ? value : 0)}
            strong={strong}
        />
    );
}

function ReviewField({ label, value }: { label: string; value: string }) {
    return (
        <SoftTile>
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-1 text-sm font-semibold">{value}</div>
        </SoftTile>
    );
}

/**
 * A value the invoice decides. Rendered as a tile rather than a disabled input,
 * so it never reads as something the user failed to fill in.
 */
function ReadOnlyField({ label, value }: { label: string; value: string }) {
    return (
        <FormField label={label}>
            <SoftTile className="flex h-9 items-center px-3">
                <span className="truncate text-sm font-medium">{value}</span>
            </SoftTile>
        </FormField>
    );
}
