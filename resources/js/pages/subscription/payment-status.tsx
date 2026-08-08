import {
    Panel,
    PillButton,
    SoftTile,
    TotalRow,
    pillButtonClass,
} from '@/components/dashboard/primitives';
import { GateShell } from '@/components/subscription/gate-shell';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import { Head, Link, router } from '@inertiajs/react';
import { CheckCircle2, Clock3, XCircle } from 'lucide-react';

interface PaymentStatusProps {
    payment: {
        id: number;
        amount: string;
        currency_code: string;
        charged_amount: string | null;
        charged_currency_code: string | null;
        payment_method: string;
        status: string;
        gateway_status: string | null;
        created_at: string;
        plan: { name: string } | null;
    } | null;
    subscription: {
        status: string;
        plan: { name: string } | null;
    } | null;
    /** DPO's own explanation of a failure, safe to show the customer. */
    gatewayMessage?: string | null;
}

const AMBER =
    'bg-amber-500/10 text-amber-700 dark:bg-amber-500/15 dark:text-amber-400';
const EMERALD =
    'bg-emerald-500/10 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400';
const ROSE =
    'bg-rose-500/10 text-rose-700 dark:bg-rose-500/15 dark:text-rose-400';

const STATUS_CONFIG = {
    pending: {
        icon: Clock3,
        wellClass: AMBER,
        title: 'Payment pending confirmation',
        description:
            'Your payment has been submitted and is awaiting confirmation. This usually takes a few hours.',
    },
    confirmed: {
        icon: CheckCircle2,
        wellClass: EMERALD,
        title: 'Payment confirmed',
        description:
            'Your payment has been confirmed and your subscription is now active.',
    },
    rejected: {
        icon: XCircle,
        wellClass: ROSE,
        title: 'Payment rejected',
        description:
            'Your payment could not be verified. Please try again or contact support.',
    },
} as const;

/**
 * A gateway payment is never waiting on a person, so the manual copy above —
 * "awaiting confirmation, usually a few hours" — would be a lie for it. These
 * key off what DPO says rather than off the admin queue.
 */
const GATEWAY_CONFIG = {
    pending: {
        icon: Clock3,
        wellClass: AMBER,
        title: 'Waiting for your payment',
        description:
            'We have not heard back from the payment provider yet. If you did not finish paying, you can pick up where you left off.',
    },
    paid: STATUS_CONFIG.confirmed,
    cancelled: {
        icon: XCircle,
        wellClass: AMBER,
        title: 'Payment cancelled',
        description: 'You cancelled before the payment went through.',
    },
    expired: {
        icon: XCircle,
        wellClass: ROSE,
        title: 'Payment expired',
        description:
            'The payment session timed out before it was completed. Please start again.',
    },
    failed: {
        icon: XCircle,
        wellClass: ROSE,
        title: 'Payment was not completed',
        description:
            'Your bank or mobile money provider did not approve the payment.',
    },
} as const;

const METHOD_LABELS: Record<string, string> = {
    mobile_money: 'Mobile money',
    /** Retired in favour of mobile_money; kept so old payments still read. */
    airtel_money: 'Airtel Money',
    bank_transfer: 'Bank transfer',
    coupon: 'Coupon',
    dpo: 'Card / mobile money',
};

export default function PaymentStatus({
    payment,
    gatewayMessage,
}: PaymentStatusProps) {
    const status = payment?.status ?? 'pending';
    const isGateway = payment?.payment_method === 'dpo';
    const gatewayStatus = payment?.gateway_status ?? 'pending';

    const config = isGateway
        ? (GATEWAY_CONFIG[gatewayStatus as keyof typeof GATEWAY_CONFIG] ??
          GATEWAY_CONFIG.pending)
        : (STATUS_CONFIG[status as keyof typeof STATUS_CONFIG] ??
          STATUS_CONFIG.pending);

    const Icon = config.icon;
    const canResume = isGateway && gatewayStatus === 'pending';
    const canRetry = isGateway
        ? ['failed', 'cancelled', 'expired'].includes(gatewayStatus)
        : status === 'rejected';

    return (
        <GateShell title="Payment status">
            <Head title="Payment Status" />

            <Panel className="text-center">
                <span
                    className={cn(
                        'mx-auto grid h-16 w-16 place-items-center rounded-2xl',
                        config.wellClass,
                    )}
                >
                    <Icon className="h-8 w-8" />
                </span>

                <div className="mt-4 text-lg font-semibold">{config.title}</div>
                <p className="mx-auto mt-1 max-w-sm text-sm text-muted-foreground">
                    {config.description}
                </p>

                {gatewayMessage ? (
                    <p className="mx-auto mt-2 max-w-sm text-xs text-muted-foreground">
                        Provider said: {gatewayMessage}
                    </p>
                ) : null}

                {payment ? (
                    <SoftTile className="mt-5 flex flex-col gap-2 text-left">
                        <TotalRow
                            label="Plan"
                            value={payment.plan?.name ?? '—'}
                        />
                        <TotalRow
                            label="Amount"
                            value={formatMoney(
                                payment.charged_amount ?? payment.amount,
                                payment.charged_currency_code ??
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
                        <TotalRow
                            label="Submitted"
                            value={new Date(
                                payment.created_at,
                            ).toLocaleDateString()}
                        />
                    </SoftTile>
                ) : null}

                <div className="mt-5 flex flex-col items-center gap-2">
                    {status === 'confirmed' ? (
                        <Link
                            href="/dashboard"
                            className={pillButtonClass('solid', 'md', 'w-full')}
                        >
                            Go to dashboard
                        </Link>
                    ) : null}

                    {canRetry ? (
                        <Link
                            href="/subscription/select"
                            className={pillButtonClass('solid', 'md', 'w-full')}
                        >
                            Try again
                        </Link>
                    ) : null}

                    {canResume ? (
                        <PillButton
                            className="w-full"
                            onClick={() =>
                                router.post(
                                    `/subscription/payment/dpo/${payment!.id}/resume`,
                                )
                            }
                        >
                            Resume payment
                        </PillButton>
                    ) : null}

                    {status === 'pending' ? (
                        <>
                            <PillButton
                                variant="soft"
                                className="w-full"
                                onClick={() =>
                                    router.reload({ only: ['payment'] })
                                }
                            >
                                Refresh status
                            </PillButton>
                            <p className="text-xs text-muted-foreground">
                                You will be able to access the app once your
                                payment is confirmed.
                            </p>
                        </>
                    ) : null}
                </div>
            </Panel>
        </GateShell>
    );
}
