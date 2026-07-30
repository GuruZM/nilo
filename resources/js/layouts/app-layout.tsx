import { AppToaster } from '@/components/app-toaster';
import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import { type BreadcrumbItem } from '@/types/index.d';
import { type ReactNode } from 'react';
interface AppLayoutProps {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
}

export default ({ children, breadcrumbs, ...props }: any) => (
    <AppLayoutTemplate breadcrumbs={breadcrumbs} {...props}>
        <AppToaster />
        {children}
    </AppLayoutTemplate>
);
