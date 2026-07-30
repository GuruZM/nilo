import { Money } from '@/components/money';
import { register } from '@/routes';
import { type Plan } from '@/types';
import { Link } from '@inertiajs/react';
import { motion, useReducedMotion } from 'framer-motion';
import { ArrowRight, Check, Sparkles } from 'lucide-react';
import { useState } from 'react';

const priceClass = 'text-4xl font-bold tracking-tight';

/**
 * The plan price with its own currency code superscripted after it, the way
 * amounts read across the dashboard. Plan prices are whole figures, so the
 * decimals are dropped rather than trailing every card with `.00`.
 */
function PlanPrice({ plan, highlight }: { plan: Plan; highlight: boolean }) {
    if (plan.slug === 'enterprise') {
        return <span className={priceClass}>Custom</span>;
    }

    const amount = parseFloat(plan.price);

    if (amount === 0) {
        return <span className={priceClass}>Free</span>;
    }

    return (
        <Money
            amount={amount}
            currency={{ code: plan.currency_code, precision: 0 }}
            className={priceClass}
            codeClassName={highlight ? 'text-brand-200' : undefined}
        />
    );
}

function periodLabel(plan: Plan): string | null {
    if (plan.slug === 'enterprise' || parseFloat(plan.price) === 0) {
        return null;
    }

    return plan.billing_period === 'yearly' ? '/yr' : '/mo';
}

function ctaLabel(plan: Plan): string {
    if (plan.slug === 'enterprise') {
        return 'Contact us';
    }

    return parseFloat(plan.price) === 0 ? 'Start free' : 'Subscribe';
}

function columnClass(count: number): string {
    if (count <= 2) {
        return 'lg:grid-cols-2';
    }

    if (count === 3) {
        return 'lg:grid-cols-3';
    }

    return count % 3 === 0 ? 'lg:grid-cols-3' : 'lg:grid-cols-4';
}

export default function Pricing({ plans }: { plans: Plan[] }) {
    const reducedMotion = useReducedMotion();
    const periods = Array.from(
        new Set(plans.map((plan) => plan.billing_period)),
    );
    const [period, setPeriod] = useState(periods[0] ?? 'monthly');

    const reveal = {
        initial: { opacity: 0, y: reducedMotion ? 0 : 32 },
        whileInView: { opacity: 1, y: 0 },
        viewport: { once: true, margin: '-80px' },
    };

    if (!plans?.length) {
        return null;
    }

    const visible =
        periods.length > 1
            ? plans.filter((plan) => plan.billing_period === period)
            : plans;

    return (
        <section
            id="pricing"
            className="relative overflow-hidden bg-background py-24"
        >
            <div
                aria-hidden
                className="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-border to-transparent"
            />

            <div className="relative mx-auto max-w-7xl px-6">
                <motion.h2
                    {...reveal}
                    transition={{ duration: 0.45, ease: 'easeOut' }}
                    className="max-w-2xl text-3xl font-bold text-balance text-foreground sm:text-4xl"
                >
                    Room for everything the business keeps
                </motion.h2>
                <motion.p
                    {...reveal}
                    transition={{
                        duration: 0.45,
                        ease: 'easeOut',
                        delay: 0.12,
                    }}
                    className="mt-5 max-w-xl text-lg text-muted-foreground"
                >
                    Every plan is the whole product. What changes is how much
                    the repository holds, and how soon the things you ask for
                    get built. Start free and move up when the business does.
                </motion.p>

                {periods.length > 1 && (
                    <motion.div
                        {...reveal}
                        transition={{
                            duration: 0.4,
                            ease: 'easeOut',
                            delay: 0.16,
                        }}
                        className="mt-8 inline-flex rounded-xl border border-border bg-card p-1"
                        role="group"
                        aria-label="Billing period"
                    >
                        {periods.map((option) => (
                            <button
                                key={option}
                                type="button"
                                onClick={() => setPeriod(option)}
                                aria-pressed={period === option}
                                className={`cursor-pointer rounded-lg px-4 py-2 text-sm font-semibold capitalize transition-colors duration-200 focus-visible:ring-2 focus-visible:ring-brand focus-visible:outline-none ${
                                    period === option
                                        ? 'bg-brand text-white dark:bg-brand-400 dark:text-brand-950'
                                        : 'text-muted-foreground hover:text-foreground'
                                }`}
                            >
                                {option}
                            </button>
                        ))}
                    </motion.div>
                )}

                <div
                    className={`mt-12 grid grid-cols-1 gap-6 sm:grid-cols-2 ${columnClass(visible.length)}`}
                >
                    {visible.map((plan, i) => {
                        const highlight = plan.is_popular;
                        const label = periodLabel(plan);
                        const href =
                            plan.slug === 'enterprise'
                                ? '/subscription/enterprise'
                                : register();
                        const features = plan.features ?? [];

                        return (
                            <motion.div
                                key={plan.id}
                                {...reveal}
                                transition={{
                                    duration: 0.45,
                                    ease: 'easeOut',
                                    delay: reducedMotion ? 0 : i * 0.08,
                                }}
                                whileHover={reducedMotion ? {} : { y: -6 }}
                                className={`relative flex flex-col overflow-hidden rounded-2xl p-8 transition-[box-shadow,border-color] duration-300 ${
                                    highlight
                                        ? 'bg-gradient-to-b from-brand-600 to-brand-900 text-white shadow-xl shadow-brand-900/25 hover:shadow-2xl hover:shadow-brand-900/35'
                                        : 'border border-border bg-card shadow-sm shadow-brand-900/5 hover:border-brand/30 hover:shadow-xl hover:shadow-brand-900/10'
                                }`}
                            >
                                {highlight && (
                                    <>
                                        <span
                                            aria-hidden
                                            className="pointer-events-none absolute -top-24 -right-16 h-56 w-56 rounded-full bg-brand-300/20 blur-3xl"
                                        />
                                        <span className="inline-flex w-fit items-center gap-1.5 rounded-md bg-white/15 px-2.5 py-1 text-[11px] font-bold tracking-wider text-white uppercase backdrop-blur-sm">
                                            <Sparkles className="h-3 w-3" />
                                            Most popular
                                        </span>
                                    </>
                                )}

                                <h3
                                    className={`text-xl font-bold ${highlight ? 'mt-4 text-white' : 'text-foreground'}`}
                                >
                                    {plan.name}
                                </h3>

                                {plan.description && (
                                    <p
                                        className={`mt-2 text-sm leading-relaxed ${
                                            highlight
                                                ? 'text-brand-100'
                                                : 'text-muted-foreground'
                                        }`}
                                    >
                                        {plan.description}
                                    </p>
                                )}

                                <div className="mt-6 flex items-baseline gap-1">
                                    <PlanPrice
                                        plan={plan}
                                        highlight={highlight}
                                    />
                                    {label && (
                                        <span
                                            className={`text-sm font-medium ${
                                                highlight
                                                    ? 'text-brand-200'
                                                    : 'text-muted-foreground'
                                            }`}
                                        >
                                            {label}
                                        </span>
                                    )}
                                </div>

                                <Link
                                    href={href}
                                    className={`group/cta mt-6 inline-flex items-center justify-center gap-2 rounded-lg px-6 py-3 text-sm font-semibold transition-all duration-300 hover:-translate-y-0.5 focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none active:translate-y-0 ${
                                        highlight
                                            ? 'bg-white text-brand-900 shadow-lg shadow-brand-950/30 hover:bg-brand-50 hover:shadow-xl focus-visible:ring-white focus-visible:ring-offset-brand-800'
                                            : 'border-2 border-brand text-brand hover:bg-brand hover:text-white hover:shadow-lg hover:shadow-brand-900/20 focus-visible:ring-brand focus-visible:ring-offset-background dark:border-brand-300 dark:text-brand-300 dark:hover:bg-brand-300 dark:hover:text-brand-950'
                                    }`}
                                >
                                    {ctaLabel(plan)}
                                    <ArrowRight className="h-4 w-4 transition-transform duration-300 group-hover/cta:translate-x-1" />
                                </Link>

                                {features.length > 0 && (
                                    <ul
                                        className={`mt-8 flex flex-col gap-2.5 border-t pt-6 text-sm ${
                                            highlight
                                                ? 'border-white/15 text-brand-100'
                                                : 'border-border text-muted-foreground'
                                        }`}
                                    >
                                        {features.map((feature) => (
                                            <li
                                                key={feature}
                                                className="flex items-start gap-2.5"
                                            >
                                                <Check
                                                    className={`mt-0.5 h-4 w-4 shrink-0 ${
                                                        highlight
                                                            ? 'text-brand-300'
                                                            : 'text-brand dark:text-brand-300'
                                                    }`}
                                                />
                                                {feature}
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </motion.div>
                        );
                    })}
                </div>

                <motion.p
                    {...reveal}
                    transition={{ duration: 0.4, ease: 'easeOut' }}
                    className="mt-8 text-sm text-muted-foreground"
                >
                    Change or cancel at any time. Moving between plans changes
                    what you can add next; everything already on file stays on
                    file.
                </motion.p>
            </div>
        </section>
    );
}
