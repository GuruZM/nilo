import { Card } from '@/components/ui/card';
import { MiniBar } from '@/components/ui/mini-bar';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Cell, Legend, Pie, PieChart, ResponsiveContainer } from 'recharts';

// Use Inertia page props for dashboard data
const page =
    typeof window !== 'undefined' ? (window as any).Inertia?.page : undefined;
const inertiaProps = page?.props || {};

function InvoiceTableWithFilter({ invoices = [] }: { invoices: any[] }) {
    const [filter, setFilter] = useState('All');
    const filtered =
        filter === 'All'
            ? invoices
            : invoices.filter((i) => i.status === filter);
    return (
        <>
            <div className="ml-auto flex gap-2 px-6 pt-4 pb-2">
                {['All', 'Paid', 'Unpaid'].map((type) => (
                    <button
                        key={type}
                        className={`rounded border px-3 py-1 text-xs font-semibold transition-colors duration-200 ${filter === type ? 'border-[#00417d] bg-[#00417d] text-white' : 'border-[#00417d] bg-white text-[#00417d]'}`}
                        onClick={() => setFilter(type)}
                    >
                        {type}
                    </button>
                ))}
            </div>
            <div className="overflow-x-auto px-6 pb-4">
                <table className="min-w-full text-sm">
                    <thead>
                        <tr className="border-b text-left text-muted-foreground">
                            <th className="py-2 pr-4">Date</th>
                            <th className="py-2 pr-4">Invoice #</th>
                            <th className="py-2 pr-4">Client</th>
                            <th className="py-2 pr-4">Amount</th>
                            <th className="py-2 pr-4">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        {filtered.map((inv) => (
                            <tr key={inv.id} className="border-b last:border-0">
                                <td className="py-2 pr-4 whitespace-nowrap">
                                    {inv.date}
                                </td>
                                <td className="py-2 pr-4 whitespace-nowrap">
                                    {inv.number}
                                </td>
                                <td className="py-2 pr-4 whitespace-nowrap">
                                    {inv.client ?? inv.client_id}
                                </td>
                                <td className="py-2 pr-4 whitespace-nowrap">
                                    $
                                    {inv.amount?.toLocaleString?.() ??
                                        inv.amount}
                                </td>
                                <td
                                    className={`py-2 pr-4 font-semibold whitespace-nowrap ${inv.status === 'Paid' ? 'text-green-600' : 'text-red-600'}`}
                                >
                                    {inv.status}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </>
    );
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

function getStatsFromProps(stats: any) {
    if (!stats) return [];
    return [
        {
            label: 'Revenue',
            value: stats.total_revenue ?? 0,
            trend: [
                { name: 'Prev', value: stats.total_revenue ?? 0 },
                { name: 'Now', value: stats.total_revenue ?? 0 },
            ],
        },
        {
            label: 'Clients',
            value: stats.client_count ?? 0,
            trend: [
                { name: 'Prev', value: stats.client_count ?? 0 },
                { name: 'Now', value: stats.client_count ?? 0 },
            ],
        },
        {
            label: 'Unpaid Invoices',
            value: stats.unpaid_count ?? 0,
            trend: [
                { name: 'Prev', value: stats.unpaid_count ?? 0 },
                { name: 'Now', value: stats.unpaid_count ?? 0 },
            ],
        },
    ];
}

const invoiceData = [
    { name: 'Jan', Paid: 8, Unpaid: 4 },
    { name: 'Feb', Paid: 10, Unpaid: 2 },
    { name: 'Mar', Paid: 12, Unpaid: 3 },
    { name: 'Apr', Paid: 15, Unpaid: 5 },
    { name: 'May', Paid: 20, Unpaid: 2 },
    { name: 'Jun', Paid: 18, Unpaid: 4 },
];

function getPaidVsUnpaid(invoices: any[]) {
    const paid = invoices.filter((i) => i.status === 'Paid').length;
    const unpaid = invoices.filter((i) => i.status === 'Unpaid').length;
    return [
        { name: 'Paid', value: paid },
        { name: 'Unpaid', value: unpaid },
    ];
}
const COLORS = ['#00417d', '#e11d48'];

export default function Dashboard() {
    const { stats, invoices } = usePage().props as any;
    const dashboardStats = getStatsFromProps(stats);
    const paidVsUnpaid = getPaidVsUnpaid(invoices || []);
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                {/* Stats */}
                <div className="grid auto-rows-min gap-4 md:grid-cols-4">
                    {dashboardStats.map((stat) => (
                        <Card
                            key={stat.label}
                            className="flex min-h-[90px] flex-col items-center justify-center px-2 py-3"
                        >
                            <div className="flex w-full items-center justify-between">
                                <div className="text-2xl font-bold text-[#00417d]">
                                    {stat.value}
                                </div>
                                <div className="h-7 w-16">
                                    <MiniBar data={stat.trend} height={28} />
                                </div>
                            </div>
                            <div className="mt-1 w-full text-left text-sm text-muted-foreground">
                                {stat.label}
                            </div>
                        </Card>
                    ))}
                </div>

                {/* Dashboard Insights */}
                <div className="flex items-center justify-between px-2 pt-2 pb-1">
                    <h2 className="text-xl font-semibold text-[#00417d]">
                        Dashboard Insights
                    </h2>
                </div>
                <div className="grid gap-4 md:grid-cols-2">
                    <Card className="flex h-[340px] flex-col">
                        <div className="px-6 pt-4 text-lg font-semibold">
                            Invoices Paid vs Unpaid
                        </div>
                        <div className="flex flex-1 items-center">
                            <ResponsiveContainer width="100%" height="90%">
                                <PieChart>
                                    <Pie
                                        data={paidVsUnpaid}
                                        dataKey="value"
                                        nameKey="name"
                                        cx="50%"
                                        cy="50%"
                                        outerRadius={70}
                                        label
                                    >
                                        {paidVsUnpaid.map((entry, idx) => (
                                            <Cell
                                                key={`cell-${idx}`}
                                                fill={
                                                    COLORS[idx % COLORS.length]
                                                }
                                            />
                                        ))}
                                    </Pie>
                                    <Legend />
                                </PieChart>
                            </ResponsiveContainer>
                        </div>
                    </Card>
                    <Card className="flex h-[340px] flex-col">
                        <InvoiceTableWithFilter invoices={invoices || []} />
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
