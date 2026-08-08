import {
    Chip,
    EmptyState,
    InitialsAvatar,
    Pagination,
    Panel,
    PanelHeader,
    PillButton,
    StatusPill,
    pillButtonClass,
    tableCellClass,
    type PaginationLink,
} from '@/components/dashboard/primitives';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import {
    CheckCircle2,
    CreditCard,
    Paperclip,
    RefreshCw,
    XCircle,
} from 'lucide-react';

interface PaymentItem {
    id: number;
    amount: string;
    currency_code: string;
    charged_amount: string | null;
    charged_currency_code: string | null;
    payment_method: string;
    payment_reference: string | null;
    company_ref: string | null;
    phone_number: string | null;
    pop_file_path: string | null;
    status: string;
    gateway_status: string | null;
    created_at: string;
    user: { id: number; name: string; email: string };
    plan: { name: string };
}

interface PaymentsIndexProps {
    payments: {
        data: PaymentItem[];
        current_page: number;
        last_page: number;
        links: PaginationLink[];
    };
    filters: { status: string; method: string };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Payments', href: '/admin/payments' },
];

const STATUS_TABS = ['pending', 'confirmed', 'rejected', 'all'] as const;
const METHOD_TABS = ['all', 'dpo', 'manual'] as const;

const METHOD_LABELS: Record<string, string> = {
    mobile_money: 'Mobile money',
    /** Retired in favour of mobile_money; kept so old payments still read. */
    airtel_money: 'Airtel Money',
    bank_transfer: 'Bank transfer',
    coupon: 'Coupon',
    dpo: 'Card / mobile money',
};

const methodLabel = (method: string): string => METHOD_LABELS[method] ?? method;

export default function PaymentsIndex({
    payments,
    filters,
}: PaymentsIndexProps) {
    const filterBy = (next: Partial<PaymentsIndexProps['filters']>) =>
        router.get(
            '/admin/payments',
            { ...filters, ...next },
            { preserveState: true },
        );

    const handleVerify = (paymentId: number) => {
        router.post(`/admin/payments/${paymentId}/verify`);
    };

    const handleConfirm = (paymentId: number) => {
        if (confirm('Confirm this payment and activate the subscription?')) {
            router.post(`/admin/payments/${paymentId}/confirm`);
        }
    };

    const handleReject = (paymentId: number) => {
        if (confirm('Reject this payment?')) {
            router.post(`/admin/payments/${paymentId}/reject`);
        }
    };

    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin - Payments" />

            <div className="flex w-full flex-col gap-4 py-6">
                <Panel>
                    <PanelHeader
                        icon={CreditCard}
                        title="Payments"
                        subtitle="Confirming a payment activates the subscriber's plan straight away."
                    />

                    <div className="mb-2 flex flex-wrap items-center gap-2">
                        {STATUS_TABS.map((tab) => (
                            <Chip
                                key={tab}
                                active={filters.status === tab}
                                onClick={() => filterBy({ status: tab })}
                            >
                                {tab}
                            </Chip>
                        ))}
                    </div>

                    <div className="mb-4 flex flex-wrap items-center gap-2">
                        <span className="text-xs text-muted-foreground">
                            Method
                        </span>
                        {METHOD_TABS.map((tab) => (
                            <Chip
                                key={tab}
                                active={(filters.method ?? 'all') === tab}
                                onClick={() => filterBy({ method: tab })}
                            >
                                {tab === 'dpo' ? 'gateway' : tab}
                            </Chip>
                        ))}
                    </div>

                    {payments.data.length === 0 ? (
                        <EmptyState
                            icon={CreditCard}
                            tone="muted"
                            title="No payments"
                            description={`Nothing to show under the “${filters.status}” filter.`}
                        />
                    ) : (
                        <div className="-mx-1 overflow-x-auto px-1">
                            <table className="w-full min-w-[52rem] border-separate border-spacing-y-1.5 text-sm">
                                <thead>
                                    <tr className="text-left text-xs text-muted-foreground">
                                        <th className="px-3 pb-1 font-medium">
                                            User
                                        </th>
                                        <th className="px-3 pb-1 font-medium">
                                            Plan
                                        </th>
                                        <th className="px-3 pb-1 font-medium">
                                            Amount
                                        </th>
                                        <th className="px-3 pb-1 font-medium">
                                            Method
                                        </th>
                                        <th className="px-3 pb-1 font-medium">
                                            Reference
                                        </th>
                                        <th className="px-3 pb-1 font-medium">
                                            Date
                                        </th>
                                        <th className="px-3 pb-1 text-right font-medium">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>

                                <tbody>
                                    {payments.data.map((payment) => (
                                        <tr key={payment.id} className="group">
                                            <td
                                                className={cn(
                                                    tableCellClass,
                                                    'rounded-l-2xl',
                                                )}
                                            >
                                                <Link
                                                    href={`/admin/users/${payment.user.id}`}
                                                    className="flex items-center gap-3"
                                                >
                                                    <InitialsAvatar
                                                        name={payment.user.name}
                                                    />
                                                    <div className="min-w-0">
                                                        <div className="truncate font-semibold">
                                                            {payment.user.name}
                                                        </div>
                                                        <div className="mt-0.5 truncate text-xs text-muted-foreground">
                                                            {payment.user.email}
                                                        </div>
                                                    </div>
                                                </Link>
                                            </td>

                                            <td className={tableCellClass}>
                                                {payment.plan.name}
                                            </td>

                                            <td
                                                className={cn(
                                                    tableCellClass,
                                                    'font-semibold tabular-nums',
                                                )}
                                            >
                                                {formatMoney(
                                                    payment.amount,
                                                    payment.currency_code,
                                                )}
                                                {payment.charged_currency_code &&
                                                payment.charged_currency_code !==
                                                    payment.currency_code ? (
                                                    <div className="mt-0.5 text-xs font-normal text-muted-foreground">
                                                        charged{' '}
                                                        {formatMoney(
                                                            payment.charged_amount,
                                                            payment.charged_currency_code,
                                                        )}
                                                    </div>
                                                ) : null}
                                            </td>

                                            <td className={tableCellClass}>
                                                {methodLabel(
                                                    payment.payment_method,
                                                )}
                                            </td>

                                            <td
                                                className={cn(
                                                    tableCellClass,
                                                    'text-muted-foreground',
                                                )}
                                            >
                                                {payment.payment_reference ??
                                                    '—'}
                                                {payment.pop_file_path ? (
                                                    <a
                                                        href={`/storage/${payment.pop_file_path}`}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="mt-1 inline-flex items-center gap-1 text-xs font-semibold text-brand-600 hover:text-brand-700 dark:text-brand-300"
                                                    >
                                                        <Paperclip className="h-3 w-3" />
                                                        Proof
                                                    </a>
                                                ) : null}
                                            </td>

                                            <td
                                                className={cn(
                                                    tableCellClass,
                                                    'text-muted-foreground',
                                                )}
                                            >
                                                {new Date(
                                                    payment.created_at,
                                                ).toLocaleDateString()}
                                            </td>

                                            <td
                                                className={cn(
                                                    tableCellClass,
                                                    'rounded-r-2xl text-right',
                                                )}
                                            >
                                                {payment.payment_method ===
                                                'dpo' ? (
                                                    /* DPO settles its own payments — the only
                                                       useful admin action is to re-ask it. */
                                                    <div className="flex items-center justify-end gap-2">
                                                        <StatusPill
                                                            status={
                                                                payment.gateway_status ??
                                                                payment.status
                                                            }
                                                        />
                                                        {payment.status ===
                                                        'pending' ? (
                                                            <PillButton
                                                                variant="ghost"
                                                                size="sm"
                                                                onClick={() =>
                                                                    handleVerify(
                                                                        payment.id,
                                                                    )
                                                                }
                                                            >
                                                                <RefreshCw className="h-3.5 w-3.5" />
                                                                Verify
                                                            </PillButton>
                                                        ) : null}
                                                        <Link
                                                            href={`/admin/payments/${payment.id}`}
                                                            className={pillButtonClass(
                                                                'ghost',
                                                                'sm',
                                                            )}
                                                        >
                                                            View
                                                        </Link>
                                                    </div>
                                                ) : payment.status ===
                                                  'pending' ? (
                                                    <div className="flex justify-end gap-2">
                                                        <PillButton
                                                            variant="soft"
                                                            size="sm"
                                                            onClick={() =>
                                                                handleConfirm(
                                                                    payment.id,
                                                                )
                                                            }
                                                        >
                                                            <CheckCircle2 className="h-3.5 w-3.5" />
                                                            Confirm
                                                        </PillButton>
                                                        <PillButton
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() =>
                                                                handleReject(
                                                                    payment.id,
                                                                )
                                                            }
                                                            className="text-destructive"
                                                        >
                                                            <XCircle className="h-3.5 w-3.5" />
                                                            Reject
                                                        </PillButton>
                                                    </div>
                                                ) : (
                                                    <div className="flex justify-end gap-2">
                                                        <StatusPill
                                                            status={
                                                                payment.status
                                                            }
                                                        />
                                                        <Link
                                                            href={`/admin/payments/${payment.id}`}
                                                            className={pillButtonClass(
                                                                'ghost',
                                                                'sm',
                                                            )}
                                                        >
                                                            View
                                                        </Link>
                                                    </div>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Panel>

                <Pagination links={payments.links} />
            </div>
        </AppSidebarLayout>
    );
}
