import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import {
    ArrowLeft,
    ArrowRight,
    BadgePercent,
    CheckCircle2,
    ClipboardList,
    Factory,
    Plus,
    Receipt,
    Trash2,
    type LucideIcon,
} from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

import ContactDialog, {
    NEW_CONTACT_VALUE,
    NewContactButton,
    NewContactOption,
    useNewContactDialog,
} from '@/components/contact-dialog';
import LimitNoticeDialog, {
    type LimitNotice,
} from '@/components/limit-notice-dialog';
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
 * A vendor the company can order from. `address` is where the goods come from,
 * so it is deliberately not used to seed the delivery address below — that is
 * where they are going, which is usually the company's own premises.
 */
type Supplier = {
    id: number;
    name: string;
    email?: string | null;
    contact_person?: string | null;
    address?: string | null;
};

type Currency = {
    code: string;
    name: string;
    symbol?: string | null;
    precision: number;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Purchase orders', href: '/purchase-orders' },
    { title: 'Create', href: '/purchase-orders/create' },
];

type StepKey = 'details' | 'items' | 'review';

const steps: {
    key: StepKey;
    title: string;
    description: string;
    icon: LucideIcon;
}[] = [
    {
        key: 'details',
        title: 'Details',
        description: 'Supplier, dates, currency and delivery',
        icon: ClipboardList,
    },
    {
        key: 'items',
        title: 'Items',
        description: 'What is being ordered',
        icon: Receipt,
    },
    {
        key: 'review',
        title: 'Review',
        description: 'Discount, status and final check',
        icon: CheckCircle2,
    },
];

/** What is blocking a step, and the field to point the hand at. */
type StepIssue = { field: string | null; message: string };

/** Standard VAT rate; editable per order on the review step. */
const DEFAULT_TAX_PERCENT = 16;

/** Today as `YYYY-MM-DD`, the format the date inputs expect. */
function todayAsDateInputValue(): string {
    return new Date().toISOString().slice(0, 10);
}

/**
 * There is no unsaved-draft preview here, unlike the quotation form. A purchase
 * order is only rendered once it exists — `/purchase-orders/{id}/preview` — so
 * the wizard offers no preview button rather than one that would 404.
 */
export default function PurchaseOrdersCreate({
    suppliers,
    defaultCurrencyCode,
    statuses,
    currencies,
    hasActiveCompany = true,
    limitNotice = null,
}: {
    suppliers: Supplier[];
    defaultCurrencyCode: string;
    statuses: string[];
    currencies?: { all: Currency[]; current: Currency | null };
    hasActiveCompany?: boolean;
    limitNotice?: LimitNotice | null;
}) {
    const currencyList = currencies?.all ?? [];
    const activeCurrency = currencies?.current ?? null;
    const hasSuppliers = suppliers.length > 0;

    /** Lets a missing supplier be added without abandoning the order. */
    const newSupplier = useNewContactDialog();
    const hasCurrencies = currencyList.length > 0;

    /**
     * The two things that must exist first. Unlike a quotation there is no
     * template to pick — the controller provisions one — so a supplier and an
     * active currency are the whole prerequisite.
     */
    const canCreatePurchaseOrder =
        hasActiveCompany && hasSuppliers && hasCurrencies;

    const initialCurrencyCode =
        currencyList.find((currency) => currency.code === defaultCurrencyCode)
            ?.code ??
        activeCurrency?.code ??
        currencyList[0]?.code ??
        '';

    const [step, setStep] = React.useState<StepKey>('details');

    /**
     * Some refusals come back as a flash rather than validation errors — the
     * subscription limit is one — so without this the page would bounce the
     * user back with no explanation at all.
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

    const form = useForm({
        supplier_id: '',
        title: '',
        reference: '',
        issue_date: today,

        /** Optional: an order can be placed before a delivery date is agreed. */
        expected_date: '',

        currency_code: initialCurrencyCode,
        delivery_address: '',

        status: (statuses[0] ?? 'draft') as string,

        notes: '',
        terms: '',

        /** Overall discount, set on review. */
        purchase_order_discount: 0,

        /** Whole-order tax rate, applied after discounts. */
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

    const selectedSupplier = React.useMemo(
        () =>
            suppliers.find(
                (s) => String(s.id) === String(form.data.supplier_id),
            ) ?? null,
        [suppliers, form.data.supplier_id],
    );

    const selectedCurrency = React.useMemo(
        () =>
            currencyList.find((c) => c.code === form.data.currency_code) ??
            activeCurrency ??
            null,
        [currencyList, form.data.currency_code, activeCurrency],
    );

    const precision = selectedCurrency?.precision ?? 2;

    const money = (amount: number): React.ReactNode => (
        <Money
            amount={amount}
            code={form.data.currency_code || undefined}
            currency={selectedCurrency ?? undefined}
        />
    );

    /**
     * Mirrors PurchaseOrderController::computeTotals. Prices are tax-inclusive,
     * so the tax is carved out of the gross rather than added on top of it.
     */
    const computed = React.useMemo(() => {
        const round2 = (v: number) => Math.round(v * 100) / 100;

        let itemsGross = 0;
        let lineDiscount = 0;

        for (const it of form.data.items) {
            itemsGross += Number(it.quantity || 0) * Number(it.unit_price || 0);
            lineDiscount += Number(it.discount || 0);
        }

        const orderDiscount = Number(form.data.purchase_order_discount || 0);
        const taxPercent = Number(form.data.tax_percent || 0);

        const total = round2(
            Math.max(0, itemsGross - lineDiscount - orderDiscount),
        );
        const subtotal = round2(total / (1 + taxPercent / 100));

        return {
            itemsGross,
            lineDiscount,
            orderDiscount,
            subtotal,
            taxPercent,
            tax: round2(total - subtotal),
            total,
        };
    }, [
        form.data.items,
        form.data.purchase_order_discount,
        form.data.tax_percent,
    ]);

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

    const updateItem = <K extends keyof (typeof form.data.items)[number]>(
        idx: number,
        key: K,
        value: (typeof form.data.items)[number][K],
    ) => {
        const next = [...form.data.items];
        next[idx][key] = value;
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
                    'Add or select a company before raising a purchase order.',
            };
        }

        if (!hasSuppliers) {
            return {
                field: null,
                message: 'Add a supplier before raising a purchase order.',
            };
        }

        if (!hasCurrencies) {
            return {
                field: null,
                message:
                    'Add an active currency before raising a purchase order.',
            };
        }

        if (s === 'details') {
            if (!form.data.supplier_id) {
                return { field: 'supplier_id', message: 'Select a supplier.' };
            }
            if (!form.data.issue_date) {
                return {
                    field: 'issue_date',
                    message: 'Issue date is required.',
                };
            }
            if (!form.data.currency_code) {
                return {
                    field: 'currency_code',
                    message: 'Currency is required.',
                };
            }
            if (
                form.data.expected_date &&
                form.data.expected_date < form.data.issue_date
            ) {
                return {
                    field: 'expected_date',
                    message: 'Expected cannot be before the issue date.',
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
            if (Number(form.data.purchase_order_discount || 0) < 0) {
                return {
                    field: 'purchase_order_discount',
                    message: 'A purchase order discount cannot be negative.',
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
    }, [form.data, blockedField, step]);

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

        form.post('/purchase-orders', {
            preserveScroll: true,
            /**
             * Success navigates away to the new order, so this only matters
             * when the server refuses: keep the wizard where it was instead of
             * remounting back to step one and losing everything typed.
             */
            preserveState: true,
            onError: (errors) =>
                toast.error(
                    errors?.supplier_id ||
                        errors?.currency_code ||
                        errors?.expected_date ||
                        errors?.status ||
                        errors?.purchase_order_discount ||
                        errors?.items ||
                        errors?.purchaseOrder ||
                        'Failed to create purchase order.',
                ),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create purchase order" />

            {/* Plan refusals stop the work, so they get a dialog not a toast. */}
            <LimitNoticeDialog notice={limitNotice} />

            {/*
             * Mounted at page level rather than beside the picker: the setup
             * banner opens it too, and the picker lives on a step that unmounts
             * once the user moves on.
             */}
            <ContactDialog
                mode="create"
                kind="supplier"
                documentLabel="purchase order"
                open={newSupplier.dialogOpen}
                onOpenChange={newSupplier.setDialogOpen}
                onCreated={(id) => form.setData('supplier_id', String(id))}
            />

            <div className="mx-auto w-full py-3">
                {/*
                 * This page is reachable straight from the URL, so it cannot
                 * assume the list screen already turned the user away. With no
                 * supplier on file the only required picker would be empty, so
                 * say so plainly and point at the one place that fixes it.
                 */}
                {!canCreatePurchaseOrder && (
                    <Panel className="mb-4">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <div className="text-sm font-semibold">
                                    {!hasActiveCompany
                                        ? 'Add or select a company before raising purchase orders'
                                        : !hasSuppliers
                                          ? 'Add a supplier before raising a purchase order'
                                          : 'Activate a currency before raising a purchase order'}
                                </div>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {!hasActiveCompany
                                        ? 'Purchase orders belong to a company, so one has to be active before suppliers and orders can be managed.'
                                        : !hasSuppliers
                                          ? 'An order is always placed with somebody. Add the vendor you are buying from, then come back and order from them.'
                                          : 'An order is priced in the money the supplier invoices in, so at least one currency has to be active.'}
                                </p>
                            </div>

                            <div className="flex flex-col gap-2 sm:flex-row">
                                {!hasActiveCompany ? (
                                    <Link
                                        href="/companies"
                                        className={pillButtonClass(
                                            'solid',
                                            'sm',
                                        )}
                                    >
                                        Manage companies
                                    </Link>
                                ) : !hasSuppliers ? (
                                    <PillButton
                                        size="sm"
                                        onClick={newSupplier.openDialog}
                                    >
                                        <Factory className="h-4 w-4" />
                                        Add supplier
                                    </PillButton>
                                ) : (
                                    <Link
                                        href="/settings/currencies"
                                        className={pillButtonClass(
                                            'solid',
                                            'sm',
                                        )}
                                    >
                                        Manage currencies
                                    </Link>
                                )}
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
                         * form must not create the purchase order.
                         */
                        if (e.target !== e.currentTarget) {
                            return;
                        }

                        if (step === 'review') {
                            submit();
                        } else {
                            goNext();
                        }
                    }}
                >
                    <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
                        {/* ============ The purchase order ============ */}
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
                                                title="Purchase order details"
                                                action={
                                                    <NewContactButton
                                                        kind="supplier"
                                                        onSelect={
                                                            newSupplier.openDialog
                                                        }
                                                    />
                                                }
                                            />

                                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                <FormField
                                                    label="Supplier *"
                                                    className="sm:col-span-2"
                                                >
                                                    <RequiredHand
                                                        show={
                                                            blockedField ===
                                                            'supplier_id'
                                                        }
                                                        message="Pick the supplier you are ordering from."
                                                    />
                                                    {/*
                                                     * Deliberately never
                                                     * disabled: with no
                                                     * suppliers on file the
                                                     * picker is the only way to
                                                     * reach the "New supplier"
                                                     * row.
                                                     */}
                                                    <Select
                                                        value={
                                                            form.data
                                                                .supplier_id
                                                        }
                                                        onValueChange={(v) =>
                                                            v ===
                                                            NEW_CONTACT_VALUE
                                                                ? newSupplier.openDialog()
                                                                : form.setData(
                                                                      'supplier_id',
                                                                      v,
                                                                  )
                                                        }
                                                    >
                                                        <SelectTrigger
                                                            id="field-supplier_id"
                                                            className={
                                                                fieldInputClass
                                                            }
                                                        >
                                                            <SelectValue placeholder="Select supplier" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {hasSuppliers ? (
                                                                suppliers.map(
                                                                    (s) => (
                                                                        <SelectItem
                                                                            key={
                                                                                s.id
                                                                            }
                                                                            value={String(
                                                                                s.id,
                                                                            )}
                                                                        >
                                                                            {
                                                                                s.name
                                                                            }
                                                                        </SelectItem>
                                                                    ),
                                                                )
                                                            ) : (
                                                                <div className="px-2 py-3 text-sm text-muted-foreground">
                                                                    No suppliers
                                                                    yet.
                                                                </div>
                                                            )}

                                                            <NewContactOption kind="supplier" />
                                                        </SelectContent>
                                                    </Select>
                                                    {!hasSuppliers && (
                                                        <p className="mt-1 text-sm text-muted-foreground">
                                                            No suppliers yet —
                                                            add one from the
                                                            picker above.
                                                        </p>
                                                    )}
                                                    {selectedSupplier?.contact_person && (
                                                        <p className="mt-1 text-xs text-muted-foreground">
                                                            Attn:{' '}
                                                            {
                                                                selectedSupplier.contact_person
                                                            }
                                                            {selectedSupplier.email
                                                                ? ` • ${selectedSupplier.email}`
                                                                : ''}
                                                        </p>
                                                    )}
                                                    {form.errors
                                                        .supplier_id && (
                                                        <p className="mt-1 text-sm text-destructive">
                                                            {
                                                                form.errors
                                                                    .supplier_id
                                                            }
                                                        </p>
                                                    )}
                                                </FormField>

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

                                                <FormField label="Expected">
                                                    <RequiredHand
                                                        show={
                                                            blockedField ===
                                                            'expected_date'
                                                        }
                                                        message="Expected cannot be before the issue date."
                                                    />
                                                    <Input
                                                        id="field-expected_date"
                                                        className={
                                                            fieldInputClass
                                                        }
                                                        type="date"
                                                        min={
                                                            form.data.issue_date
                                                        }
                                                        value={
                                                            form.data
                                                                .expected_date
                                                        }
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'expected_date',
                                                                e.target.value,
                                                            )
                                                        }
                                                    />
                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                        When you expect the
                                                        goods. Leave it blank if
                                                        no date is agreed yet.
                                                    </p>
                                                    {form.errors
                                                        .expected_date && (
                                                        <p className="mt-1 text-sm text-destructive">
                                                            {
                                                                form.errors
                                                                    .expected_date
                                                            }
                                                        </p>
                                                    )}
                                                </FormField>

                                                {/*
                                                 * Kept, unlike the credit note.
                                                 * An order is priced in whatever
                                                 * the supplier invoices in, which
                                                 * may not be the company default.
                                                 */}
                                                <FormField
                                                    label="Currency *"
                                                    className="sm:col-span-2"
                                                >
                                                    <RequiredHand
                                                        show={
                                                            blockedField ===
                                                            'currency_code'
                                                        }
                                                        message="Choose the currency the supplier invoices in."
                                                    />
                                                    <Select
                                                        value={
                                                            form.data
                                                                .currency_code
                                                        }
                                                        disabled={
                                                            !hasCurrencies
                                                        }
                                                        onValueChange={(v) =>
                                                            form.setData(
                                                                'currency_code',
                                                                v,
                                                            )
                                                        }
                                                    >
                                                        <SelectTrigger
                                                            id="field-currency_code"
                                                            className={
                                                                fieldInputClass
                                                            }
                                                        >
                                                            <SelectValue placeholder="Select currency" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {hasCurrencies ? (
                                                                currencyList.map(
                                                                    (c) => (
                                                                        <SelectItem
                                                                            key={
                                                                                c.code
                                                                            }
                                                                            value={
                                                                                c.code
                                                                            }
                                                                        >
                                                                            {
                                                                                c.code
                                                                            }{' '}
                                                                            {
                                                                                c.name
                                                                            }
                                                                        </SelectItem>
                                                                    ),
                                                                )
                                                            ) : (
                                                                <div className="px-2 py-3 text-sm text-muted-foreground">
                                                                    No active
                                                                    currencies
                                                                    configured
                                                                    yet.
                                                                </div>
                                                            )}
                                                        </SelectContent>
                                                    </Select>
                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                        Defaults to the company
                                                        currency. Change it if
                                                        this supplier bills in
                                                        another.
                                                    </p>
                                                    {form.errors
                                                        .currency_code && (
                                                        <p className="mt-1 text-sm text-destructive">
                                                            {
                                                                form.errors
                                                                    .currency_code
                                                            }
                                                        </p>
                                                    )}
                                                </FormField>

                                                <FormField
                                                    label="Deliver to"
                                                    className="sm:col-span-2"
                                                >
                                                    <Input
                                                        id="field-delivery_address"
                                                        className={
                                                            fieldInputClass
                                                        }
                                                        maxLength={255}
                                                        value={
                                                            form.data
                                                                .delivery_address
                                                        }
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'delivery_address',
                                                                e.target.value,
                                                            )
                                                        }
                                                    />
                                                    {form.errors
                                                        .delivery_address && (
                                                        <p className="mt-1 text-sm text-destructive">
                                                            {
                                                                form.errors
                                                                    .delivery_address
                                                            }
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
                                                                        message="Describe what is being ordered."
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
                                                    label="Supplier"
                                                    value={
                                                        selectedSupplier?.name ??
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
                                                    label="Expected"
                                                    value={
                                                        form.data
                                                            .expected_date ||
                                                        'Not agreed'
                                                    }
                                                />
                                                <ReviewField
                                                    label="Currency"
                                                    value={
                                                        form.data
                                                            .currency_code ||
                                                        'Not set'
                                                    }
                                                />
                                                <ReviewField
                                                    label="Deliver to"
                                                    value={
                                                        form.data
                                                            .delivery_address ||
                                                        '—'
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
                                                    label="Status"
                                                    value={form.data.status}
                                                />
                                            </div>

                                            <div className="my-5 h-px bg-border/70 dark:bg-white/10" />

                                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                <FormField label="Overall discount">
                                                    <RequiredHand
                                                        show={
                                                            blockedField ===
                                                            'purchase_order_discount'
                                                        }
                                                        message="A discount cannot be negative."
                                                    />
                                                    <Input
                                                        id="field-purchase_order_discount"
                                                        className={
                                                            fieldInputClass
                                                        }
                                                        type="number"
                                                        min={0}
                                                        step={0.01}
                                                        value={
                                                            form.data
                                                                .purchase_order_discount
                                                        }
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'purchase_order_discount',
                                                                Number(
                                                                    e.target
                                                                        .value,
                                                                ),
                                                            )
                                                        }
                                                    />
                                                    {form.errors
                                                        .purchase_order_discount && (
                                                        <p className="mt-1 text-sm text-destructive">
                                                            {
                                                                form.errors
                                                                    .purchase_order_discount
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
                                                    <div className="flex flex-wrap gap-2">
                                                        {statuses.map(
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
                                                        A draft commits nothing.
                                                        Anything further can
                                                        also be set later, from
                                                        the order itself.
                                                    </p>
                                                    {form.errors.status && (
                                                        <p className="text-sm text-destructive">
                                                            {form.errors.status}
                                                        </p>
                                                    )}
                                                </FormField>
                                            </div>

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
                                                    !canCreatePurchaseOrder
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
                                                    !canCreatePurchaseOrder
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
                                                        Create purchase order
                                                        <CheckCircle2 className="h-4 w-4" />
                                                    </>
                                                )}
                                            </PillButton>
                                        )}
                                    </div>
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
                                            selectedSupplier
                                                ? `${form.data.currency_code || 'No currency yet'} • ${selectedSupplier.name}`
                                                : form.data.currency_code ||
                                                  'No currency yet'
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
                                            label="Purchase order discount"
                                            value={computed.orderDiscount}
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
                                            label="Total (incl. tax)"
                                            value={computed.total}
                                            money={money}
                                            strong
                                        />
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
function SectionTitle({
    icon: Icon,
    title,
    action,
}: {
    icon: LucideIcon;
    title: string;
    action?: React.ReactNode;
}) {
    return <PanelHeader icon={Icon} title={title} action={action} />;
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
