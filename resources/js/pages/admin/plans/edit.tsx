import { type CurrencyOption, PlanForm } from '@/components/admin/plan-form';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { type BreadcrumbItem, type Plan } from '@/types';
import { Head } from '@inertiajs/react';

export default function PlanEdit({
    plan,
    currencies,
    activeSubscriptions,
}: {
    plan: Plan;
    currencies: CurrencyOption[];
    activeSubscriptions: number;
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Admin', href: '/admin' },
        { title: 'Plans', href: '/admin/plans' },
        { title: plan.name, href: `/admin/plans/${plan.id}/edit` },
    ];

    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>
            <Head title={`Admin - ${plan.name}`} />

            <div className="flex w-full flex-col gap-4 py-6">
                <PlanForm
                    plan={plan}
                    currencies={currencies}
                    activeSubscriptions={activeSubscriptions}
                />
            </div>
        </AppSidebarLayout>
    );
}
