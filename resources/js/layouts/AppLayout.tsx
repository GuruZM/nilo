import { AppToaster } from '@/components/app-toaster';
import { Head } from '@inertiajs/react';
import React, { PropsWithChildren } from 'react';
const AppLayout: React.FC<PropsWithChildren<{ title?: string }>> = ({
    title,
    children,
}) => {
    return (
        <div className="min-h-screen bg-gray-100">
            <Head title={title || 'App'} />

            <main className="mx-auto py-8">
                <AppToaster />

                {children}
            </main>
        </div>
    );
};

export default AppLayout;
