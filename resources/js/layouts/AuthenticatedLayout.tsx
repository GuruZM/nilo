import { Head, Link, usePage } from '@inertiajs/react';
import React from 'react';

type AuthenticatedLayoutProps = {
    title?: string;
    children: React.ReactNode;
};

const AuthenticatedLayout = ({ title, children }: AuthenticatedLayoutProps) => {
    const { auth } = usePage().props as any;
    return (
        <div className="min-h-screen bg-gray-100">
            <Head title={title || 'Dashboard'} />
            <header className="bg-white shadow">
                <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-4">
                    <h1 className="text-lg font-bold">My SaaS App</h1>
                    <div className="flex items-center gap-4">
                        <span className="text-sm text-gray-700">
                            {auth?.user?.name}
                        </span>
                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            className="text-sm text-red-600 hover:underline"
                        >
                            Logout
                        </Link>
                    </div>
                </div>
            </header>
            <main className="mx-auto max-w-7xl px-4 py-8">{children}</main>
        </div>
    );
};

export default AuthenticatedLayout;
