import { SidebarProvider } from '@/components/ui/sidebar';
import { SharedData } from '@/types/index.d';
import { usePage } from '@inertiajs/react';

interface AppShellProps {
    children: React.ReactNode;
    variant?: 'header' | 'sidebar';
}

export function AppShell({ children, variant = 'header' }: AppShellProps) {
    const isOpen = usePage<SharedData>().props.sidebarOpen;

    if (variant === 'header') {
        return (
            <div className="flex min-h-screen w-full flex-col">{children}</div>
        );
    }

    return (
        <div className="min-h-svh w-full bg-canvas">
            <SidebarProvider
                defaultOpen={isOpen}
                style={{ '--sidebar-width': '17rem' } as React.CSSProperties}
                className="bg-canvas md:mx-auto md:w-4/5"
            >
                {children}
            </SidebarProvider>
        </div>
    );
}
