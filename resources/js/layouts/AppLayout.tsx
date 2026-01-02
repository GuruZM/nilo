import { Head } from '@inertiajs/react';
import React, { PropsWithChildren } from 'react';
import { Toaster } from 'sonner';
const AppLayout: React.FC<PropsWithChildren<{ title?: string }>> = ({
    title,
    children,
}) => {
    return (
        <div className="min-h-screen bg-gray-100">
            <Head title={title || 'App'} />

            <main className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <Toaster richColors position="top-right" />

                {children}
            </main>
        </div>
    );
};

export default AppLayout;
