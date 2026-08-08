import {
    EmptyState,
    Pagination,
    Panel,
    PanelHeader,
    Ring,
    SoftTile,
    StatusPill,
    pillButtonClass,
    tableCellClass,
    type PaginationLink,
} from '@/components/dashboard/primitives';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import { Plan, UsageData, type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import {
    ArrowUpRight,
    CalendarClock,
    CalendarDays,
    Clock3,
    Crown,
    Gauge,
    Receipt,
    Wallet,
} from 'lucide-react';

interface SubscriptionSummary {
    id: number;
    status: string;
    starts_at: string;
    ends_at: string | null;
    plan: Plan;
}

interface PaymentRow {
    id: number;
    amount: string;
    currency_code: string;
    charged_amount: string | null;
    charged_currency_code: string | null;
    payment_method: string;
    payment_reference: string | null;
    coupon_code: string | null;
    discount_amount: string;
    status: string;
    created_at: string;
    paid_at: string | null;
    plan: { id: number; name: string } | null;
}

interface CurrentProps {
    subscription: SubscriptionSummary | null;
    /** An upgrade that has been started but not yet paid for. */
    pendingSubscription: SubscriptionSummary | null;
    usage: UsageData;
    plans: Plan[];
    payments: { data: PaymentRow[]; links: PaginationLink[] };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Billing', href: '/subscription' },
];

/** SVG strokes take literal colours, matching the dashboard's ring meters. */
const RING_BRAND = '#00417d';
const RING_AMBER = '#d97706';

const METHOD_LABELS: Record<string, string> = {
    mobile_money: 'Mobile money',
    /** Retired in favour of mobile_money; kept so old payments still read. */
    airtel_money: 'Airtel Money',
    bank_transfer: 'Bank transfer',
    coupon: 'Coupon',
    dpo: 'Card / mobile money',
    free: 'Free plan',
    admin_assigned: 'Assigned by Nilo',
};

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleDateString() : '—';
}

/**
 * Unlimited allowances have no meaningful percentage, so the ring is dropped
 * and the tile reads as a plain count instead of a meter pinned at nothing.
 */
function UsageTile({
    label,
    used,
    limit,
}: {
    label: string;
    used: number;
    limit: number;
}) {
    const isUnlimited = limit === -1;
    const percent = isUnlimited || limit <= 0 ? 0 : (used / limit) * 100;
    const nearLimit = !isUnlimited && percent >= 80;

    return (
        <SoftTile className="flex items-center gap-3">
            {isUnlimited ? (
                <span className="grid h-14 w-14 shrink-0 place-items-center rounded-full bg-brand-50 text-lg font-semibold text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">
                    ∞
                </span>
            ) : (
                <Ring
                    percent={percent}
                    color={nearLimit ? RING_AMBER : RING_BRAND}
                    label={label}
                />
            )}

            <div className="min-w-0">
                <div className="truncate text-sm font-semibold">{label}</div>
                <div className="mt-0.5 text-xs text-muted-foreground tabular-nums">
                    {used} of {isUnlimited ? 'unlimited' : limit} used
                </div>
            </div>
        </SoftTile>
    );
}

function FactTile({
    icon: Icon,
    label,
    value,
    note,
}: {
    icon: React.ComponentType<{ className?: string }>;
    label: string;
    value: string;
    note?: string;
}) {
    return (
        <SoftTile className="flex items-start gap-3">
            <span className="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">
                <Icon className="h-4 w-4" />
            </span>

            <div className="min-w-0">
                <div className="text-xs font-semibold text-muted-foreground">
                    {label}
                </div>
                <div className="mt-0.5 text-sm font-semibold">{value}</div>
                {note ? (
                    <div className="mt-0.5 text-xs text-muted-foreground">
                        {note}
                    </div>
                ) : null}
            </div>
        </SoftTile>
    );
}

export default function Current({
    subscription,
    pendingSubscription,
    usage,
    payments,
}: CurrentProps) {
    const plan = subscription?.plan;
    const isFree = plan ? parseFloat(plan.price) === 0 : false;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Billing" />

            <SettingsLayout>
                <div className="flex w-full flex-col gap-4">
                    {pendingSubscription ? (
                        <Panel>
                            <SoftTile className="flex flex-col gap-3 bg-amber-500/10 sm:flex-row sm:items-center sm:justify-between dark:bg-amber-500/15">
                                <div className="flex items-start gap-3">
                                    <span className="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-amber-500/15 text-amber-700 dark:text-amber-400">
                                        <Clock3 className="h-4 w-4" />
                                    </span>

                                    <p className="text-sm">
                                        Your move to{' '}
                                        <span className="font-semibold">
                                            {pendingSubscription.plan.name}
                                        </span>{' '}
                                        is waiting on payment.{' '}
                                        {plan
                                            ? `Your ${plan.name} plan stays active until it settles.`
                                            : 'It starts as soon as the payment settles.'}
                                    </p>
                                </div>

                                <Link
                                    href="/subscription/payment-status"
                                    className={pillButtonClass(
                                        'soft',
                                        'sm',
                                        'shrink-0',
                                    )}
                                >
                                    Check payment status
                                </Link>
                            </SoftTile>
                        </Panel>
                    ) : null}

                    <Panel>
                        {subscription && plan ? (
                            <>
                                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                    <div className="flex items-start gap-3">
                                        <span className="mt-0.5 grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">
                                            <Crown className="h-5 w-5" />
                                        </span>

                                        <div className="min-w-0">
                                            <div className="text-xs font-semibold text-muted-foreground">
                                                Current plan
                                            </div>
                                            <div className="mt-0.5 text-2xl tracking-tight">
                                                {plan.name}
                                            </div>
                                            <div className="mt-2">
                                                <StatusPill
                                                    status={subscription.status}
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    {pendingSubscription ? (
                                        <Link
                                            href="/subscription/payment-status"
                                            className={pillButtonClass(
                                                'soft',
                                                'md',
                                            )}
                                        >
                                            <Clock3 className="h-4 w-4" />
                                            Payment in progress
                                        </Link>
                                    ) : (
                                        <Link
                                            href="/subscription/select"
                                            className={pillButtonClass(
                                                'solid',
                                                'md',
                                            )}
                                        >
                                            <ArrowUpRight className="h-4 w-4" />
                                            Upgrade
                                        </Link>
                                    )}
                                </div>

                                <div className="mt-4 grid gap-2 sm:grid-cols-3">
                                    <FactTile
                                        icon={Wallet}
                                        label="Price"
                                        value={
                                            isFree
                                                ? 'Free'
                                                : formatMoney(
                                                      plan.price,
                                                      plan.currency_code,
                                                  )
                                        }
                                        note={
                                            isFree
                                                ? undefined
                                                : plan.billing_period ===
                                                    'yearly'
                                                  ? 'per year'
                                                  : 'per month'
                                        }
                                    />
                                    <FactTile
                                        icon={CalendarDays}
                                        label="Started"
                                        value={formatDate(
                                            subscription.starts_at,
                                        )}
                                    />
                                    <FactTile
                                        icon={CalendarClock}
                                        label="Next payment due"
                                        value={
                                            subscription.ends_at
                                                ? formatDate(
                                                      subscription.ends_at,
                                                  )
                                                : 'Does not expire'
                                        }
                                        note={
                                            subscription.ends_at
                                                ? 'No automatic renewal — pay again to continue.'
                                                : undefined
                                        }
                                    />
                                </div>
                            </>
                        ) : (
                            <EmptyState
                                icon={Crown}
                                title="No active plan"
                                description="Pick a plan to unlock Nilo."
                                action={
                                    <Link
                                        href="/subscription/select"
                                        className={pillButtonClass(
                                            'solid',
                                            'sm',
                                        )}
                                    >
                                        Choose a plan
                                    </Link>
                                }
                            />
                        )}
                    </Panel>

                    <Panel>
                        <PanelHeader
                            icon={Gauge}
                            title="Usage"
                            subtitle="What you have used against this plan's allowances. Document counts are for the company you are working in."
                        />

                        <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-3">
                            <UsageTile
                                label="Companies"
                                used={usage.companies.used}
                                limit={usage.companies.limit}
                            />
                            <UsageTile
                                label="Invoices"
                                used={usage.invoices.used}
                                limit={usage.invoices.limit}
                            />
                            <UsageTile
                                label="Quotations"
                                used={usage.quotations.used}
                                limit={usage.quotations.limit}
                            />
                            <UsageTile
                                label="Purchase orders"
                                used={usage.purchase_orders.used}
                                limit={usage.purchase_orders.limit}
                            />
                            <UsageTile
                                label="Invoice templates"
                                used={usage.invoice_templates.used}
                                limit={usage.invoice_templates.limit}
                            />
                            <UsageTile
                                label="Quotation templates"
                                used={usage.quotation_templates.used}
                                limit={usage.quotation_templates.limit}
                            />
                        </div>
                    </Panel>

                    <Panel>
                        <PanelHeader
                            icon={Receipt}
                            title="Payment history"
                            subtitle="Every payment made on this account."
                        />

                        {payments.data.length === 0 ? (
                            <EmptyState
                                icon={Receipt}
                                tone="muted"
                                title="No payments yet"
                                description="Payments you make for a plan will show up here."
                            />
                        ) : (
                            <div className="-mx-1 overflow-x-auto px-1">
                                <table className="w-full min-w-[44rem] border-separate border-spacing-y-1.5 text-sm">
                                    <thead>
                                        <tr className="text-left text-xs text-muted-foreground">
                                            <th className="px-3 pb-1 font-medium">
                                                Date
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
                                                Status
                                            </th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        {payments.data.map((payment) => {
                                            const convertedCharge =
                                                payment.charged_amount &&
                                                payment.charged_currency_code &&
                                                payment.charged_currency_code !==
                                                    payment.currency_code;

                                            return (
                                                <tr
                                                    key={payment.id}
                                                    className="group"
                                                >
                                                    <td
                                                        className={cn(
                                                            tableCellClass,
                                                            'rounded-l-2xl whitespace-nowrap',
                                                        )}
                                                    >
                                                        {formatDate(
                                                            payment.paid_at ??
                                                                payment.created_at,
                                                        )}
                                                    </td>
                                                    <td
                                                        className={
                                                            tableCellClass
                                                        }
                                                    >
                                                        {payment.plan?.name ??
                                                            '—'}
                                                    </td>
                                                    <td
                                                        className={
                                                            tableCellClass
                                                        }
                                                    >
                                                        <div className="font-semibold tabular-nums">
                                                            {formatMoney(
                                                                payment.amount,
                                                                payment.currency_code,
                                                            )}
                                                        </div>
                                                        {convertedCharge ? (
                                                            <div className="text-xs text-muted-foreground tabular-nums">
                                                                charged{' '}
                                                                {formatMoney(
                                                                    payment.charged_amount,
                                                                    payment.charged_currency_code as string,
                                                                )}
                                                            </div>
                                                        ) : null}
                                                        {payment.coupon_code ? (
                                                            <div className="text-xs text-muted-foreground">
                                                                coupon{' '}
                                                                {
                                                                    payment.coupon_code
                                                                }
                                                            </div>
                                                        ) : null}
                                                    </td>
                                                    <td
                                                        className={
                                                            tableCellClass
                                                        }
                                                    >
                                                        {METHOD_LABELS[
                                                            payment
                                                                .payment_method
                                                        ] ??
                                                            payment.payment_method}
                                                    </td>
                                                    <td
                                                        className={cn(
                                                            tableCellClass,
                                                            'text-muted-foreground',
                                                        )}
                                                    >
                                                        {payment.payment_reference ??
                                                            '—'}
                                                    </td>
                                                    <td
                                                        className={cn(
                                                            tableCellClass,
                                                            'rounded-r-2xl',
                                                        )}
                                                    >
                                                        <StatusPill
                                                            status={
                                                                payment.status
                                                            }
                                                        />
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}

                        <Pagination links={payments.links} className="mt-4" />
                    </Panel>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
