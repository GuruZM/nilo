import {
    InitialsAvatar,
    Panel,
    PanelHeader,
    PillButton,
    SoftTile,
    StatusPill,
    TotalRow,
    pillButtonClass,
} from '@/components/dashboard/primitives';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { formatMoney } from '@/lib/money';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCircle2,
    CreditCard,
    FileText,
    Receipt,
    RefreshCw,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';

interface PaymentShowProps {
    payment: {
        id: number;
        amount: string;
        original_amount: string | null;
        discount_amount: string;
        coupon_code: string | null;
        currency_code: string;
        charged_amount: string | null;
        charged_currency_code: string | null;
        charged_exchange_rate: number | null;
        charged_rate_fetched_at: string | null;
        payment_method: string;
        payment_reference: string | null;
        company_ref: string | null;
        dpo_transaction_token: string | null;
        phone_number: string | null;
        pop_file_path: string | null;
        status: string;
        gateway_status: string | null;
        gateway_response: Record<string, unknown> | null;
        admin_notes: string | null;
        created_at: string;
        confirmed_at: string | null;
        paid_at: string | null;
        verified_at: string | null;
        user: { id: number; name: string; email: string };
        plan: { name: string };
        confirmed_by: { name: string } | null;
    };
}

const METHOD_LABELS: Record<string, string> = {
    mobile_money: 'Mobile money',
    /** Retired in favour of mobile_money; kept so old payments still read. */
    airtel_money: 'Airtel Money',
    bank_transfer: 'Bank transfer',
    coupon: 'Coupon',
    dpo: 'Card / mobile money',
};

export default function PaymentShow({ payment }: PaymentShowProps) {
    const isGateway = payment.payment_method === 'dpo';
    const [showRaw, setShowRaw] = useState(false);
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Admin', href: '/admin' },
        { title: 'Payments', href: '/admin/payments' },
        {
            title: `Payment #${payment.id}`,
            href: `/admin/payments/${payment.id}`,
        },
    ];

    const handleConfirm = () => {
        if (confirm('Confirm this payment and activate the subscription?')) {
            router.post(`/admin/payments/${payment.id}/confirm`);
        }
    };

    const handleReject = () => {
        if (confirm('Reject this payment?')) {
            router.post(`/admin/payments/${payment.id}/reject`);
        }
    };

    const handleVerify = () => {
        router.post(`/admin/payments/${payment.id}/verify`);
    };

    const isPdf = payment.pop_file_path?.endsWith('.pdf') ?? false;
    const discount = parseFloat(payment.discount_amount);
    const wasDiscounted = discount > 0;

    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>
            <Head title="Payment Detail" />

            <div className="flex w-full flex-col gap-4 py-6">
                <Panel>
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-center gap-3">
                            <InitialsAvatar
                                name={payment.user.name}
                                className="h-11 w-11 text-sm"
                            />
                            <div className="min-w-0">
                                <div className="flex items-center gap-2">
                                    <span className="truncate text-base font-semibold">
                                        Payment #{payment.id}
                                    </span>
                                    <StatusPill status={payment.status} />
                                </div>
                                <Link
                                    href={`/admin/users/${payment.user.id}`}
                                    className="truncate text-xs text-muted-foreground hover:text-foreground"
                                >
                                    {payment.user.name} · {payment.user.email}
                                </Link>
                            </div>
                        </div>

                        <Link
                            href="/admin/payments"
                            className={pillButtonClass('ghost', 'sm')}
                        >
                            <ArrowLeft className="h-3.5 w-3.5" />
                            All payments
                        </Link>
                    </div>
                </Panel>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
                    <Panel className="lg:col-span-5">
                        <PanelHeader
                            icon={Receipt}
                            title="Payment details"
                            subtitle="What the subscriber submitted."
                        />

                        <SoftTile className="flex flex-col gap-2">
                            <TotalRow label="Plan" value={payment.plan.name} />
                            {wasDiscounted ? (
                                <>
                                    <TotalRow
                                        label="List price"
                                        value={formatMoney(
                                            payment.original_amount ??
                                                payment.amount,
                                            payment.currency_code,
                                        )}
                                    />
                                    <TotalRow
                                        label={`Coupon ${payment.coupon_code ?? ''}`.trim()}
                                        value={`− ${formatMoney(discount, payment.currency_code)}`}
                                    />
                                </>
                            ) : null}
                            <TotalRow
                                label={wasDiscounted ? 'Amount due' : 'Amount'}
                                value={formatMoney(
                                    payment.amount,
                                    payment.currency_code,
                                )}
                                strong
                            />
                            <TotalRow
                                label="Method"
                                value={
                                    METHOD_LABELS[payment.payment_method] ??
                                    payment.payment_method
                                }
                            />
                            {payment.phone_number ? (
                                <TotalRow
                                    label="Phone number"
                                    value={payment.phone_number}
                                />
                            ) : null}
                            {payment.payment_reference ? (
                                <TotalRow
                                    label="Reference"
                                    value={payment.payment_reference}
                                />
                            ) : null}
                            <TotalRow
                                label="Submitted"
                                value={new Date(
                                    payment.created_at,
                                ).toLocaleString()}
                            />
                            {payment.confirmed_at ? (
                                <TotalRow
                                    label="Confirmed"
                                    value={new Date(
                                        payment.confirmed_at,
                                    ).toLocaleString()}
                                />
                            ) : null}
                            {payment.confirmed_by ? (
                                <TotalRow
                                    label="Confirmed by"
                                    value={payment.confirmed_by.name}
                                />
                            ) : null}
                        </SoftTile>

                        {payment.admin_notes ? (
                            <p className="mt-3 rounded-2xl bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">
                                {payment.admin_notes}
                            </p>
                        ) : null}

                        {isGateway ? (
                            payment.status === 'pending' ? (
                                <div className="mt-4 flex flex-wrap items-center gap-2">
                                    <PillButton
                                        variant="soft"
                                        onClick={handleVerify}
                                    >
                                        <RefreshCw className="h-4 w-4" />
                                        Verify with DPO
                                    </PillButton>
                                    <span className="text-xs text-muted-foreground">
                                        DPO settles this payment — it cannot be
                                        confirmed by hand.
                                    </span>
                                </div>
                            ) : null
                        ) : payment.status === 'pending' ? (
                            <div className="mt-4 flex flex-wrap items-center gap-2">
                                <PillButton onClick={handleConfirm}>
                                    <CheckCircle2 className="h-4 w-4" />
                                    Confirm payment
                                </PillButton>
                                <PillButton
                                    variant="ghost"
                                    onClick={handleReject}
                                    className="text-destructive"
                                >
                                    <XCircle className="h-4 w-4" />
                                    Reject
                                </PillButton>
                            </div>
                        ) : null}
                    </Panel>

                    {isGateway ? (
                        <Panel className="lg:col-span-7">
                            <PanelHeader
                                icon={CreditCard}
                                title="Gateway"
                                subtitle="What DPO was asked for, and what it said."
                            />

                            <SoftTile className="flex flex-col gap-2">
                                <TotalRow
                                    label="Gateway status"
                                    value={payment.gateway_status ?? '—'}
                                />
                                <TotalRow
                                    label="Charged"
                                    value={formatMoney(
                                        payment.charged_amount ??
                                            payment.amount,
                                        payment.charged_currency_code ??
                                            payment.currency_code,
                                    )}
                                    strong
                                />
                                {payment.charged_exchange_rate ? (
                                    <TotalRow
                                        label="Rate frozen at"
                                        value={`${payment.charged_exchange_rate} ${payment.currency_code}/USD${
                                            payment.charged_rate_fetched_at
                                                ? ` · ${new Date(payment.charged_rate_fetched_at).toLocaleDateString()}`
                                                : ''
                                        }`}
                                    />
                                ) : null}
                                <TotalRow
                                    label="Our reference"
                                    value={payment.company_ref ?? '—'}
                                />
                                <TotalRow
                                    label="DPO token"
                                    value={
                                        payment.dpo_transaction_token
                                            ? `${payment.dpo_transaction_token.slice(0, 12)}…`
                                            : '—'
                                    }
                                />
                                {payment.verified_at ? (
                                    <TotalRow
                                        label="Last verified"
                                        value={new Date(
                                            payment.verified_at,
                                        ).toLocaleString()}
                                    />
                                ) : null}
                                {payment.paid_at ? (
                                    <TotalRow
                                        label="Paid"
                                        value={new Date(
                                            payment.paid_at,
                                        ).toLocaleString()}
                                    />
                                ) : null}
                            </SoftTile>

                            {payment.gateway_response ? (
                                <div className="mt-3">
                                    <PillButton
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => setShowRaw((on) => !on)}
                                    >
                                        {showRaw ? 'Hide' : 'Show'} raw response
                                    </PillButton>
                                    {showRaw ? (
                                        <pre className="mt-2 max-h-72 overflow-auto rounded-2xl bg-muted/50 p-3 text-xs dark:bg-white/5">
                                            {JSON.stringify(
                                                payment.gateway_response,
                                                null,
                                                2,
                                            )}
                                        </pre>
                                    ) : null}
                                </div>
                            ) : null}
                        </Panel>
                    ) : (
                        <Panel className="lg:col-span-7">
                            <PanelHeader
                                icon={FileText}
                                title="Proof of payment"
                                subtitle="Uploaded by the subscriber at submission."
                            />

                            {!payment.pop_file_path ? (
                                <p className="py-8 text-center text-sm text-muted-foreground">
                                    No proof of payment was uploaded.
                                </p>
                            ) : isPdf ? (
                                <a
                                    href={`/storage/${payment.pop_file_path}`}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className={pillButtonClass('soft', 'md')}
                                >
                                    <FileText className="h-4 w-4" />
                                    Open PDF
                                </a>
                            ) : (
                                <a
                                    href={`/storage/${payment.pop_file_path}`}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="block overflow-hidden rounded-2xl bg-muted/50 dark:bg-white/5"
                                >
                                    <img
                                        src={`/storage/${payment.pop_file_path}`}
                                        alt="Proof of payment"
                                        className="h-auto w-full max-w-full"
                                    />
                                </a>
                            )}
                        </Panel>
                    )}
                </div>
            </div>
        </AppSidebarLayout>
    );
}
