import Chatbot from '@/components/chatbot/Chatbot';
import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import { type BreadcrumbItem } from '@/types/index.d';
import { type ReactNode } from 'react';
import { Toaster } from 'sonner';
interface AppLayoutProps {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
}

export default ({ children, breadcrumbs, ...props }: any) => (
    <AppLayoutTemplate breadcrumbs={breadcrumbs} {...props}>
        <Toaster richColors position="top-right" />
        {children}
        <Chatbot />
    </AppLayoutTemplate>
);
