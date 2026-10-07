import {
    CouponForm,
    type CouponPlanOption,
} from '@/components/admin/coupon-form';
import { type CurrencyOption } from '@/components/admin/plan-form';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Coupons', href: '/admin/coupons' },
    { title: 'New coupon', href: '/admin/coupons/create' },
];

export default function CouponCreate({
    currencies,
    plans,
}: {
    currencies: CurrencyOption[];
    plans: CouponPlanOption[];
}) {
    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin - New coupon" />

            <div className="flex w-full flex-col gap-4 py-6">
                <CouponForm currencies={currencies} plans={plans} />
            </div>
        </AppSidebarLayout>
    );
}
