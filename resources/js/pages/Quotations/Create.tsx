import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import React from 'react';

const QuotationsCreate: React.FC = () => {
    return (
        <AppLayout>
            <Head title="Create Quotation" />
            <div className="mx-auto max-w-2xl py-10">
                <h1 className="mb-6 text-2xl font-bold text-gray-900">
                    Create Quotation
                </h1>
                {/* Quotation form goes here */}
                <div className="rounded bg-white p-6 shadow">
                    <p className="text-gray-600">
                        Quotation creation form coming soon...
                    </p>
                </div>
            </div>
        </AppLayout>
    );
};

export default QuotationsCreate;
