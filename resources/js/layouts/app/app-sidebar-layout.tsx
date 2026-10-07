import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar, isAdminPath } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types/index.d';
import { usePage } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: PropsWithChildren<{ breadcrumbs?: BreadcrumbItem[] }>) {
    /** Admin pages are wide tables, so they take the whole viewport. */
    const fullWidth = isAdminPath(usePage().url);

    return (
        <AppShell variant="sidebar" fullWidth={fullWidth}>
            <AppSidebar fullWidth={fullWidth} />
            <AppContent
                variant="sidebar"
                className="overflow-x-hidden bg-canvas md:m-2 md:min-h-[calc(100svh-(--spacing(4)))] md:rounded-xl"
            >
                <AppSidebarHeader
                    breadcrumbs={breadcrumbs}
                    fullWidth={fullWidth}
                />
                <div
                    className={cn(
                        'mx-auto w-full px-3',
                        !fullWidth && 'max-w-[1400px]',
                    )}
                >
                    {children}
                </div>
            </AppContent>
        </AppShell>
    );
}
