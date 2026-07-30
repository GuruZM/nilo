import {
    Chip,
    Panel,
    PanelHeader,
    PillButton,
    pillButtonClass,
} from '@/components/dashboard/primitives';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { type BreadcrumbItem, type Plan } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { CreditCard, Pencil, Plus, Trash2 } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Plans', href: '/admin/plans' },
];

const formatLimit = (value: number): string =>
    value === -1 ? 'Unlimited' : String(value);

const formatPrice = (plan: Plan): string => {
    const amount = parseFloat(plan.price);

    if (amount === 0) {
        return 'Free';
    }

    return new Intl.NumberFormat('en', {
        style: 'currency',
        currency: plan.currency_code,
        maximumFractionDigits: 0,
    }).format(amount);
};

export default function PlansIndex({ plans }: { plans: Plan[] }) {
    const handleDelete = (plan: Plan) => {
        if (!confirm(`Delete the ${plan.name} plan? This cannot be undone.`)) {
            return;
        }

        router.delete(`/admin/plans/${plan.id}`, { preserveScroll: true });
    };

    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin - Plans" />

            <div className="mx-auto flex w-full flex-col gap-4 py-6">
                <Panel>
                    <PanelHeader
                        icon={CreditCard}
                        title="Plans"
                        subtitle="Active plans appear on the public pricing page, ordered by sort order."
                        action={
                            <Link
                                href="/admin/plans/create"
                                className={pillButtonClass('solid', 'sm')}
                            >
                                <Plus className="h-3.5 w-3.5" />
                                New plan
                            </Link>
                        }
                    />

                    {plans.length === 0 ? (
                        <p className="py-8 text-center text-sm text-muted-foreground">
                            No plans yet. Create one to populate the pricing
                            page.
                        </p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="text-left text-xs text-muted-foreground">
                                        <th className="py-2 pr-3 font-medium">
                                            Plan
                                        </th>
                                        <th className="py-2 pr-3 font-medium">
                                            Price
                                        </th>
                                        <th className="py-2 pr-3 font-medium">
                                            Companies
                                        </th>
                                        <th className="py-2 pr-3 font-medium">
                                            Invoices
                                        </th>
                                        <th className="py-2 pr-3 font-medium">
                                            Quotations
                                        </th>
                                        <th className="py-2 pr-3 font-medium">
                                            Subscribers
                                        </th>
                                        <th className="py-2 pr-3 font-medium">
                                            Status
                                        </th>
                                        <th className="py-2 text-right font-medium">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {plans.map((plan) => (
                                        <tr
                                            key={plan.id}
                                            className="border-t border-border/60"
                                        >
                                            <td className="py-3 pr-3">
                                                <div className="font-semibold">
                                                    {plan.name}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {plan.slug}
                                                </div>
                                            </td>
                                            <td className="py-3 pr-3">
                                                {formatPrice(plan)}
                                                <span className="text-xs text-muted-foreground">
                                                    {parseFloat(plan.price) > 0
                                                        ? plan.billing_period ===
                                                          'yearly'
                                                            ? ' /yr'
                                                            : ' /mo'
                                                        : ''}
                                                </span>
                                            </td>
                                            <td className="py-3 pr-3">
                                                {formatLimit(
                                                    plan.max_companies,
                                                )}
                                            </td>
                                            <td className="py-3 pr-3">
                                                {formatLimit(plan.max_invoices)}
                                            </td>
                                            <td className="py-3 pr-3">
                                                {formatLimit(
                                                    plan.max_quotations,
                                                )}
                                            </td>
                                            <td className="py-3 pr-3">
                                                {plan.subscriptions_count ?? 0}
                                            </td>
                                            <td className="py-3 pr-3">
                                                <div className="flex flex-wrap gap-1.5">
                                                    <Chip
                                                        interactive={false}
                                                        active={plan.is_active}
                                                    >
                                                        {plan.is_active
                                                            ? 'Active'
                                                            : 'Hidden'}
                                                    </Chip>
                                                    {plan.is_active &&
                                                    !plan.is_public ? (
                                                        <Chip
                                                            interactive={false}
                                                        >
                                                            Complimentary
                                                        </Chip>
                                                    ) : null}
                                                    {plan.is_popular ? (
                                                        <Chip
                                                            interactive={false}
                                                        >
                                                            Popular
                                                        </Chip>
                                                    ) : null}
                                                </div>
                                            </td>
                                            <td className="py-3">
                                                <div className="flex justify-end gap-2">
                                                    <Link
                                                        href={`/admin/plans/${plan.id}/edit`}
                                                        className={pillButtonClass(
                                                            'ghost',
                                                            'sm',
                                                        )}
                                                    >
                                                        <Pencil className="h-3.5 w-3.5" />
                                                        Edit
                                                    </Link>
                                                    <PillButton
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            handleDelete(plan)
                                                        }
                                                        className="text-destructive"
                                                    >
                                                        <Trash2 className="h-3.5 w-3.5" />
                                                        Delete
                                                    </PillButton>
                                                </div>
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
