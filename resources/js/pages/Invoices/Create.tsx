import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import {
    ArrowLeft,
    ArrowRight,
    BadgePercent,
    CalendarClock,
    CheckCircle2,
    ClipboardList,
    Eye,
    Mail,
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

// shadcn
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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

const steps: {
    key: StepKey;
    title: string;
    description: string;
    icon: LucideIcon;
}[] = [
    {
        key: 'details',
        title: 'Details',
        description: 'Client, template, dates and currency',
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
        description: 'Discount, status and final check',
        icon: CheckCircle2,
    },
];

type InvoiceStatus = 'pending' | 'paid';

/** What is blocking a step, and the field to point the hand at. */
type StepIssue = { field: string | null; message: string };

/** Standard VAT rate; editable per invoice on the review step. */
const DEFAULT_TAX_PERCENT = 16;

/** A4 at 96dpi, the size the printed/downloaded invoice actually uses. */
const A4_WIDTH_PX = 794;
const A4_HEIGHT_PX = 1123;

/** Today as `YYYY-MM-DD`, the format the date inputs expect. */
function todayAsDateInputValue(): string {
    return new Date().toISOString().slice(0, 10);
}

export default function InvoicesCreate({
    clients,
    templates,
    defaultCurrencyCode,
    currencies,
    hasActiveCompany = true,
    limitNotice = null,
}: {
    clients: Client[];
    templates: Template[];
    defaultCurrencyCode: string;
    currencies?: { all: Currency[]; current: Currency | null };
    hasActiveCompany?: boolean;
    limitNotice?: LimitNotice | null;
}) {
    const currencyList = currencies?.all ?? [];
    const activeCurrency = currencies?.current ?? null;
    const hasClients = clients.length > 0;

    /** Lets a forgotten client be added without abandoning the invoice. */
    const newClient = useNewContactDialog();
    const hasCurrencies = currencyList.length > 0;
    const canCreateInvoice = hasActiveCompany && hasClients && hasCurrencies;
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

    // ✅ Preview modal
    const [previewOpen, setPreviewOpen] = React.useState(false);

    /** The preview renders the full document server-side, so it is not instant. */
    const [previewLoading, setPreviewLoading] = React.useState(false);
    const [previewHtml, setPreviewHtml] = React.useState('');

    /**
     * The document renders at true A4 (210mm x 297mm ≈ 794px x 1123px at 96dpi).
     * The iframe keeps those dimensions and is scaled down to fit the dialog, so the
     * preview stays a faithful reduction of the PDF instead of a reflowed narrow page.
     */
    const previewStageRef = React.useRef<HTMLDivElement | null>(null);
    const previewFrameRef = React.useRef<HTMLIFrameElement | null>(null);
    const [previewScale, setPreviewScale] = React.useState(1);
    const [previewPageHeight, setPreviewPageHeight] =
        React.useState(A4_HEIGHT_PX);

    React.useEffect(() => {
        const stage = previewStageRef.current;
        if (!previewOpen || !stage) return;

        const fitToStage = () => {
            const available = stage.clientWidth;
            if (available > 0) {
                setPreviewScale(Math.min(1, available / A4_WIDTH_PX));
            }
        };

        fitToStage();

        const observer = new ResizeObserver(fitToStage);
        observer.observe(stage);

        return () => observer.disconnect();
    }, [previewOpen]);

    /** Grows the frame to the rendered document height, rounded to whole A4 pages. */
    const measurePreviewHeight = () => {
        const doc = previewFrameRef.current?.contentDocument;
        if (!doc) return;

        const rendered = Math.max(
            doc.body?.scrollHeight ?? 0,
            doc.documentElement?.scrollHeight ?? 0,
            A4_HEIGHT_PX,
        );

        setPreviewPageHeight(Math.ceil(rendered / A4_HEIGHT_PX) * A4_HEIGHT_PX);
    };

    const today = todayAsDateInputValue();

    const form = useForm({
        client_id: '',
        invoice_template_id:
            templates.find((t) => t.is_default)?.id?.toString() ?? '',
        title: '',
        reference: '',
        issue_date: today,
        due_date: today,
        currency_code: initialCurrencyCode,
        has_delivery_note: false,

        // ✅ default pending
        status: 'pending' as InvoiceStatus,

        is_recurring: false,
        recurrence_frequency: 'monthly',
        recurrence_interval: 1,
        recurrence_start_date: '',
        recurrence_end_date: '',

        notes: '',
        terms: '',

        // ✅ overall discount on review
        invoice_discount: 0,

        // ✅ whole-invoice tax rate, applied after discounts
        tax_percent: DEFAULT_TAX_PERCENT,

        // ✅ email the finished invoice to the client
        send_to_client: false,

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

    const selectedClient = React.useMemo(
        () =>
            clients.find((c) => String(c.id) === String(form.data.client_id)) ??
            null,
        [clients, form.data.client_id],
    );

    /** Only a client with an address on file can be emailed. */
    const clientEmail = selectedClient?.email?.trim() || null;

    /** Switching to a client with no email silently withdraws the request. */
    React.useEffect(() => {
        if (!clientEmail && form.data.send_to_client) {
            form.setData('send_to_client', false);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [clientEmail, form.data.send_to_client]);

    const precision = React.useMemo(() => {
        const selected = currencyList.find(
            (c) => c.code === form.data.currency_code,
        );
        return selected?.precision ?? activeCurrency?.precision ?? 2;
    }, [currencyList, form.data.currency_code, activeCurrency?.precision]);

    const fmt = (v: number) => (Number.isFinite(v) ? v : 0).toFixed(precision);

    /**
     * Mirrors InvoiceController::computeTotals so the preview cannot drift.
     * Prices are tax-inclusive, so the tax is carved out of the gross rather
     * than added on top of it.
     */
    const computed = React.useMemo(() => {
        const round2 = (v: number) => Math.round(v * 100) / 100;

        let itemsGross = 0;
        let lineDiscount = 0;

        for (const it of form.data.items) {
            itemsGross += Number(it.quantity || 0) * Number(it.unit_price || 0);
            lineDiscount += Number(it.discount || 0);
        }

        const invoiceDiscount = Number(form.data.invoice_discount || 0);
        const taxPercent = Number(form.data.tax_percent || 0);

        const total = round2(
            Math.max(0, itemsGross - lineDiscount - invoiceDiscount),
        );
        const subtotal = round2(total / (1 + taxPercent / 100));

        return {
            itemsGross,
            lineDiscount,
            invoiceDiscount,
            subtotal,
            taxPercent,
            tax: round2(total - subtotal),
            total,
        };
    }, [form.data.items, form.data.invoice_discount, form.data.tax_percent]);

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
                message: 'Add or select a company before creating an invoice.',
            };
        }

        if (!hasClients) {
            return {
                field: null,
                message: 'Add a client before creating an invoice.',
            };
        }

        if (!hasCurrencies) {
            return {
                field: null,
                message: 'Add an active currency before creating an invoice.',
            };
        }

        if (s === 'details') {
            if (!form.data.client_id) {
                return { field: 'client_id', message: 'Select a client.' };
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
            if (Number(form.data.invoice_discount || 0) < 0) {
                return {
                    field: 'invoice_discount',
                    message: 'Invoice discount cannot be negative.',
                };
            }

            if (form.data.is_recurring) {
                if (!form.data.recurrence_frequency) {
                    return {
                        field: 'recurrence_frequency',
                        message: 'Select a recurrence frequency.',
                    };
                }
                if (
                    !form.data.recurrence_interval ||
                    form.data.recurrence_interval < 1
                ) {
                    return {
                        field: 'recurrence_interval',
                        message: 'Recurrence interval must be at least 1.',
                    };
                }
            }

            if (form.data.send_to_client && !clientEmail) {
                return {
                    field: 'send_to_client',
                    message:
                        'The selected client has no email address to send to.',
                };
            }
        }

        return null;
    };

    /**
     * Drop the hand the moment the field it is pointing at stops being the
     * problem, rather than making the user click Next again to find out. If a
     * later field on the same step is still incomplete it stays silent — the
     * next Next will point at it.
     */
    React.useEffect(() => {
        if (!blockedField) {
            return;
        }

        if (stepIssue(step)?.field !== blockedField) {
            setBlockedField(null);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [form.data, clientEmail, blockedField, step]);

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

    const openPreview = async () => {
        // preview must be accurate => validate current + previous
        const idx = order.indexOf(step);
        for (let i = 0; i <= Math.max(idx, order.indexOf('review')); i++) {
            if (!validateStep(order[i])) return;
        }

        setPreviewLoading(true);

        try {
            const res = await fetch('/invoices/preview', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN':
                        (
                            document.querySelector(
                                'meta[name="csrf-token"]',
                            ) as HTMLMetaElement
                        )?.content ?? '',
                },
                body: JSON.stringify({ ...form.data, embed: true }),
            });

            if (!res.ok) {
                // If your preview returns validation JSON, surface it
                const json = await res.json().catch(() => null);
                toast.error(json?.message || 'Preview failed.');
                return;
            }

            const html = await res.text();
            setPreviewPageHeight(A4_HEIGHT_PX);
            setPreviewHtml(html);
            setPreviewOpen(true);
        } catch {
            toast.error('Preview failed.');
        } finally {
            setPreviewLoading(false);
        }
    };

    const submit = () => {
        if (step !== 'review') return toast.error('Finish review first.');
        if (!validateStep('review')) return;

        form.post('/invoices', {
            preserveScroll: true,
            /**
             * Success navigates away to the new invoice, so this only matters
             * when the server refuses: keep the wizard where it was instead of
             * remounting back to step one and losing everything typed.
             */
            preserveState: true,
            /**
             * No success toast here — the redirect lands on the invoice, which
             * flashes the server's message. That one also says whether the
             * email was queued, so toasting here would only duplicate it.
             */
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

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create invoice" />

            {/* Plan refusals stop the work, so they get a dialog not a toast. */}
            <LimitNoticeDialog notice={limitNotice} />

            {/*
             * Mounted at page level rather than beside the picker: the setup
             * banner opens it too, and the picker lives on a step that unmounts
             * once the user moves on.
             */}
            <ContactDialog
                mode="create"
                kind="client"
                documentLabel="invoice"
                open={newClient.dialogOpen}
                onOpenChange={newClient.setDialogOpen}
                onCreated={(id) => form.setData('client_id', String(id))}
            />

            <div className="mx-auto w-full py-3">
                {!canCreateInvoice && (
                    <Panel className="mb-4">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <div className="text-sm font-semibold">
                                    {hasActiveCompany
                                        ? 'Finish setup before creating invoices'
                                        : 'Add or select a company before creating invoices'}
                                </div>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {hasActiveCompany
                                        ? 'Invoices stay available even with no data, but you need at least one client and one active currency before you can create one.'
                                        : 'Invoices are available, but you need an active company before clients, templates, and invoices can be managed.'}
                                </p>
                            </div>

                            <div className="flex flex-col gap-2 sm:flex-row">
                                {hasActiveCompany ? (
                                    <>
                                        <PillButton
                                            size="sm"
                                            onClick={newClient.openDialog}
                                        >
                                            Add client
                                        </PillButton>
                                        <Link
                                            href="/settings/currencies"
                                            className={pillButtonClass(
                                                'ghost',
                                                'sm',
                                            )}
                                        >
                                            Manage currencies
                                        </Link>
                                    </>
                                ) : (
                                    <Link
                                        href="/companies"
                                        className={pillButtonClass(
                                            'solid',
                                            'sm',
                                        )}
                                    >
                                        Manage companies
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
                         * Only act on this form's own submit. Dialogs rendered
                         * from inside it are portalled out of the DOM but still
                         * bubble through React, so a nested form saving its own
                         * data must not create the invoice.
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
                        {/* ============ The invoice ============ */}
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
                                                title="Invoice details"
                                                action={
                                                    <NewContactButton
                                                        kind="client"
                                                        onSelect={
                                                            newClient.openDialog
                                                        }
                                                    />
                                                }
                                            />

                                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                <FormField label="Client *">
                                                    <RequiredHand
                                                        show={
                                                            blockedField ===
                                                            'client_id'
                                                        }
                                                        message="Pick a client to bill."
                                                    />
                                                    {/*
                                                     * Deliberately never
                                                     * disabled: with no clients
                                                     * on file the picker is the
                                                     * only way to reach the
                                                     * "New client" row.
                                                     */}
                                                    <Select
                                                        value={
                                                            form.data.client_id
                                                        }
                                                        onValueChange={(v) =>
                                                            v ===
                                                            NEW_CONTACT_VALUE
                                                                ? newClient.openDialog()
                                                                : form.setData(
                                                                      'client_id',
                                                                      v,
                                                                  )
                                                        }
                                                    >
                                                        <SelectTrigger
                                                            id="field-client_id"
                                                            className={
                                                                fieldInputClass
                                                            }
                                                        >
                                                            <SelectValue placeholder="Select client" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {hasClients ? (
                                                                clients.map(
                                                                    (c) => (
                                                                        <SelectItem
                                                                            key={
                                                                                c.id
                                                                            }
                                                                            value={String(
                                                                                c.id,
                                                                            )}
                                                                        >
                                                                            {
                                                                                c.name
                                                                            }
                                                                        </SelectItem>
                                                                    ),
                                                                )
                                                            ) : (
                                                                <div className="px-2 py-3 text-sm text-muted-foreground">
                                                                    No clients
                                                                    yet.
                                                                </div>
                                                            )}

                                                            <NewContactOption kind="client" />
                                                        </SelectContent>
                                                    </Select>

                                                    {!hasClients && (
                                                        <p className="mt-1 text-sm text-muted-foreground">
                                                            No clients yet — add
                                                            one from the picker
                                                            above.
                                                        </p>
                                                    )}
                                                    {form.errors.client_id && (
                                                        <p className="mt-1 text-sm text-destructive">
                                                            {
                                                                form.errors
                                                                    .client_id
                                                            }
                                                        </p>
                                                    )}
                                                </FormField>

                                                <FormField label="Template">
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
                                                        <SelectTrigger
                                                            className={
                                                                fieldInputClass
                                                            }
                                                        >
                                                            <SelectValue placeholder="Select template" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {templates.map(
                                                                (t) => (
                                                                    <SelectItem
                                                                        key={
                                                                            t.id
                                                                        }
                                                                        value={String(
                                                                            t.id,
                                                                        )}
                                                                    >
                                                                        {t.name}
                                                                        {t.is_default
                                                                            ? ' (default)'
                                                                            : ''}
                                                                    </SelectItem>
                                                                ),
                                                            )}
                                                        </SelectContent>
                                                    </Select>
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

                                                <FormField label="Due date">
                                                    <Input
                                                        className={
                                                            fieldInputClass
                                                        }
                                                        type="date"
                                                        value={
                                                            form.data.due_date
                                                        }
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'due_date',
                                                                e.target.value,
                                                            )
                                                        }
                                                    />
                                                </FormField>

                                                <FormField
                                                    label="Currency *"
                                                    className="sm:col-span-2"
                                                >
                                                    <RequiredHand
                                                        show={
                                                            blockedField ===
                                                            'currency_code'
                                                        }
                                                        message="Choose the billing currency."
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
                                                    {!hasCurrencies && (
                                                        <p className="mt-1 text-sm text-muted-foreground">
                                                            Activate at least
                                                            one currency before
                                                            creating an invoice.
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

                                            <SoftTile className="mt-4 flex items-center justify-between gap-3">
                                                <div className="min-w-0">
                                                    <div className="text-sm font-semibold">
                                                        Delivery note
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        Enable if you want a
                                                        delivery note option
                                                        later.
                                                    </div>
                                                </div>
                                                <Switch
                                                    checked={
                                                        !!form.data
                                                            .has_delivery_note
                                                    }
                                                    onCheckedChange={(v) =>
                                                        form.setData(
                                                            'has_delivery_note',
                                                            !!v,
                                                        )
                                                    }
                                                />
                                            </SoftTile>

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
                                                                        message="Describe what is being charged."
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

                                                                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
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
                                                        form.data
                                                            .invoice_template_id,
                                                    )}
                                                />
                                                <ReviewField
                                                    label="Issue date"
                                                    value={
                                                        form.data.issue_date ||
                                                        '—'
                                                    }
                                                />
                                                <ReviewField
                                                    label="Due date"
                                                    value={
                                                        form.data.due_date ||
                                                        '—'
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
                                                    label="Delivery note"
                                                    value={
                                                        form.data
                                                            .has_delivery_note
                                                            ? 'Yes'
                                                            : 'No'
                                                    }
                                                />
                                                <ReviewField
                                                    label="Tax rate"
                                                    value={`${computed.taxPercent}%`}
                                                />
                                                <ReviewField
                                                    label="Email to client"
                                                    value={
                                                        form.data.send_to_client
                                                            ? (clientEmail ??
                                                              'Yes')
                                                            : 'No'
                                                    }
                                                />
                                            </div>

                                            <div className="my-5 h-px bg-border/70 dark:bg-white/10" />

                                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                <FormField label="Overall discount">
                                                    <RequiredHand
                                                        show={
                                                            blockedField ===
                                                            'invoice_discount'
                                                        }
                                                        message="A discount cannot be negative."
                                                    />
                                                    <Input
                                                        id="field-invoice_discount"
                                                        className={
                                                            fieldInputClass
                                                        }
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
                                                                    e.target
                                                                        .value,
                                                                ),
                                                            )
                                                        }
                                                    />
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

                                                <FormField label="Status">
                                                    <div className="flex gap-2">
                                                        <StatusPill
                                                            active={
                                                                form.data
                                                                    .status ===
                                                                'pending'
                                                            }
                                                            onClick={() =>
                                                                form.setData(
                                                                    'status',
                                                                    'pending',
                                                                )
                                                            }
                                                            label="Pending"
                                                        />
                                                        <StatusPill
                                                            active={
                                                                form.data
                                                                    .status ===
                                                                'paid'
                                                            }
                                                            onClick={() =>
                                                                form.setData(
                                                                    'status',
                                                                    'paid',
                                                                )
                                                            }
                                                            label="Paid"
                                                        />
                                                    </div>
                                                    {/* keep Select for accessibility / form consistency */}
                                                    <div className="hidden">
                                                        <Select
                                                            value={
                                                                form.data.status
                                                            }
                                                            onValueChange={(
                                                                v,
                                                            ) =>
                                                                form.setData(
                                                                    'status',
                                                                    v as InvoiceStatus,
                                                                )
                                                            }
                                                        >
                                                            <SelectTrigger />
                                                            <SelectContent>
                                                                <SelectItem value="pending">
                                                                    Pending
                                                                </SelectItem>
                                                                <SelectItem value="paid">
                                                                    Paid
                                                                </SelectItem>
                                                            </SelectContent>
                                                        </Select>
                                                    </div>
                                                    {form.errors.status && (
                                                        <p className="text-sm text-destructive">
                                                            {form.errors.status}
                                                        </p>
                                                    )}
                                                </FormField>
                                            </div>

                                            <div className="my-5 h-px bg-border/70 dark:bg-white/10" />

                                            {/* Recurrence and delivery share a row on wide screens. */}
                                            <div className="flex flex-col gap-5 sm:flex-row sm:items-start sm:gap-6">
                                                <div className="min-w-0 flex-1">
                                                    <PanelHeader
                                                        icon={CalendarClock}
                                                        title="Recurrence"
                                                    />

                                                    <SoftTile className="flex items-center justify-between gap-3">
                                                        <div className="min-w-0">
                                                            <div className="text-sm font-semibold">
                                                                Recurring
                                                                invoice
                                                            </div>
                                                            <div className="text-xs text-muted-foreground">
                                                                Reissue this
                                                                invoice on a
                                                                schedule.
                                                            </div>
                                                        </div>
                                                        <Switch
                                                            checked={
                                                                !!form.data
                                                                    .is_recurring
                                                            }
                                                            onCheckedChange={(
                                                                v,
                                                            ) =>
                                                                form.setData(
                                                                    'is_recurring',
                                                                    !!v,
                                                                )
                                                            }
                                                        />
                                                    </SoftTile>

                                                    {form.data.is_recurring && (
                                                        <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                                            <FormField
                                                                label="Frequency *"
                                                                className="sm:col-span-2"
                                                            >
                                                                <RequiredHand
                                                                    show={
                                                                        blockedField ===
                                                                        'recurrence_frequency'
                                                                    }
                                                                    message="Choose how often to reissue."
                                                                />
                                                                <Select
                                                                    value={
                                                                        form
                                                                            .data
                                                                            .recurrence_frequency
                                                                    }
                                                                    onValueChange={(
                                                                        v,
                                                                    ) =>
                                                                        form.setData(
                                                                            'recurrence_frequency',
                                                                            v,
                                                                        )
                                                                    }
                                                                >
                                                                    <SelectTrigger
                                                                        id="field-recurrence_frequency"
                                                                        className={
                                                                            fieldInputClass
                                                                        }
                                                                    >
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
                                                            </FormField>

                                                            <FormField
                                                                label="Interval *"
                                                                className="sm:col-span-2"
                                                            >
                                                                <RequiredHand
                                                                    show={
                                                                        blockedField ===
                                                                        'recurrence_interval'
                                                                    }
                                                                    message="Interval must be at least 1."
                                                                />
                                                                <Input
                                                                    id="field-recurrence_interval"
                                                                    className={
                                                                        fieldInputClass
                                                                    }
                                                                    type="number"
                                                                    min={1}
                                                                    value={
                                                                        form
                                                                            .data
                                                                            .recurrence_interval
                                                                    }
                                                                    onChange={(
                                                                        e,
                                                                    ) =>
                                                                        form.setData(
                                                                            'recurrence_interval',
                                                                            Number(
                                                                                e
                                                                                    .target
                                                                                    .value,
                                                                            ),
                                                                        )
                                                                    }
                                                                />
                                                            </FormField>

                                                            <FormField label="Start date">
                                                                <Input
                                                                    className={
                                                                        fieldInputClass
                                                                    }
                                                                    type="date"
                                                                    value={
                                                                        form
                                                                            .data
                                                                            .recurrence_start_date
                                                                    }
                                                                    onChange={(
                                                                        e,
                                                                    ) =>
                                                                        form.setData(
                                                                            'recurrence_start_date',
                                                                            e
                                                                                .target
                                                                                .value,
                                                                        )
                                                                    }
                                                                />
                                                            </FormField>

                                                            <FormField label="End date">
                                                                <Input
                                                                    className={
                                                                        fieldInputClass
                                                                    }
                                                                    type="date"
                                                                    value={
                                                                        form
                                                                            .data
                                                                            .recurrence_end_date
                                                                    }
                                                                    onChange={(
                                                                        e,
                                                                    ) =>
                                                                        form.setData(
                                                                            'recurrence_end_date',
                                                                            e
                                                                                .target
                                                                                .value,
                                                                        )
                                                                    }
                                                                />
                                                            </FormField>
                                                        </div>
                                                    )}
                                                </div>

                                                <div className="min-w-0 flex-1">
                                                    <PanelHeader
                                                        icon={Mail}
                                                        title="Email to client"
                                                        action={
                                                            selectedClient ? (
                                                                <ContactDialog
                                                                    mode="edit"
                                                                    kind="client"
                                                                    contact={
                                                                        selectedClient
                                                                    }
                                                                    documentLabel="invoice"
                                                                />
                                                            ) : null
                                                        }
                                                    />

                                                    <RequiredHand
                                                        show={
                                                            blockedField ===
                                                            'send_to_client'
                                                        }
                                                        message="This client has no email address yet."
                                                    />

                                                    <SoftTile
                                                        id="field-send_to_client"
                                                        className="flex items-center justify-between gap-3"
                                                    >
                                                        <div className="min-w-0">
                                                            <div className="text-sm font-semibold">
                                                                Send on create
                                                            </div>
                                                            <div className="truncate text-xs text-muted-foreground">
                                                                {clientEmail ??
                                                                    (selectedClient
                                                                        ? 'No email address on file'
                                                                        : 'Select a client first')}
                                                            </div>
                                                        </div>
                                                        <Switch
                                                            checked={
                                                                !!form.data
                                                                    .send_to_client
                                                            }
                                                            disabled={
                                                                !clientEmail
                                                            }
                                                            onCheckedChange={(
                                                                v,
                                                            ) =>
                                                                form.setData(
                                                                    'send_to_client',
                                                                    !!v,
                                                                )
                                                            }
                                                        />
                                                    </SoftTile>

                                                    {selectedClient &&
                                                    !clientEmail ? (
                                                        <p className="mt-2 text-xs text-muted-foreground">
                                                            {
                                                                selectedClient.name
                                                            }{' '}
                                                            has no email
                                                            address, so this
                                                            invoice cannot be
                                                            sent. Use Edit
                                                            client to add one
                                                            without leaving this
                                                            page.
                                                        </p>
                                                    ) : null}

                                                    {form.errors
                                                        .send_to_client && (
                                                        <p className="mt-2 text-xs text-destructive">
                                                            {
                                                                form.errors
                                                                    .send_to_client
                                                            }
                                                        </p>
                                                    )}
                                                </div>
                                            </div>

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
                                                                            {fmt(
                                                                                qty,
                                                                            )}
                                                                        </td>
                                                                        <td
                                                                            className={cn(
                                                                                reviewCellClass,
                                                                                'text-right tabular-nums',
                                                                            )}
                                                                        >
                                                                            {fmt(
                                                                                price,
                                                                            )}
                                                                        </td>
                                                                        <td
                                                                            className={cn(
                                                                                reviewCellClass,
                                                                                'rounded-r-2xl text-right font-semibold tabular-nums',
                                                                            )}
                                                                        >
                                                                            {fmt(
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
                                                    !canCreateInvoice
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
                                                    !canCreateInvoice
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
                                                        Create invoice
                                                        <CheckCircle2 className="h-4 w-4" />
                                                    </>
                                                )}
                                            </PillButton>
                                        )}
                                    </div>

                                    <PillButton
                                        variant="soft"
                                        size="sm"
                                        onClick={openPreview}
                                        disabled={
                                            !canCreateInvoice || previewLoading
                                        }
                                        className="mt-2 w-full"
                                    >
                                        {previewLoading ? (
                                            <>
                                                <NiloSpinner size={16} />
                                                Building preview…
                                            </>
                                        ) : (
                                            <>
                                                <Eye className="h-4 w-4" />
                                                Preview invoice
                                            </>
                                        )}
                                    </PillButton>
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
                                            form.data.currency_code ||
                                            'No currency yet'
                                        }
                                    />

                                    <SoftTile className="space-y-2 p-4">
                                        <Row
                                            label="Items (incl. tax)"
                                            value={computed.itemsGross}
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

                                        <div className="my-2 h-px bg-border/70 dark:bg-white/10" />

                                        <Row
                                            label="Subtotal (excl. tax)"
                                            value={computed.subtotal}
                                            precision={precision}
                                        />
                                        <Row
                                            label={`Tax (${computed.taxPercent}%)`}
                                            value={computed.tax}
                                            precision={precision}
                                        />

                                        <div className="my-2 h-px bg-border/70 dark:bg-white/10" />

                                        <Row
                                            label="Total (incl. tax)"
                                            value={computed.total}
                                            precision={precision}
                                            strong
                                        />
                                    </SoftTile>
                                </Panel>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            {/* ✅ Preview Modal */}
            <Dialog open={previewOpen} onOpenChange={setPreviewOpen}>
                <DialogContent className="rounded-2xl p-0 sm:max-w-[min(880px,calc(100vw-2rem))]">
                    <DialogHeader className="px-5 pt-5">
                        <DialogTitle className="flex items-center gap-2">
                            <Eye className="h-5 w-5 opacity-80" />
                            Invoice preview
                        </DialogTitle>
                        <DialogDescription>
                            Shown at A4 ({Math.round(previewScale * 100)}% of
                            print size) — exactly what the PDF will contain.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="max-h-[74vh] overflow-auto px-5 pb-5">
                        <div ref={previewStageRef} className="w-full">
                            <div
                                className="mx-auto overflow-hidden rounded-lg border bg-white shadow-sm"
                                style={{
                                    width: A4_WIDTH_PX * previewScale,
                                    height: previewPageHeight * previewScale,
                                }}
                            >
                                <iframe
                                    ref={previewFrameRef}
                                    title="Invoice preview"
                                    className="block border-0 bg-white"
                                    srcDoc={previewHtml}
                                    onLoad={measurePreviewHeight}
                                    style={{
                                        width: A4_WIDTH_PX,
                                        height: previewPageHeight,
                                        transform: `scale(${previewScale})`,
                                        transformOrigin: 'top left',
                                    }}
                                />
                            </div>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>
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
    precision,
    strong,
}: {
    label: string;
    value: number;
    precision: number;
    strong?: boolean;
}) {
    return (
        <TotalRow
            label={label}
            value={(Number.isFinite(value) ? value : 0).toFixed(precision)}
            strong={strong}
        />
    );
}

function ReviewField({ label, value }: { label: string; value: string }) {
    return (
        <SoftTile>
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-1 text-sm font-semibold capitalize">{value}</div>
        </SoftTile>
    );
}

function StatusPill({
    active,
    onClick,
    label,
}: {
    active: boolean;
    onClick: () => void;
    label: string;
}) {
    return (
        <Chip active={active} onClick={onClick}>
            {label}
        </Chip>
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
