import {
    FormField,
    IconButton,
    Panel,
    PanelHeader,
    PillButton,
    fieldInputClass,
    pillButtonClass,
} from '@/components/dashboard/primitives';
import NiloSpinner from '@/components/nilo-spinner';
import { cn } from '@/lib/utils';
import { type Plan } from '@/types';
import { Link, useForm } from '@inertiajs/react';
import {
    Coins,
    Gauge,
    ListChecks,
    Plus,
    Sparkles,
    Tag,
    Trash2,
} from 'lucide-react';

export interface CurrencyOption {
    code: string;
    name: string;
    symbol: string | null;
}

/** -1 is the sentinel the limit checks treat as unlimited. */
const UNLIMITED = -1;

type PlanFormData = {
    name: string;
    slug: string;
    description: string;
    price: string;
    currency_code: string;
    billing_period: string;
    max_companies: number;
    max_invoices: number;
    max_quotations: number;
    max_invoice_templates: number;
    max_quotation_templates: number;
    can_upload_custom_template: boolean;
    is_active: boolean;
    is_public: boolean;
    is_popular: boolean;
    sort_order: number;
    features: string[];
};

const LIMIT_FIELDS = [
    { key: 'max_companies', label: 'Companies' },
    { key: 'max_invoices', label: 'Invoices' },
    { key: 'max_quotations', label: 'Quotations' },
    { key: 'max_invoice_templates', label: 'Invoice templates' },
    { key: 'max_quotation_templates', label: 'Quotation templates' },
] as const;

const slugify = (value: string): string =>
    value
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');

function Toggle({
    checked,
    onChange,
    label,
    hint,
    disabled = false,
}: {
    checked: boolean;
    onChange: (next: boolean) => void;
    label: string;
    hint?: string;
    disabled?: boolean;
}) {
    return (
        <label
            className={cn(
                'flex items-start gap-3',
                disabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer',
            )}
        >
            <input
                type="checkbox"
                checked={checked}
                onChange={(e) => onChange(e.target.checked)}
                disabled={disabled}
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

export function PlanForm({
    plan,
    currencies,
    defaultSortOrder = 0,
    activeSubscriptions = 0,
}: {
    plan?: Plan;
    currencies: CurrencyOption[];
    defaultSortOrder?: number;
    activeSubscriptions?: number;
}) {
    const isEdit = Boolean(plan);

    const { data, setData, post, put, processing, errors } =
        useForm<PlanFormData>({
            name: plan?.name ?? '',
            slug: plan?.slug ?? '',
            description: plan?.description ?? '',
            price: plan?.price ?? '0',
            currency_code: plan?.currency_code ?? currencies[0]?.code ?? 'ZMW',
            billing_period: plan?.billing_period ?? 'monthly',
            max_companies: plan?.max_companies ?? 1,
            max_invoices: plan?.max_invoices ?? 1,
            max_quotations: plan?.max_quotations ?? 1,
            max_invoice_templates: plan?.max_invoice_templates ?? 1,
            max_quotation_templates: plan?.max_quotation_templates ?? 1,
            can_upload_custom_template:
                plan?.can_upload_custom_template ?? false,
            is_active: plan?.is_active ?? true,
            is_public: plan?.is_public ?? true,
            is_popular: plan?.is_popular ?? false,
            sort_order: plan?.sort_order ?? defaultSortOrder,
            features: plan?.features?.length ? plan.features : [''],
        });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (isEdit && plan) {
            put(`/admin/plans/${plan.id}`);

            return;
        }

        post('/admin/plans');
    };

    const setFeature = (index: number, value: string) => {
        setData(
            'features',
            data.features.map((f, i) => (i === index ? value : f)),
        );
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-4">
            <Panel>
                <PanelHeader
                    icon={Tag}
                    title="Plan details"
                    subtitle="Name and description appear on the pricing card."
                />

                <div className="grid gap-4 sm:grid-cols-2">
                    <FormField label="Name" htmlFor="name" required>
                        <input
                            id="name"
                            value={data.name}
                            onChange={(e) => {
                                setData('name', e.target.value);

                                if (!isEdit) {
                                    setData('slug', slugify(e.target.value));
                                }
                            }}
                            className={fieldInputClass}
                            placeholder="Growth"
                        />
                        {errors.name ? (
                            <p className="text-xs text-destructive">
                                {errors.name}
                            </p>
                        ) : null}
                    </FormField>

                    <FormField label="Slug" htmlFor="slug" required={!isEdit}>
                        <input
                            id="slug"
                            value={data.slug}
                            onChange={(e) => setData('slug', e.target.value)}
                            className={cn(
                                fieldInputClass,
                                isEdit && 'opacity-60',
                            )}
                            placeholder="growth"
                            disabled={isEdit}
                        />
                        <p className="text-xs text-muted-foreground">
                            {isEdit
                                ? 'Fixed after creation — billing and feature checks key off it.'
                                : 'Lowercase, hyphenated. Used in URLs and feature checks.'}
                        </p>
                        {errors.slug ? (
                            <p className="text-xs text-destructive">
                                {errors.slug}
                            </p>
                        ) : null}
                    </FormField>

                    <FormField
                        label="Description"
                        htmlFor="description"
                        className="sm:col-span-2"
                    >
                        <textarea
                            id="description"
                            value={data.description}
                            onChange={(e) =>
                                setData('description', e.target.value)
                            }
                            rows={2}
                            className={cn(fieldInputClass, 'h-auto py-2')}
                            placeholder="For growing businesses that need more capacity."
                        />
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
                    icon={Coins}
                    title="Pricing"
                    subtitle="A price of zero renders as “Free” on the pricing page."
                />

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <FormField label="Price" htmlFor="price" required>
                        <input
                            id="price"
                            type="number"
                            min="0"
                            step="0.01"
                            value={data.price}
                            onChange={(e) => setData('price', e.target.value)}
                            className={fieldInputClass}
                        />
                        {errors.price ? (
                            <p className="text-xs text-destructive">
                                {errors.price}
                            </p>
                        ) : null}
                    </FormField>

                    <FormField
                        label="Currency"
                        htmlFor="currency_code"
                        required
                    >
                        <select
                            id="currency_code"
                            value={data.currency_code}
                            onChange={(e) =>
                                setData('currency_code', e.target.value)
                            }
                            className={fieldInputClass}
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
                        {errors.currency_code ? (
                            <p className="text-xs text-destructive">
                                {errors.currency_code}
                            </p>
                        ) : null}
                    </FormField>

                    <FormField
                        label="Billing period"
                        htmlFor="billing_period"
                        required
                    >
                        <select
                            id="billing_period"
                            value={data.billing_period}
                            onChange={(e) =>
                                setData('billing_period', e.target.value)
                            }
                            className={fieldInputClass}
                        >
                            <option value="monthly">Monthly</option>
                            <option value="yearly">Yearly</option>
                        </select>
                    </FormField>

                    <FormField label="Sort order" htmlFor="sort_order" required>
                        <input
                            id="sort_order"
                            type="number"
                            min="0"
                            value={data.sort_order}
                            onChange={(e) =>
                                setData('sort_order', Number(e.target.value))
                            }
                            className={fieldInputClass}
                        />
                        <p className="text-xs text-muted-foreground">
                            Low to high, left to right.
                        </p>
                    </FormField>
                </div>
            </Panel>

            <Panel>
                <PanelHeader
                    icon={Gauge}
                    title="Limits"
                    subtitle="These enforce what subscribers can create. Use −1 for unlimited."
                />

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {LIMIT_FIELDS.map(({ key, label }) => {
                        const value = data[key];
                        const unlimited = value === UNLIMITED;

                        return (
                            <FormField
                                key={key}
                                label={label}
                                htmlFor={key}
                                required
                            >
                                <div className="flex items-center gap-2">
                                    <input
                                        id={key}
                                        type="number"
                                        min="-1"
                                        value={value}
                                        onChange={(e) =>
                                            setData(key, Number(e.target.value))
                                        }
                                        className={cn(
                                            fieldInputClass,
                                            unlimited && 'opacity-60',
                                        )}
                                        disabled={unlimited}
                                    />
                                    <PillButton
                                        variant={unlimited ? 'solid' : 'ghost'}
                                        size="sm"
                                        onClick={() =>
                                            setData(
                                                key,
                                                unlimited ? 1 : UNLIMITED,
                                            )
                                        }
                                        aria-pressed={unlimited}
                                    >
                                        ∞
                                    </PillButton>
                                </div>
                                {errors[key] ? (
                                    <p className="text-xs text-destructive">
                                        {errors[key]}
                                    </p>
                                ) : null}
                            </FormField>
                        );
                    })}
                </div>
            </Panel>

            <Panel>
                <PanelHeader
                    icon={ListChecks}
                    title="Feature list"
                    subtitle="The ticked bullets shown on the pricing card."
                    action={
                        <PillButton
                            variant="soft"
                            size="sm"
                            onClick={() =>
                                setData('features', [...data.features, ''])
                            }
                        >
                            <Plus className="h-3.5 w-3.5" />
                            Add feature
                        </PillButton>
                    }
                />

                <div className="space-y-2">
                    {data.features.map((feature, index) => (
                        <div key={index} className="flex items-center gap-2">
                            <input
                                value={feature}
                                onChange={(e) =>
                                    setFeature(index, e.target.value)
                                }
                                className={fieldInputClass}
                                placeholder="Unlimited invoices"
                                aria-label={`Feature ${index + 1}`}
                            />
                            <IconButton
                                label={`Remove feature ${index + 1}`}
                                onClick={() =>
                                    setData(
                                        'features',
                                        data.features.filter(
                                            (_, i) => i !== index,
                                        ),
                                    )
                                }
                            >
                                <Trash2 className="h-4 w-4" />
                            </IconButton>
                        </div>
                    ))}

                    {data.features.length === 0 ? (
                        <p className="text-xs text-muted-foreground">
                            No features yet — the card will show only the price.
                        </p>
                    ) : null}
                </div>
            </Panel>

            <Panel>
                <PanelHeader
                    icon={Sparkles}
                    title="Visibility"
                    subtitle="Controls how the plan appears on the public pricing page."
                />

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Toggle
                        checked={data.is_active}
                        onChange={(next) => setData('is_active', next)}
                        label="Active"
                        hint="Inactive plans are hidden everywhere and cannot be assigned."
                    />
                    <Toggle
                        checked={data.is_public}
                        onChange={(next) => {
                            setData('is_public', next);

                            if (!next) {
                                setData('is_popular', false);
                            }
                        }}
                        label="Listed publicly"
                        hint="Turn off for a complimentary plan only admins can assign."
                    />
                    <Toggle
                        checked={data.is_popular}
                        onChange={(next) => setData('is_popular', next)}
                        label="Popular"
                        hint={
                            data.is_public
                                ? 'Highlights this card. Only one plan can hold it.'
                                : 'Unavailable — unlisted plans have no pricing card.'
                        }
                        disabled={!data.is_public}
                    />
                    <Toggle
                        checked={data.can_upload_custom_template}
                        onChange={(next) =>
                            setData('can_upload_custom_template', next)
                        }
                        label="Custom template uploads"
                        hint="Lets subscribers upload their own templates."
                    />
                </div>

                {!data.is_public ? (
                    <p className="mt-4 rounded-xl bg-sky-50 px-3 py-2 text-xs text-sky-800 dark:bg-sky-500/10 dark:text-sky-300">
                        This plan stays off the pricing page and cannot be
                        purchased. Assign it from a user’s admin page.
                    </p>
                ) : null}

                {isEdit && activeSubscriptions > 0 ? (
                    <p className="mt-4 rounded-xl bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">
                        {activeSubscriptions} active subscription(s) are on this
                        plan. Lowering a limit applies to them immediately.
                    </p>
                ) : null}
            </Panel>

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
                        'Create plan'
                    )}
                </PillButton>
                <Link
                    href="/admin/plans"
                    className={pillButtonClass('ghost', 'md')}
                >
                    Cancel
                </Link>
            </div>
        </form>
    );
}
