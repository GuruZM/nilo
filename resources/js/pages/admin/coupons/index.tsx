import {
    Chip,
    Panel,
    PanelHeader,
    PillButton,
    pillButtonClass,
} from '@/components/dashboard/primitives';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { type BreadcrumbItem, type Coupon } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus, TicketPercent, Trash2 } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Coupons', href: '/admin/coupons' },
];

const formatDiscount = (coupon: Coupon): string => {
    const value = parseFloat(coupon.discount_value);

    if (coupon.discount_type === 'percentage') {
        return `${value}% off`;
    }

    return `${coupon.currency_code} ${value.toLocaleString()} off`;
};

const formatUsage = (coupon: Coupon): string =>
    coupon.max_redemptions === null
        ? `${coupon.redemptions_count} · unlimited`
        : `${coupon.redemptions_count} / ${coupon.max_redemptions}`;

const formatWindow = (coupon: Coupon): string => {
    const format = (value: string) => new Date(value).toLocaleDateString();

    if (coupon.starts_at && coupon.expires_at) {
        return `${format(coupon.starts_at)} – ${format(coupon.expires_at)}`;
    }

    if (coupon.expires_at) {
        return `Until ${format(coupon.expires_at)}`;
    }

    if (coupon.starts_at) {
        return `From ${format(coupon.starts_at)}`;
    }

    return 'Always';
};

const isExpired = (coupon: Coupon): boolean =>
    coupon.expires_at !== null && new Date(coupon.expires_at) < new Date();

const isExhausted = (coupon: Coupon): boolean =>
    coupon.max_redemptions !== null &&
    coupon.redemptions_count >= coupon.max_redemptions;

export default function CouponsIndex({ coupons }: { coupons: Coupon[] }) {
    const handleDelete = (coupon: Coupon) => {
        if (!confirm(`Delete coupon ${coupon.code}? This cannot be undone.`)) {
            return;
        }

        router.delete(`/admin/coupons/${coupon.id}`, { preserveScroll: true });
    };

    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin - Coupons" />

            <div className="mx-auto flex w-full flex-col gap-4 py-6">
                <Panel>
                    <PanelHeader
                        icon={TicketPercent}
                        title="Coupons"
                        subtitle="Discount codes customers apply while purchasing a plan."
                        action={
                            <Link
                                href="/admin/coupons/create"
                                className={pillButtonClass('solid', 'sm')}
                            >
                                <Plus className="h-3.5 w-3.5" />
                                New coupon
                            </Link>
                        }
                    />

                    {coupons.length === 0 ? (
                        <p className="py-8 text-center text-sm text-muted-foreground">
                            No coupons yet. Create one to start discounting plan
                            purchases.
                        </p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="text-left text-xs text-muted-foreground">
                                        <th className="py-2 pr-3 font-medium">
                                            Code
                                        </th>
                                        <th className="py-2 pr-3 font-medium">
                                            Discount
                                        </th>
                                        <th className="py-2 pr-3 font-medium">
                                            Applies to
                                        </th>
                                        <th className="py-2 pr-3 font-medium">
                                            Window
                                        </th>
                                        <th className="py-2 pr-3 font-medium">
                                            Redeemed
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
                                    {coupons.map((coupon) => (
                                        <tr
                                            key={coupon.id}
                                            className="border-t border-border/60"
                                        >
                                            <td className="py-3 pr-3">
                                                <div className="font-semibold">
                                                    {coupon.code}
                                                </div>
                                                {coupon.description ? (
                                                    <div className="text-xs text-muted-foreground">
                                                        {coupon.description}
                                                    </div>
                                                ) : null}
                                            </td>
                                            <td className="py-3 pr-3">
                                                {formatDiscount(coupon)}
                                            </td>
                                            <td className="py-3 pr-3">
                                                {coupon.plans?.length
                                                    ? coupon.plans
                                                          .map(
                                                              (plan) =>
                                                                  plan.name,
                                                          )
                                                          .join(', ')
                                                    : 'All plans'}
                                            </td>
                                            <td className="py-3 pr-3">
                                                {formatWindow(coupon)}
                                            </td>
                                            <td className="py-3 pr-3">
                                                {formatUsage(coupon)}
                                            </td>
                                            <td className="py-3 pr-3">
                                                <div className="flex flex-wrap gap-1.5">
                                                    <Chip
                                                        interactive={false}
                                                        active={
                                                            coupon.is_active
                                                        }
                                                    >
                                                        {coupon.is_active
                                                            ? 'Active'
                                                            : 'Retired'}
                                                    </Chip>
                                                    {isExpired(coupon) ? (
                                                        <Chip
                                                            interactive={false}
                                                        >
                                                            Expired
                                                        </Chip>
                                                    ) : null}
                                                    {isExhausted(coupon) ? (
                                                        <Chip
                                                            interactive={false}
                                                        >
                                                            Used up
                                                        </Chip>
                                                    ) : null}
                                                    {coupon.once_per_user ? (
                                                        <Chip
                                                            interactive={false}
                                                        >
                                                            One per account
                                                        </Chip>
                                                    ) : null}
                                                </div>
                                            </td>
                                            <td className="py-3">
                                                <div className="flex justify-end gap-2">
                                                    <Link
                                                        href={`/admin/coupons/${coupon.id}/edit`}
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
                                                            handleDelete(coupon)
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
