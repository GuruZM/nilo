import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { type BreadcrumbItem } from '@/types/index.d';
import { type PropsWithChildren } from 'react';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: PropsWithChildren<{ breadcrumbs?: BreadcrumbItem[] }>) {
    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent
                variant="sidebar"
                className="overflow-x-hidden bg-canvas md:m-2 md:min-h-[calc(100svh-(--spacing(4)))] md:rounded-xl"
            >
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                <div className="mx-auto w-full max-w-[1400px] px-3">
                    {children}
                </div>
            </AppContent>
        </AppShell>
    );
}
