import { usePage } from '@inertiajs/react';
import { PanelLeft } from 'lucide-react';

import { Breadcrumbs } from '@/components/breadcrumbs';
import { useSidebar } from '@/components/ui/sidebar';
import { UserMenu } from '@/components/user-menu';
import { cn } from '@/lib/utils';
import { type SharedData } from '@/types';
import { type BreadcrumbItem as BreadcrumbItemType } from '@/types/index.d';

export function AppSidebarHeader({
    breadcrumbs = [],
    fullWidth = false,
}: {
    breadcrumbs?: BreadcrumbItemType[];
    /** Must match the content column below, or the card edges drift apart. */
    fullWidth?: boolean;
}) {
    const page = usePage<SharedData>();
    const { toggleSidebar } = useSidebar();

    const authUser = page.props.auth?.user ?? null;
    const hasBreadcrumbs = breadcrumbs.length > 0;

    return (
        <header
            className={cn(
                'mx-auto w-full px-3 pt-2 pb-3',
                !fullWidth && 'max-w-[1400px]',
            )}
        >
            <div
                className={cn(
                    'flex h-14 items-center justify-between gap-3 rounded-2xl bg-card px-2 sm:px-3',
                    'ring-1 ring-black/[0.04] dark:ring-white/10',
                    'shadow-[0_1px_2px_0_rgb(16_24_40/0.04),0_12px_32px_-18px_rgb(16_24_40/0.22)]',
                    'dark:shadow-none',
                )}
            >
                <div className="flex min-w-0 items-center gap-1.5">
                    <button
                        type="button"
                        onClick={toggleSidebar}
                        aria-label="Toggle sidebar"
                        className={cn(
                            'flex h-9 w-9 shrink-0 items-center justify-center rounded-xl transition',
                            'text-muted-foreground hover:bg-muted hover:text-foreground dark:hover:bg-white/5',
                            'focus-visible:ring-2 focus-visible:ring-brand-400 focus-visible:outline-none',
                        )}
                    >
                        <PanelLeft className="h-4.5 w-4.5" />
                    </button>

                    {hasBreadcrumbs && (
                        <>
                            <span
                                aria-hidden
                                className="h-5 w-px shrink-0 bg-border"
                            />
                            <div className="min-w-0 pl-1.5">
                                <Breadcrumbs breadcrumbs={breadcrumbs} />
                            </div>
                        </>
                    )}
                </div>

                {authUser && <UserMenu user={authUser} />}
            </div>
        </header>
    );
}
