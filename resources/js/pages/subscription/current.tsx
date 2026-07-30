import {
    Panel,
    PanelHeader,
    Ring,
    SoftTile,
    StatusPill,
    pillButtonClass,
} from '@/components/dashboard/primitives';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { Plan, UsageData, type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowUpRight, Crown, Gauge } from 'lucide-react';

interface CurrentProps {
    subscription: {
        id: number;
        status: string;
        starts_at: string;
        ends_at: string | null;
        plan: Plan;
    } | null;
    usage: UsageData;
    plans: Plan[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Subscription', href: '/subscription' },
];

/** SVG strokes take literal colours, matching the dashboard's ring meters. */
const RING_BRAND = '#00417d';
const RING_AMBER = '#d97706';

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

export default function Current({ subscription, usage }: CurrentProps) {
    const plan = subscription?.plan;

    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>
            <Head title="My Subscription" />

            <div className="flex w-full flex-col gap-4 py-6">
                <Panel>
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
                                    {plan?.name ?? 'No plan'}
                                </div>

                                {subscription ? (
                                    <div className="mt-2 flex flex-wrap items-center gap-2">
                                        <StatusPill
                                            status={subscription.status}
                                        />
                                        {subscription.status ===
                                        'pending_payment' ? (
                                            <Link
                                                href="/subscription/payment-status"
                                                className="text-xs font-semibold text-brand-600 hover:text-brand-700 dark:text-brand-300"
                                            >
                                                Check payment status
                                            </Link>
                                        ) : null}
                                    </div>
                                ) : null}
                            </div>
                        </div>

                        <Link
                            href="/subscription/select"
                            className={pillButtonClass('solid', 'md')}
                        >
                            <ArrowUpRight className="h-4 w-4" />
                            Upgrade
                        </Link>
                    </div>
                </Panel>

                <Panel>
                    <PanelHeader
                        icon={Gauge}
                        title="Usage"
                        subtitle="What you have used against this plan's allowances."
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
            </div>
        </AppSidebarLayout>
    );
}
