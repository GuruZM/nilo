import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import React from 'react';
import type { PageProps } from '../../types';

interface Quotation {
    id: number;
    number: string;
    amount: number;
    status: string;
    created_at: string;
}

interface QuotationsIndexProps extends PageProps {
    quotations: Quotation[];
}

const QuotationsIndex: React.FC<QuotationsIndexProps> = ({ quotations }) => {
    return (
        <AppLayout>
            <Head title="Quotations" />
            <div className="mx-auto max-w-4xl py-10">
                <div className="mb-8 flex items-center justify-between">
                    <h1 className="text-3xl font-bold text-gray-900">
                        Quotations
                    </h1>
                    <a
                        href="/quotations/create"
                        className="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none"
                    >
                        + Add Quotation
                    </a>
                </div>
                <div className="overflow-x-auto rounded-lg bg-white shadow">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase">
                                    Number
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase">
                                    Amount
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase">
                                    Status
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase">
                                    Date
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200">
                            {quotations.map((quotation) => (
                                <tr key={quotation.id}>
                                    <td className="px-6 py-4 font-medium whitespace-nowrap text-gray-900">
                                        {quotation.number}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap">
                                        ${quotation.amount.toFixed(2)}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap">
                                        {quotation.status}
                                    </td>
                                    <td className="px-6 py-4 whitespace-nowrap">
                                        {new Date(
                                            quotation.created_at,
                                        ).toLocaleDateString()}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
};

export default QuotationsIndex;
