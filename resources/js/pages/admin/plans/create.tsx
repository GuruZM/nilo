import { type CurrencyOption, PlanForm } from '@/components/admin/plan-form';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Plans', href: '/admin/plans' },
    { title: 'New plan', href: '/admin/plans/create' },
];

export default function PlanCreate({
    currencies,
    nextSortOrder,
}: {
    currencies: CurrencyOption[];
    nextSortOrder: number;
}) {
    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin - New plan" />

            <div className="mx-auto flex w-full max-w-[1400px] flex-col gap-4 px-3 py-6">
                <PlanForm
                    currencies={currencies}
                    defaultSortOrder={nextSortOrder}
                />
            </div>
        </AppSidebarLayout>
    );
}
