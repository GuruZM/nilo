import {
    Panel,
    PillButton,
    SoftTile,
    TotalRow,
    pillButtonClass,
} from '@/components/dashboard/primitives';
import { GateShell } from '@/components/subscription/gate-shell';
import { cn } from '@/lib/utils';
import { Head, Link, router } from '@inertiajs/react';
import { CheckCircle2, Clock3, XCircle } from 'lucide-react';

interface PaymentStatusProps {
    payment: {
        id: number;
        amount: string;
        currency_code: string;
        payment_method: string;
        status: string;
        created_at: string;
        plan: { name: string } | null;
    } | null;
    subscription: {
        status: string;
        plan: { name: string } | null;
    } | null;
}

const STATUS_CONFIG = {
    pending: {
        icon: Clock3,
        wellClass:
            'bg-amber-500/10 text-amber-700 dark:bg-amber-500/15 dark:text-amber-400',
        title: 'Payment pending confirmation',
        description:
            'Your payment has been submitted and is awaiting confirmation. This usually takes a few hours.',
    },
    confirmed: {
        icon: CheckCircle2,
        wellClass:
            'bg-emerald-500/10 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400',
        title: 'Payment confirmed',
        description:
            'Your payment has been confirmed and your subscription is now active.',
    },
    rejected: {
        icon: XCircle,
        wellClass:
            'bg-rose-500/10 text-rose-700 dark:bg-rose-500/15 dark:text-rose-400',
        title: 'Payment rejected',
        description:
            'Your payment could not be verified. Please try again or contact support.',
    },
} as const;

export default function PaymentStatus({ payment }: PaymentStatusProps) {
    const status = payment?.status ?? 'pending';
    const config =
        STATUS_CONFIG[status as keyof typeof STATUS_CONFIG] ??
        STATUS_CONFIG.pending;
    const Icon = config.icon;

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

                {payment ? (
                    <SoftTile className="mt-5 flex flex-col gap-2 text-left">
                        <TotalRow
                            label="Plan"
                            value={payment.plan?.name ?? '—'}
                        />
                        <TotalRow
                            label="Amount"
                            value={`K${parseFloat(payment.amount).toLocaleString()}`}
                            strong
                        />
                        <TotalRow
                            label="Method"
                            value={
                                payment.payment_method === 'airtel_money'
                                    ? 'Airtel Money'
                                    : 'Bank Transfer'
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

                    {status === 'rejected' ? (
                        <Link
                            href="/subscription/select"
                            className={pillButtonClass('solid', 'md', 'w-full')}
                        >
                            Try again
                        </Link>
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
