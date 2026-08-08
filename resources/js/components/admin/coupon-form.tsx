import {
    FormField,
    Panel,
    PanelHeader,
    PillButton,
    fieldInputClass,
    pillButtonClass,
} from '@/components/dashboard/primitives';
import NiloSpinner from '@/components/nilo-spinner';
import { cn } from '@/lib/utils';
import { type Coupon } from '@/types';
import { Link, useForm } from '@inertiajs/react';
import { CalendarClock, Layers, Percent, Sparkles } from 'lucide-react';
import { type CurrencyOption } from './plan-form';

export interface CouponPlanOption {
    id: number;
    name: string;
    price: string;
    currency_code: string;
    billing_period: string;
}

type CouponFormData = {
    code: string;
    description: string;
    discount_type: 'percentage' | 'fixed';
    discount_value: string;
    currency_code: string | null;
    starts_at: string;
    expires_at: string;
    max_redemptions: string;
    once_per_user: boolean;
    is_active: boolean;
    plan_ids: number[];
};

/** Eloquent hands back "2026-08-01T00:00:00.000000Z"; the input wants minutes. */
const toLocalInput = (value: string | null): string =>
    value ? new Date(value).toISOString().slice(0, 16) : '';

function Toggle({
    checked,
    onChange,
    label,
    hint,
}: {
    checked: boolean;
    onChange: (next: boolean) => void;
    label: string;
    hint?: string;
}) {
    return (
        <label className="flex cursor-pointer items-start gap-3">
            <input
                type="checkbox"
                checked={checked}
                onChange={(e) => onChange(e.target.checked)}
                className="mt-0.5 h-4 w-4 shrink-0 rounded accent-brand"
            />
            <span>
                <span className="block text-sm font-medium">{label}</span>
                {hint ? (
                    <span className="block text-xs text-muted-foreground">
                        {hint}
                    </span>
                ) : null}
            </span>
        </label>
    );
}

export function CouponForm({
    coupon,
    currencies,
    plans,
    redemptions = 0,
}: {
    coupon?: Coupon;
    currencies: CurrencyOption[];
    plans: CouponPlanOption[];
    redemptions?: number;
}) {
    const isEdit = Boolean(coupon);

    const { data, setData, post, put, processing, errors } =
        useForm<CouponFormData>({
            code: coupon?.code ?? '',
            description: coupon?.description ?? '',
            discount_type: coupon?.discount_type ?? 'percentage',
            discount_value: coupon?.discount_value ?? '',
            currency_code:
                coupon?.currency_code ?? currencies[0]?.code ?? 'ZMW',
            starts_at: toLocalInput(coupon?.starts_at ?? null),
            expires_at: toLocalInput(coupon?.expires_at ?? null),
            max_redemptions: coupon?.max_redemptions?.toString() ?? '',
            once_per_user: coupon?.once_per_user ?? true,
            is_active: coupon?.is_active ?? true,
            plan_ids: coupon?.plan_ids ?? [],
        });

    const isPercentage = data.discount_type === 'percentage';

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (isEdit && coupon) {
            put(`/admin/coupons/${coupon.id}`);

            return;
        }

        post('/admin/coupons');
    };

    const togglePlan = (planId: number) => {
        setData(
            'plan_ids',
            data.plan_ids.includes(planId)
                ? data.plan_ids.filter((id) => id !== planId)
                : [...data.plan_ids, planId],
        );
    };

    /** Shows the admin what the coupon is actually worth against each plan. */
    const previewFor = (plan: CouponPlanOption): string => {
        const price = parseFloat(plan.price);
        const value = parseFloat(data.discount_value);

        if (Number.isNaN(value) || value <= 0) {
            return '';
        }

        if (!isPercentage && data.currency_code !== plan.currency_code) {
            return 'wrong currency';
        }

        const off = Math.min(
            isPercentage ? price * (value / 100) : value,
            price,
        );

        return `${plan.currency_code} ${(price - off).toLocaleString(undefined, { maximumFractionDigits: 2 })} after discount`;
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-4">
            <Panel>
                <PanelHeader
                    icon={Sparkles}
                    title="Coupon details"
                    subtitle="The code customers type at checkout."
                />

                <div className="grid gap-4 sm:grid-cols-2">
                    <FormField label="Code" htmlFor="code" required>
                        <input
                            id="code"
                            value={data.code}
                            onChange={(e) =>
                                setData('code', e.target.value.toUpperCase())
                            }
                            className={cn(fieldInputClass, 'uppercase')}
                        />
                        <p className="text-xs text-muted-foreground">
                            Uppercase letters, numbers and hyphens. Customers
                            can type it in any case.
                        </p>
                        {errors.code ? (
                            <p className="text-xs text-destructive">
                                {errors.code}
                            </p>
                        ) : null}
                    </FormField>

                    <FormField label="Description" htmlFor="description">
                        <input
                            id="description"
                            value={data.description}
                            onChange={(e) =>
                                setData('description', e.target.value)
                            }
                            className={fieldInputClass}
                        />
                        <p className="text-xs text-muted-foreground">
                            Shown to the customer once the code is applied.
                        </p>
                        {errors.description ? (
                            <p className="text-xs text-destructive">
                                {errors.description}
                            </p>
                        ) : null}
                    </FormField>
                </div>
            </Panel>

            <Panel>
                <PanelHeader
                    icon={Percent}
                    title="Discount"
                    subtitle="A percentage works against any plan; a fixed amount only against plans priced in its currency."
                />

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <FormField label="Type" htmlFor="discount_type" required>
                        <select
                            id="discount_type"
                            value={data.discount_type}
                            onChange={(e) =>
                                setData(
                                    'discount_type',
                                    e.target
                                        .value as CouponFormData['discount_type'],
                                )
                            }
                            className={fieldInputClass}
                        >
                            <option value="percentage">Percentage off</option>
                            <option value="fixed">Fixed amount off</option>
                        </select>
                        {errors.discount_type ? (
                            <p className="text-xs text-destructive">
                                {errors.discount_type}
                            </p>
                        ) : null}
                    </FormField>

                    <FormField
                        label={isPercentage ? 'Percent off' : 'Amount off'}
                        htmlFor="discount_value"
                        required
                    >
                        <input
                            id="discount_value"
                            type="number"
                            min="0.01"
                            max={isPercentage ? 100 : undefined}
                            step="0.01"
                            value={data.discount_value}
                            onChange={(e) =>
                                setData('discount_value', e.target.value)
                            }
                            className={fieldInputClass}
                        />
                        {isPercentage ? (
                            <p className="text-xs text-muted-foreground">
                                100 makes the plan free and starts the
                                subscription without an admin confirming a
                                payment.
                            </p>
                        ) : null}
                        {errors.discount_value ? (
                            <p className="text-xs text-destructive">
                                {errors.discount_value}
                            </p>
                        ) : null}
                    </FormField>

                    <FormField
                        label="Currency"
                        htmlFor="currency_code"
                        required={!isPercentage}
                    >
                        <select
                            id="currency_code"
                            value={data.currency_code ?? ''}
                            onChange={(e) =>
                                setData('currency_code', e.target.value)
                            }
                            className={cn(
                                fieldInputClass,
                                isPercentage && 'opacity-60',
                            )}
                            disabled={isPercentage}
                        >
                            {currencies.map((currency) => (
                                <option
                                    key={currency.code}
                                    value={currency.code}
                                >
                                    {currency.code} — {currency.name}
                                </option>
                            ))}
                        </select>
                        <p className="text-xs text-muted-foreground">
                            {isPercentage
                                ? 'Not needed — a percentage follows the plan’s own currency.'
                                : 'The coupon only applies to plans priced in this currency.'}
                        </p>
                        {errors.currency_code ? (
                            <p className="text-xs text-destructive">
                                {errors.currency_code}
                            </p>
                        ) : null}
                    </FormField>
                </div>
            </Panel>

            <Panel>
                <PanelHeader
                    icon={CalendarClock}
                    title="Availability"
                    subtitle="Leave dates blank for a coupon that never starts or stops on its own."
                />

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <FormField label="Starts" htmlFor="starts_at">
                        <input
                            id="starts_at"
                            type="datetime-local"
                            value={data.starts_at}
                            onChange={(e) =>
                                setData('starts_at', e.target.value)
                            }
                            className={fieldInputClass}
                        />
                        {errors.starts_at ? (
                            <p className="text-xs text-destructive">
                                {errors.starts_at}
                            </p>
                        ) : null}
                    </FormField>

                    <FormField label="Expires" htmlFor="expires_at">
                        <input
                            id="expires_at"
                            type="datetime-local"
                            value={data.expires_at}
                            onChange={(e) =>
                                setData('expires_at', e.target.value)
                            }
                            className={fieldInputClass}
                        />
                        {errors.expires_at ? (
                            <p className="text-xs text-destructive">
                                {errors.expires_at}
                            </p>
                        ) : null}
                    </FormField>

                    <FormField
                        label="Redemption limit"
                        htmlFor="max_redemptions"
                    >
                        <input
                            id="max_redemptions"
                            type="number"
                            min="1"
                            value={data.max_redemptions}
                            onChange={(e) =>
                                setData('max_redemptions', e.target.value)
                            }
                            className={fieldInputClass}
                        />
                        <p className="text-xs text-muted-foreground">
                            {isEdit
                                ? `Redeemed ${redemptions} time(s) so far.`
                                : 'Blank means unlimited.'}
                        </p>
                        {errors.max_redemptions ? (
                            <p className="text-xs text-destructive">
                                {errors.max_redemptions}
                            </p>
                        ) : null}
                    </FormField>

                    <div className="flex flex-col justify-center gap-3">
                        <Toggle
                            checked={data.once_per_user}
                            onChange={(next) => setData('once_per_user', next)}
                            label="One use per account"
                        />
                        <Toggle
                            checked={data.is_active}
                            onChange={(next) => setData('is_active', next)}
                            label="Active"
                            hint="Turn off to retire the code without losing its history."
                        />
                    </div>
                </div>
            </Panel>

            <Panel>
                <PanelHeader
                    icon={Layers}
                    title="Plan restrictions"
                    subtitle="Tick nothing to let the coupon apply to every purchasable plan."
                />

                {plans.length === 0 ? (
                    <p className="py-6 text-center text-sm text-muted-foreground">
                        No purchasable plans yet.
                    </p>
                ) : (
                    <div className="grid gap-2 sm:grid-cols-2">
                        {plans.map((plan) => {
                            const selected = data.plan_ids.includes(plan.id);
                            const preview = previewFor(plan);

                            return (
                                <label
                                    key={plan.id}
                                    className={cn(
                                        'flex cursor-pointer items-start gap-3 rounded-2xl px-3 py-2.5 transition',
                                        selected
                                            ? 'bg-brand-50 dark:bg-brand-500/10'
                                            : 'bg-muted/50 hover:bg-muted dark:bg-white/5 dark:hover:bg-white/10',
                                    )}
                                >
                                    <input
                                        type="checkbox"
                                        checked={selected}
                                        onChange={() => togglePlan(plan.id)}
                                        className="mt-0.5 h-4 w-4 shrink-0 rounded accent-brand"
                                    />
                                    <span className="min-w-0">
                                        <span className="block text-sm font-medium">
                                            {plan.name}
                                        </span>
                                        <span className="block text-xs text-muted-foreground">
                                            {plan.currency_code}{' '}
                                            {parseFloat(
                                                plan.price,
                                            ).toLocaleString()}
                                            {plan.billing_period === 'yearly'
                                                ? ' /yr'
                                                : ' /mo'}
                                            {preview ? ` · ${preview}` : ''}
                                        </span>
                                    </span>
                                </label>
                            );
                        })}
                    </div>
                )}

                {data.plan_ids.length === 0 ? (
                    <p className="mt-4 rounded-xl bg-sky-50 px-3 py-2 text-xs text-sky-800 dark:bg-sky-500/10 dark:text-sky-300">
                        This coupon applies to every purchasable plan.
                    </p>
                ) : null}

                {errors.plan_ids ? (
                    <p className="mt-2 text-xs text-destructive">
                        {errors.plan_ids}
                    </p>
                ) : null}
            </Panel>

            {isEdit && redemptions > 0 ? (
                <p className="rounded-xl bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">
                    This coupon has already been redeemed {redemptions} time(s).
                    Changes apply to future redemptions only — past purchases
                    keep the discount they were given.
                </p>
            ) : null}

            <div className="flex items-center gap-2">
                <PillButton type="submit" disabled={processing}>
                    {processing ? (
                        <>
                            <NiloSpinner size={16} />
                            Saving…
                        </>
                    ) : isEdit ? (
                        'Save changes'
                    ) : (
                        'Create coupon'
                    )}
                </PillButton>
                <Link
                    href="/admin/coupons"
                    className={pillButtonClass('ghost', 'md')}
                >
                    Cancel
                </Link>
            </div>
        </form>
    );
}
