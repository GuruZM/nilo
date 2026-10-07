import {
    CouponForm,
    type CouponPlanOption,
} from '@/components/admin/coupon-form';
import { type CurrencyOption } from '@/components/admin/plan-form';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { type BreadcrumbItem, type Coupon } from '@/types';
import { Head } from '@inertiajs/react';

export default function CouponEdit({
    coupon,
    currencies,
    plans,
    redemptions,
}: {
    coupon: Coupon;
    currencies: CurrencyOption[];
    plans: CouponPlanOption[];
    redemptions: number;
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Admin', href: '/admin' },
        { title: 'Coupons', href: '/admin/coupons' },
        { title: coupon.code, href: `/admin/coupons/${coupon.id}/edit` },
    ];

    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>
            <Head title={`Admin - ${coupon.code}`} />

            <div className="flex w-full flex-col gap-4 py-6">
                <CouponForm
                    coupon={coupon}
                    currencies={currencies}
                    plans={plans}
                    redemptions={redemptions}
                />
            </div>
        </AppSidebarLayout>
    );
}
