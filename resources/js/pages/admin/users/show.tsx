import {
    EmptyState,
    FormField,
    InitialsAvatar,
    Panel,
    PanelHeader,
    PillButton,
    SoftTile,
    StatusPill,
    TotalRow,
    fieldInputClass,
    pillButtonClass,
    tableCellClass,
} from '@/components/dashboard/primitives';
import NiloSpinner from '@/components/nilo-spinner';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, Plan } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Building2, CreditCard, Crown, User } from 'lucide-react';

interface UserShowProps {
    user: {
        id: number;
        name: string;
        email: string;
        created_at: string;
        subscription: {
            id: number;
            status: string;
            starts_at: string;
            ends_at: string | null;
            plan: Plan;
        } | null;
        payments: Array<{
            id: number;
            amount: string;
            payment_method: string;
            status: string;
            created_at: string;
            plan: { name: string };
        }>;
        owned_companies: Array<{
            id: number;
            name: string;
        }>;
    };
    plans: Plan[];
}

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

const methodLabel = (method: string): string => METHOD_LABELS[method] ?? method;

const STATUS_OPTIONS = [
    { value: 'active', label: 'Active' },
    { value: 'pending_payment', label: 'Pending payment' },
    { value: 'cancelled', label: 'Cancelled' },
    { value: 'expired', label: 'Expired' },
] as const;

/** The date input needs YYYY-MM-DD; the API sends a full ISO timestamp. */
const toDateInput = (value: string | null): string =>
    value ? value.slice(0, 10) : '';

const formatDate = (value: string | null): string =>
    value ? new Date(value).toLocaleDateString() : 'Never';

export default function UserShow({ user, plans }: UserShowProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Admin', href: '/admin' },
        { title: 'Users', href: '/admin/users' },
        { title: user.name, href: `/admin/users/${user.id}` },
    ];

    const { data, setData, post, processing, errors } = useForm({
        plan_id: user.subscription?.plan?.id ?? 0,
        status: user.subscription?.status ?? 'active',
        ends_at: toDateInput(user.subscription?.ends_at ?? null),
    });

    const selectedPlan = plans.find((plan) => plan.id === data.plan_id);

    // Complimentary plans carry a high sort_order so they sit last on the
    // pricing page, which buried them at the bottom of this picker. Grouping
    // them under their own heading keeps them findable.
    const listedPlans = plans.filter((plan) => plan.is_public);
    const complimentaryPlans = plans.filter((plan) => !plan.is_public);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/admin/users/${user.id}/subscription`);
    };

    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>
            <Head title={`Admin - ${user.name}`} />

            <div className="flex w-full flex-col gap-4 py-6">
                <Panel>
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-center gap-3">
                            <InitialsAvatar
                                name={user.name}
                                className="h-11 w-11 text-sm"
                            />
                            <div className="min-w-0">
                                <div className="truncate text-base font-semibold">
                                    {user.name}
                                </div>
                                <div className="truncate text-xs text-muted-foreground">
                                    {user.email}
                                </div>
                            </div>
                        </div>

                        <Link
                            href="/admin/users"
                            className={pillButtonClass('ghost', 'sm')}
                        >
                            <ArrowLeft className="h-3.5 w-3.5" />
                            All users
                        </Link>
                    </div>
                </Panel>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
                    <Panel className="lg:col-span-5">
                        <PanelHeader
                            icon={User}
                            title="Account"
                            subtitle="Registration details for this user."
                        />

                        <SoftTile className="flex flex-col gap-2">
                            <TotalRow label="Name" value={user.name} />
                            <TotalRow label="Email" value={user.email} />
                            <TotalRow
                                label="Joined"
                                value={new Date(
                                    user.created_at,
                                ).toLocaleDateString()}
                            />
                            <TotalRow
                                label="Companies"
                                value={user.owned_companies.length}
                            />
                        </SoftTile>

                        {user.owned_companies.length > 0 ? (
                            <div className="mt-3 flex flex-wrap gap-1.5">
                                {user.owned_companies.map((company) => (
                                    <span
                                        key={company.id}
                                        className="inline-flex items-center gap-1.5 rounded-full bg-muted/60 px-3 py-1.5 text-xs font-semibold text-muted-foreground dark:bg-white/5"
                                    >
                                        <Building2 className="h-3.5 w-3.5" />
                                        {company.name}
                                    </span>
                                ))}
                            </div>
                        ) : null}
                    </Panel>

                    <Panel className="lg:col-span-7">
                        <PanelHeader
                            icon={Crown}
                            title="Subscription"
                            subtitle="Changes apply immediately, without a payment."
                        />

                        {user.subscription ? (
                            <SoftTile className="mb-4 flex flex-col gap-2">
                                <TotalRow
                                    label="Plan"
                                    value={user.subscription.plan.name}
                                    strong
                                />
                                <div className="flex items-center justify-between gap-3">
                                    <span className="text-xs text-muted-foreground">
                                        Status
                                    </span>
                                    <StatusPill
                                        status={user.subscription.status}
                                    />
                                </div>
                                <TotalRow
                                    label="Started"
                                    value={formatDate(
                                        user.subscription.starts_at,
                                    )}
                                />
                                <TotalRow
                                    label="Expires"
                                    value={formatDate(
                                        user.subscription.ends_at,
                                    )}
                                />
                            </SoftTile>
                        ) : (
                            <p className="mb-4 text-sm text-muted-foreground">
                                No active subscription.
                            </p>
                        )}

                        <form
                            onSubmit={handleSubmit}
                            className="flex flex-col gap-3"
                        >
                            <div className="grid gap-3 sm:grid-cols-2">
                                <FormField
                                    label="Plan"
                                    htmlFor="plan_id"
                                    className="sm:col-span-2"
                                >
                                    <select
                                        id="plan_id"
                                        value={data.plan_id}
                                        onChange={(e) =>
                                            setData(
                                                'plan_id',
                                                Number(e.target.value),
                                            )
                                        }
                                        className={fieldInputClass}
                                    >
                                        <option value={0}>
                                            Select a plan…
                                        </option>

                                        {listedPlans.length > 0 ? (
                                            <optgroup label="Public plans">
                                                {listedPlans.map((plan) => (
                                                    <option
                                                        key={plan.id}
                                                        value={plan.id}
                                                    >
                                                        {plan.name}
                                                    </option>
                                                ))}
                                            </optgroup>
                                        ) : null}

                                        {complimentaryPlans.length > 0 ? (
                                            <optgroup label="Complimentary — admin assignment only">
                                                {complimentaryPlans.map(
                                                    (plan) => (
                                                        <option
                                                            key={plan.id}
                                                            value={plan.id}
                                                        >
                                                            {plan.name}
                                                        </option>
                                                    ),
                                                )}
                                            </optgroup>
                                        ) : null}
                                    </select>
                                    {errors.plan_id ? (
                                        <p className="text-xs text-destructive">
                                            {errors.plan_id}
                                        </p>
                                    ) : null}
                                </FormField>

                                <FormField label="Status" htmlFor="status">
                                    <select
                                        id="status"
                                        value={data.status}
                                        onChange={(e) =>
                                            setData('status', e.target.value)
                                        }
                                        className={fieldInputClass}
                                    >
                                        {STATUS_OPTIONS.map((option) => (
                                            <option
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </option>
                                        ))}
                                    </select>
                                    {errors.status ? (
                                        <p className="text-xs text-destructive">
                                            {errors.status}
                                        </p>
                                    ) : null}
                                </FormField>

                                <FormField label="Expires on" htmlFor="ends_at">
                                    <input
                                        id="ends_at"
                                        type="date"
                                        value={data.ends_at}
                                        onChange={(e) =>
                                            setData('ends_at', e.target.value)
                                        }
                                        className={fieldInputClass}
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Leave empty so the plan never expires.
                                    </p>
                                    {errors.ends_at ? (
                                        <p className="text-xs text-destructive">
                                            {errors.ends_at}
                                        </p>
                                    ) : null}
                                </FormField>
                            </div>

                            {selectedPlan && !selectedPlan.is_public ? (
                                <p className="rounded-xl bg-sky-50 px-3 py-2 text-xs text-sky-800 dark:bg-sky-500/10 dark:text-sky-300">
                                    {selectedPlan.name} is a complimentary plan.
                                    It is never listed on the pricing page and
                                    takes no payment.
                                </p>
                            ) : null}

                            <div>
                                <PillButton
                                    type="submit"
                                    disabled={processing || data.plan_id === 0}
                                >
                                    {processing ? (
                                        <>
                                            <NiloSpinner size={16} />
                                            Saving…
                                        </>
                                    ) : (
                                        'Update subscription'
                                    )}
                                </PillButton>
                            </div>
                        </form>
                    </Panel>
                </div>

                <Panel>
                    <PanelHeader
                        icon={CreditCard}
                        title="Payment history"
                        subtitle="Every payment this user has submitted."
                    />

                    {user.payments.length === 0 ? (
                        <EmptyState
                            icon={CreditCard}
                            tone="muted"
                            title="No payments"
                            description="This user has not submitted a payment yet."
                        />
                    ) : (
                        <div className="-mx-1 overflow-x-auto px-1">
                            <table className="w-full min-w-[38rem] border-separate border-spacing-y-1.5 text-sm">
                                <thead>
                                    <tr className="text-left text-xs text-muted-foreground">
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
                                            Status
                                        </th>
                                        <th className="px-3 pb-1 text-right font-medium">
                                            Date
                                        </th>
                                    </tr>
                                </thead>

                                <tbody>
                                    {user.payments.map((payment) => (
                                        <tr key={payment.id} className="group">
                                            <td
                                                className={cn(
                                                    tableCellClass,
                                                    'rounded-l-2xl font-semibold',
                                                )}
                                            >
                                                {payment.plan.name}
                                            </td>
                                            <td
                                                className={cn(
                                                    tableCellClass,
                                                    'tabular-nums',
                                                )}
                                            >
                                                K
                                                {parseFloat(
                                                    payment.amount,
                                                ).toLocaleString()}
                                            </td>
                                            <td className={tableCellClass}>
                                                {methodLabel(
                                                    payment.payment_method,
                                                )}
                                            </td>
                                            <td className={tableCellClass}>
                                                <StatusPill
                                                    status={payment.status}
                                                />
                                            </td>
                                            <td
                                                className={cn(
                                                    tableCellClass,
                                                    'rounded-r-2xl text-right text-muted-foreground',
                                                )}
                                            >
                                                {new Date(
                                                    payment.created_at,
                                                ).toLocaleDateString()}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Panel>
            </div>
        </AppSidebarLayout>
    );
}
