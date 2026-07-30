import {
    EmptyState,
    Panel,
    PanelHeader,
    SoftTile,
    StatTile,
    pillButtonClass,
} from '@/components/dashboard/primitives';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import {
    CreditCard,
    DollarSign,
    MessageSquare,
    PieChart,
    UserPlus,
    Users,
} from 'lucide-react';

interface DashboardProps {
    stats: {
        totalUsers: number;
        pendingPayments: number;
        confirmedRevenue: number;
        pendingInquiries: number;
    };
    planStats: Array<{
        id: number;
        name: string;
        slug: string;
        subscriptions_count: number;
    }>;
    recentUsers: Array<{
        id: number;
        name: string;
        email: string;
        created_at: string;
    }>;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Dashboard', href: '/admin' },
];

export default function Dashboard({
    stats,
    planStats,
    recentUsers,
}: DashboardProps) {
    const statCards = [
        {
            label: 'Total Users',
            value: stats.totalUsers,
            icon: Users,
            href: '/admin/users',
        },
        {
            label: 'Pending Payments',
            value: stats.pendingPayments,
            icon: CreditCard,
            href: '/admin/payments',
        },
        {
            label: 'Total Revenue',
            value: `K${Number(stats.confirmedRevenue).toLocaleString()}`,
            icon: DollarSign,
            href: '/admin/payments?status=confirmed',
        },
        {
            label: 'Pending Inquiries',
            value: stats.pendingInquiries,
            icon: MessageSquare,
            href: '/admin/inquiries',
        },
    ];

    /** Widest plan drives the bar scale, so the busiest plan always fills it. */
    const busiestPlan = planStats.reduce(
        (max, plan) => Math.max(max, plan.subscriptions_count),
        0,
    );

    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />

            <div className="flex w-full flex-col gap-4 py-3">
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {statCards.map((stat) => (
                        <StatTile
                            key={stat.label}
                            title={stat.label}
                            value={stat.value}
                            icon={stat.icon}
                            href={stat.href}
                        />
                    ))}
                </div>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
                    <Panel className="lg:col-span-5">
                        <PanelHeader
                            icon={PieChart}
                            title="Users by plan"
                            subtitle="Active subscriptions counted per plan."
                            action={
                                <Link
                                    href="/admin/plans"
                                    className={pillButtonClass('ghost', 'sm')}
                                >
                                    Manage plans
                                </Link>
                            }
                        />

                        {planStats.length === 0 ? (
                            <EmptyState
                                icon={PieChart}
                                title="No plans yet"
                                description="Create a plan to start tracking subscriptions."
                            />
                        ) : (
                            <div className="flex flex-col gap-2">
                                {planStats.map((plan) => (
                                    <SoftTile
                                        key={plan.id}
                                        className="flex flex-col gap-2"
                                    >
                                        <div className="flex items-center justify-between gap-3">
                                            <span className="truncate text-sm font-semibold">
                                                {plan.name}
                                            </span>
                                            <span className="text-sm font-semibold tabular-nums">
                                                {plan.subscriptions_count}
                                            </span>
                                        </div>
                                        <div className="h-1.5 overflow-hidden rounded-full bg-muted dark:bg-white/10">
                                            <div
                                                className="h-full rounded-full bg-brand transition-all"
                                                style={{
                                                    width: `${
                                                        busiestPlan > 0
                                                            ? Math.max(
                                                                  (plan.subscriptions_count /
                                                                      busiestPlan) *
                                                                      100,
                                                                  2,
                                                              )
                                                            : 2
                                                    }%`,
                                                }}
                                            />
                                        </div>
                                    </SoftTile>
                                ))}
                            </div>
                        )}
                    </Panel>

                    <Panel className="lg:col-span-7">
                        <PanelHeader
                            icon={UserPlus}
                            title="Recent users"
                            subtitle="The newest sign-ups, most recent first."
                            action={
                                <Link
                                    href="/admin/users"
                                    className={pillButtonClass('ghost', 'sm')}
                                >
                                    View all
                                </Link>
                            }
                        />

                        {recentUsers.length === 0 ? (
                            <EmptyState
                                icon={UserPlus}
                                title="No sign-ups yet"
                                description="New accounts will appear here as they register."
                            />
                        ) : (
                            <div className="flex flex-col gap-1.5">
                                {recentUsers.map((user) => (
                                    <Link
                                        key={user.id}
                                        href={`/admin/users/${user.id}`}
                                        className="flex items-center justify-between gap-3 rounded-2xl bg-muted/40 px-3 py-2.5 transition hover:bg-brand-50/70 dark:bg-white/5 dark:hover:bg-brand-500/10"
                                    >
                                        <div className="min-w-0">
                                            <div className="truncate text-sm font-semibold">
                                                {user.name}
                                            </div>
                                            <div className="truncate text-xs text-muted-foreground">
                                                {user.email}
                                            </div>
                                        </div>
                                        <span className="shrink-0 text-xs text-muted-foreground">
                                            {new Date(
                                                user.created_at,
                                            ).toLocaleDateString()}
                                        </span>
                                    </Link>
                                ))}
                            </div>
                        )}
                    </Panel>
                </div>
            </div>
        </AppSidebarLayout>
    );
}
