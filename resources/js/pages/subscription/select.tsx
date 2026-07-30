import {
    Panel,
    PillButton,
    pillButtonClass,
} from '@/components/dashboard/primitives';
import NiloSpinner from '@/components/nilo-spinner';
import { GateShell } from '@/components/subscription/gate-shell';
import { cn } from '@/lib/utils';
import { Plan } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Check, Crown, Sparkles, Star } from 'lucide-react';
import { useState } from 'react';

interface SelectProps {
    plans: Plan[];
    currentPlan: Plan | null;
}

function formatPrice(price: string): string {
    const amount = parseFloat(price);

    return amount === 0 ? 'Free' : `K${amount.toLocaleString()}`;
}

const PLAN_ICONS: Record<
    string,
    React.ComponentType<{ className?: string }>
> = {
    free: Star,
    standard: Sparkles,
    premium: Crown,
    enterprise: Crown,
};

export default function Select({ plans, currentPlan }: SelectProps) {
    /** Tracked per plan so only the pressed card shows the pending label. */
    const [submittingPlanId, setSubmittingPlanId] = useState<number | null>(
        null,
    );

    const handleSelect = (plan: Plan) => {
        setSubmittingPlanId(plan.id);

        router.post(
            '/subscription/subscribe',
            { plan_id: plan.id },
            { onFinish: () => setSubmittingPlanId(null) },
        );
    };

    return (
        <GateShell
            title="Choose your plan"
            subtitle="Select the plan that best fits your business. You can upgrade at any time."
            width="xl"
        >
            <Head title="Choose Your Plan" />

            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                {plans.map((plan) => {
                    const isCurrentPlan = currentPlan?.id === plan.id;
                    const isEnterprise = plan.slug === 'enterprise';
                    const features: string[] = plan.features ?? [];
                    const Icon = PLAN_ICONS[plan.slug] ?? Star;
                    const isSubmitting = submittingPlanId === plan.id;

                    return (
                        <Panel
                            key={plan.id}
                            className={cn(
                                'relative flex flex-col',
                                plan.is_popular &&
                                    'ring-2 ring-brand dark:ring-brand-400',
                            )}
                        >
                            {plan.is_popular ? (
                                <span className="absolute -top-2.5 left-1/2 -translate-x-1/2 rounded-full bg-brand px-3 py-0.5 text-xs font-semibold text-brand-foreground">
                                    Popular
                                </span>
                            ) : null}

                            <div className="flex items-center gap-2.5">
                                <span className="grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">
                                    <Icon className="h-4 w-4" />
                                </span>
                                <div className="text-sm font-semibold">
                                    {plan.name}
                                </div>
                            </div>

                            <div className="mt-4">
                                {isEnterprise ? (
                                    <span className="text-2xl tracking-tight">
                                        Custom
                                    </span>
                                ) : (
                                    <>
                                        <span className="text-3xl tracking-tight tabular-nums">
                                            {formatPrice(plan.price)}
                                        </span>
                                        {parseFloat(plan.price) > 0 ? (
                                            <span className="text-sm text-muted-foreground">
                                                {plan.billing_period ===
                                                'yearly'
                                                    ? ' /yr'
                                                    : ' /mo'}
                                            </span>
                                        ) : null}
                                    </>
                                )}
                            </div>

                            {plan.description ? (
                                <p className="mt-2 text-xs text-muted-foreground">
                                    {plan.description}
                                </p>
                            ) : null}

                            <ul className="mt-4 flex flex-1 flex-col gap-2">
                                {features.map((feature) => (
                                    <li
                                        key={feature}
                                        className="flex items-start gap-2 text-sm"
                                    >
                                        <Check className="mt-0.5 h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                                        {feature}
                                    </li>
                                ))}
                            </ul>

                            <div className="mt-5">
                                {isCurrentPlan ? (
                                    <PillButton
                                        variant="ghost"
                                        disabled
                                        className="w-full"
                                    >
                                        Current plan
                                    </PillButton>
                                ) : isEnterprise ? (
                                    <Link
                                        href="/subscription/enterprise"
                                        className={pillButtonClass(
                                            'soft',
                                            'md',
                                            'w-full',
                                        )}
                                    >
                                        Contact us
                                    </Link>
                                ) : (
                                    <PillButton
                                        variant={
                                            plan.is_popular ? 'solid' : 'soft'
                                        }
                                        className="w-full"
                                        disabled={submittingPlanId !== null}
                                        onClick={() => handleSelect(plan)}
                                    >
                                        {isSubmitting ? (
                                            <>
                                                <NiloSpinner size={16} />
                                                Processing…
                                            </>
                                        ) : plan.slug === 'free' ? (
                                            'Get started free'
                                        ) : (
                                            'Subscribe'
                                        )}
                                    </PillButton>
                                )}
                            </div>
                        </Panel>
                    );
                })}
            </div>
        </GateShell>
    );
}
