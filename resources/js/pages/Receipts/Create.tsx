import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { HandCoins, Plus, Receipt, UserPlus } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

import ContactDialog, {
    NewContactButton,
    useNewContactDialog,
} from '@/components/contact-dialog';
import {
    FormField,
    Panel,
    PanelHeader,
    PillButton,
    SoftTile,
    fieldInputClass,
    pillButtonClass,
} from '@/components/dashboard/primitives';
import AppLayout from '@/layouts/app-layout';
import { formatMoney } from '@/lib/money';
import { type BreadcrumbItem } from '@/types/index.d';

type ReceiptClient = {
    id: number;
    name: string;
    email?: string | null;
    contact_person?: string | null;
    address?: string | null;
};

type PaymentMethod = { value: string; label: string };

type Currency = { code: string; name?: string | null };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Receipts', href: '/receipts' },
    { title: 'New receipt', href: '/receipts/create' },
];

/** Today as `YYYY-MM-DD`, in the browser's own timezone rather than UTC. */
function todayAsDateInputValue(): string {
    const now = new Date();

    return new Date(now.getTime() - now.getTimezoneOffset() * 60000)
        .toISOString()
        .slice(0, 10);
}

/**
 * Issues a receipt for money received with no invoice behind it.
 *
 * There is no line-item editor here, unlike the other document forms. A receipt
 * records one figure — what was handed over — and the description is the single
 * line it prints.
 */
export default function ReceiptsCreate({
    clients,
    defaultCurrencyCode,
    paymentMethods,
    hasActiveCompany = true,
}: {
    clients: ReceiptClient[];
    defaultCurrencyCode: string;
    paymentMethods: PaymentMethod[];
    hasActiveCompany?: boolean;
}) {
    const { currencies, flash } = usePage<{
        currencies?: { all: Currency[]; current: Currency | null } | null;
        flash?: { error?: string | null; info?: string | null };
    }>().props;

    React.useEffect(() => {
        if (flash?.error) toast.error(flash.error);
        if (flash?.info) toast.message(flash.info);
    }, [flash?.error, flash?.info]);

    const currencyList = currencies?.all ?? [];
    const hasClients = clients.length > 0;

    /** Lets a forgotten client be added without abandoning the receipt. */
    const newClient = useNewContactDialog();

    const form = useForm({
        client_id: '',
        amount: '',
        currency_code: defaultCurrencyCode,
        paid_on: todayAsDateInputValue(),
        method: paymentMethods[0]?.value ?? 'cash',
        reference: '',
        description: '',
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        form.post('/receipts', {
            onError: (errors) => {
                toast.error(
                    errors?.client_id ||
                        errors?.amount ||
                        errors?.currency_code ||
                        'Could not issue this receipt.',
                );
            },
        });
    };

    const fieldError = (message?: string) =>
        message ? <p className="text-xs text-destructive">{message}</p> : null;

    const amount = Number(form.data.amount || 0);

    /**
     * With no clients on file the form below is replaced entirely, so the
     * dialog has to be reachable from the empty state too — otherwise a new
     * user cannot get to a receipt at all without leaving for the clients page.
     */
    if (!hasActiveCompany || !hasClients) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="New receipt" />

                <ContactDialog
                    mode="create"
                    kind="client"
                    documentLabel="receipt"
                    open={newClient.dialogOpen}
                    onOpenChange={newClient.setDialogOpen}
                    onCreated={(id) => form.setData('client_id', String(id))}
                />

                <div className="mx-auto w-full py-3">
                    <Panel>
                        <MissingClient
                            hasActiveCompany={hasActiveCompany}
                            onAddClient={newClient.openDialog}
                        />
                    </Panel>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="New receipt" />

            {/*
             * Rendered outside the form: React propagates events up the
             * component tree even though Radix portals the dialog elsewhere in
             * the DOM, and a nested submit would issue the half-filled receipt.
             */}
            <ContactDialog
                mode="create"
                kind="client"
                documentLabel="receipt"
                open={newClient.dialogOpen}
                onOpenChange={newClient.setDialogOpen}
                onCreated={(id) => form.setData('client_id', String(id))}
            />

            <form onSubmit={submit} className="mx-auto w-full py-3">
                <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="flex min-w-0 items-start gap-2.5">
                        <span className="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">
                            <HandCoins className="h-4 w-4" />
                        </span>
                        <div>
                            <div className="text-sm font-semibold">
                                Money received without an invoice
                            </div>
                            <div className="mt-0.5 text-xs text-muted-foreground">
                                To receipt a payment against an invoice, record
                                it on that invoice instead.
                            </div>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Link
                            href="/receipts"
                            className={pillButtonClass('ghost', 'sm')}
                        >
                            Cancel
                        </Link>
                        <PillButton
                            variant="solid"
                            size="sm"
                            type="submit"
                            disabled={form.processing}
                        >
                            <Receipt className="h-4 w-4" />
                            {form.processing ? 'Issuing…' : 'Issue receipt'}
                        </PillButton>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
                    <Panel className="lg:col-span-8">
                        <PanelHeader
                            icon={Receipt}
                            title="Receipt details"
                            action={
                                <NewContactButton
                                    kind="client"
                                    onSelect={newClient.openDialog}
                                />
                            }
                        />

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <FormField
                                label="Received from"
                                htmlFor="client_id"
                                required
                                className="sm:col-span-2"
                            >
                                <select
                                    id="client_id"
                                    className={fieldInputClass}
                                    value={form.data.client_id}
                                    onChange={(e) =>
                                        form.setData(
                                            'client_id',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="">Choose a client…</option>
                                    {clients.map((client) => (
                                        <option
                                            key={client.id}
                                            value={String(client.id)}
                                        >
                                            {client.name}
                                        </option>
                                    ))}
                                </select>

                                {fieldError(form.errors.client_id)}
                            </FormField>

                            <FormField label="Amount" htmlFor="amount" required>
                                <input
                                    id="amount"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    inputMode="decimal"
                                    className={fieldInputClass}
                                    value={form.data.amount}
                                    onChange={(e) =>
                                        form.setData('amount', e.target.value)
                                    }
                                />
                                {fieldError(form.errors.amount)}
                            </FormField>

                            {/*
                                Editable, not fixed to the company default: cash
                                received in a currency the company does not
                                usually bill in is exactly the case this document
                                exists for.
                            */}
                            <FormField
                                label="Currency"
                                htmlFor="currency_code"
                                required
                            >
                                <select
                                    id="currency_code"
                                    className={fieldInputClass}
                                    value={form.data.currency_code}
                                    onChange={(e) =>
                                        form.setData(
                                            'currency_code',
                                            e.target.value,
                                        )
                                    }
                                >
                                    {currencyList.length > 0 ? (
                                        currencyList.map((currency) => (
                                            <option
                                                key={currency.code}
                                                value={currency.code}
                                            >
                                                {currency.code}
                                                {currency.name
                                                    ? ` ${currency.name}`
                                                    : ''}
                                            </option>
                                        ))
                                    ) : (
                                        <option value={defaultCurrencyCode}>
                                            {defaultCurrencyCode}
                                        </option>
                                    )}
                                </select>
                                {fieldError(form.errors.currency_code)}
                            </FormField>

                            <FormField
                                label="Paid on"
                                htmlFor="paid_on"
                                required
                            >
                                <input
                                    id="paid_on"
                                    type="date"
                                    className={fieldInputClass}
                                    value={form.data.paid_on}
                                    onChange={(e) =>
                                        form.setData('paid_on', e.target.value)
                                    }
                                />
                                {fieldError(form.errors.paid_on)}
                            </FormField>

                            <FormField label="Method" htmlFor="method" required>
                                <select
                                    id="method"
                                    className={fieldInputClass}
                                    value={form.data.method}
                                    onChange={(e) =>
                                        form.setData('method', e.target.value)
                                    }
                                >
                                    {paymentMethods.map((method) => (
                                        <option
                                            key={method.value}
                                            value={method.value}
                                        >
                                            {method.label}
                                        </option>
                                    ))}
                                </select>
                                {fieldError(form.errors.method)}
                            </FormField>

                            <FormField
                                label="Reference"
                                htmlFor="reference"
                                className="sm:col-span-2"
                            >
                                <input
                                    id="reference"
                                    type="text"
                                    className={fieldInputClass}
                                    value={form.data.reference}
                                    onChange={(e) =>
                                        form.setData(
                                            'reference',
                                            e.target.value,
                                        )
                                    }
                                />
                                {fieldError(form.errors.reference)}
                            </FormField>

                            {/*
                                This is the single line the printed receipt
                                carries, which is why the hint says so — an empty
                                one prints as bare "Payment received".
                            */}
                            <FormField
                                label="What was this for?"
                                htmlFor="description"
                                className="sm:col-span-2"
                            >
                                <input
                                    id="description"
                                    type="text"
                                    className={fieldInputClass}
                                    value={form.data.description}
                                    onChange={(e) =>
                                        form.setData(
                                            'description',
                                            e.target.value,
                                        )
                                    }
                                />
                                <p className="text-xs text-muted-foreground">
                                    Printed as the receipt’s only line.
                                </p>
                                {fieldError(form.errors.description)}
                            </FormField>
                        </div>
                    </Panel>

                    <Panel className="lg:col-span-4">
                        <PanelHeader icon={HandCoins} title="Summary" />

                        <SoftTile>
                            <div className="text-xs text-muted-foreground">
                                Receipt total
                            </div>
                            <div className="mt-1 text-2xl font-semibold tabular-nums">
                                {formatMoney(amount, form.data.currency_code)}
                            </div>
                        </SoftTile>

                        <SoftTile className="mt-2">
                            <p className="text-xs text-muted-foreground">
                                The receipt number is assigned when you issue
                                it, continuing the same sequence as receipts
                                printed from invoice payments.
                            </p>
                        </SoftTile>
                    </Panel>
                </div>
            </form>
        </AppLayout>
    );
}

/**
 * A receipt has to be addressed to someone, so with no clients on file there is
 * nothing to fill in — the form is replaced rather than shown broken.
 */
function MissingClient({
    hasActiveCompany,
    onAddClient,
}: {
    hasActiveCompany: boolean;
    onAddClient: () => void;
}) {
    return (
        <div className="flex flex-col items-center px-4 py-10 text-center">
            <span className="grid h-14 w-14 place-items-center rounded-2xl bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">
                <UserPlus className="h-6 w-6" />
            </span>

            <div className="mt-4 text-sm font-semibold">
                {hasActiveCompany
                    ? 'Add a client first'
                    : 'Select a company first'}
            </div>
            <p className="mt-1 max-w-sm text-sm text-muted-foreground">
                {hasActiveCompany
                    ? 'A receipt has to say who the money came from. Add your first client to continue.'
                    : 'Every receipt is issued by a company. Create one or make an existing company active before you continue.'}
            </p>

            <div className="mt-5">
                {hasActiveCompany ? (
                    <PillButton size="sm" onClick={onAddClient}>
                        <Plus className="h-4 w-4" />
                        Add client
                    </PillButton>
                ) : (
                    <Link
                        href="/companies"
                        className={pillButtonClass('solid', 'sm')}
                    >
                        Manage companies
                    </Link>
                )}
            </div>
        </div>
    );
}
