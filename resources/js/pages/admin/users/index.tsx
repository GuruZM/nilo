import {
    Chip,
    EmptyState,
    InitialsAvatar,
    Pagination,
    Panel,
    PanelHeader,
    StatusPill,
    pillButtonClass,
    tableCellClass,
    type PaginationLink,
} from '@/components/dashboard/primitives';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { SearchX, Users } from 'lucide-react';
import { useState } from 'react';

interface UserItem {
    id: number;
    name: string;
    email: string;
    created_at: string;
    owned_companies_count: number;
    subscription: {
        id: number;
        status: string;
        plan: { name: string; slug: string } | null;
    } | null;
}

interface UsersIndexProps {
    users: {
        data: UserItem[];
        current_page: number;
        last_page: number;
        links: PaginationLink[];
    };
    filters: { search: string | null; plan: string | null };
    plans: Array<{ id: number; name: string; slug: string }>;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Users', href: '/admin/users' },
];

export default function UsersIndex({ users, filters, plans }: UsersIndexProps) {
    const [search, setSearch] = useState(filters.search ?? '');

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            '/admin/users',
            { search, plan: filters.plan },
            { preserveState: true },
        );
    };

    const handlePlanFilter = (slug: string | null) => {
        router.get(
            '/admin/users',
            { search: filters.search, plan: slug },
            { preserveState: true },
        );
    };

    const isFiltered = Boolean(filters.search || filters.plan);

    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin - Users" />

            <div className="flex w-full flex-col gap-4 py-6">
                <Panel>
                    <PanelHeader
                        icon={Users}
                        title="Users"
                        subtitle="Every registered account with its current plan and company count."
                        action={
                            <form
                                onSubmit={handleSearch}
                                className="flex w-full items-center gap-2 sm:w-auto"
                            >
                                <input
                                    type="search"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Search name or email…"
                                    aria-label="Search users"
                                    className="h-8 w-full rounded-full bg-muted/60 px-4 text-xs transition placeholder:text-muted-foreground focus:ring-2 focus:ring-brand-400 focus:outline-none sm:w-56 dark:bg-white/5"
                                />
                                <button
                                    type="submit"
                                    className={pillButtonClass('solid', 'sm')}
                                >
                                    Search
                                </button>
                            </form>
                        }
                    />

                    <div className="mb-4 flex flex-wrap items-center gap-2">
                        <Chip
                            active={!filters.plan}
                            onClick={() => handlePlanFilter(null)}
                        >
                            All plans
                        </Chip>
                        {plans.map((plan) => (
                            <Chip
                                key={plan.slug}
                                active={filters.plan === plan.slug}
                                onClick={() => handlePlanFilter(plan.slug)}
                            >
                                {plan.name}
                            </Chip>
                        ))}
                    </div>

                    {users.data.length === 0 ? (
                        <EmptyState
                            icon={isFiltered ? SearchX : Users}
                            tone={isFiltered ? 'muted' : 'brand'}
                            title={isFiltered ? 'No matches' : 'No users yet'}
                            description={
                                isFiltered
                                    ? 'No users match the current search and plan filter.'
                                    : 'Accounts appear here as people register.'
                            }
                            action={
                                isFiltered ? (
                                    <Link
                                        href="/admin/users"
                                        className={pillButtonClass(
                                            'ghost',
                                            'sm',
                                        )}
                                    >
                                        Clear filters
                                    </Link>
                                ) : undefined
                            }
                        />
                    ) : (
                        <div className="-mx-1 overflow-x-auto px-1">
                            <table className="w-full min-w-[46rem] border-separate border-spacing-y-1.5 text-sm">
                                <thead>
                                    <tr className="text-left text-xs text-muted-foreground">
                                        <th className="px-3 pb-1 font-medium">
                                            User
                                        </th>
                                        <th className="px-3 pb-1 font-medium">
                                            Plan
                                        </th>
                                        <th className="px-3 pb-1 font-medium">
                                            Status
                                        </th>
                                        <th className="px-3 pb-1 font-medium">
                                            Companies
                                        </th>
                                        <th className="px-3 pb-1 font-medium">
                                            Joined
                                        </th>
                                        <th className="px-3 pb-1 text-right font-medium">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>

                                <tbody>
                                    {users.data.map((user) => (
                                        <tr key={user.id} className="group">
                                            <td
                                                className={cn(
                                                    tableCellClass,
                                                    'rounded-l-2xl',
                                                )}
                                            >
                                                <div className="flex items-center gap-3">
                                                    <InitialsAvatar
                                                        name={user.name}
                                                    />
                                                    <div className="min-w-0">
                                                        <div className="truncate font-semibold">
                                                            {user.name}
                                                        </div>
                                                        <div className="mt-0.5 truncate text-xs text-muted-foreground">
                                                            {user.email}
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>

                                            <td className={tableCellClass}>
                                                {user.subscription?.plan
                                                    ?.name ?? (
                                                    <span className="text-muted-foreground">
                                                        None
                                                    </span>
                                                )}
                                            </td>

                                            <td className={tableCellClass}>
                                                {user.subscription ? (
                                                    <StatusPill
                                                        status={
                                                            user.subscription
                                                                .status
                                                        }
                                                    />
                                                ) : (
                                                    <span className="text-xs text-muted-foreground">
                                                        No subscription
                                                    </span>
                                                )}
                                            </td>

                                            <td
                                                className={cn(
                                                    tableCellClass,
                                                    'tabular-nums',
                                                )}
                                            >
                                                {user.owned_companies_count}
                                            </td>

                                            <td
                                                className={cn(
                                                    tableCellClass,
                                                    'text-muted-foreground',
                                                )}
                                            >
                                                {new Date(
                                                    user.created_at,
                                                ).toLocaleDateString()}
                                            </td>

                                            <td
                                                className={cn(
                                                    tableCellClass,
                                                    'rounded-r-2xl text-right',
                                                )}
                                            >
                                                <Link
                                                    href={`/admin/users/${user.id}`}
                                                    className={pillButtonClass(
                                                        'soft',
                                                        'sm',
                                                    )}
                                                >
                                                    View
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Panel>

                <Pagination links={users.links} />
            </div>
        </AppSidebarLayout>
    );
}
