import { SidebarProvider } from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';
import { SharedData } from '@/types/index.d';
import { usePage } from '@inertiajs/react';

interface AppShellProps {
    children: React.ReactNode;
    variant?: 'header' | 'sidebar';
    /** Drops the centred 4/5 column so the shell spans the viewport. */
    fullWidth?: boolean;
}

export function AppShell({
    children,
    variant = 'header',
    fullWidth = false,
}: AppShellProps) {
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
                className={cn('bg-canvas', !fullWidth && 'md:mx-auto md:w-4/5')}
            >
                {children}
            </SidebarProvider>
        </div>
    );
}
