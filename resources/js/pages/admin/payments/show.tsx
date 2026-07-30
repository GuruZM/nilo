import {
    InitialsAvatar,
    Panel,
    PanelHeader,
    PillButton,
    SoftTile,
    StatusPill,
    TotalRow,
    pillButtonClass,
} from '@/components/dashboard/primitives';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCircle2,
    FileText,
    Receipt,
    XCircle,
} from 'lucide-react';

interface PaymentShowProps {
    payment: {
        id: number;
        amount: string;
        original_amount: string | null;
        discount_amount: string;
        coupon_code: string | null;
        currency_code: string;
        payment_method: string;
        payment_reference: string | null;
        phone_number: string | null;
        pop_file_path: string | null;
        status: string;
        admin_notes: string | null;
        created_at: string;
        confirmed_at: string | null;
        user: { id: number; name: string; email: string };
        plan: { name: string };
        confirmed_by: { name: string } | null;
    };
}

export default function PaymentShow({ payment }: PaymentShowProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Admin', href: '/admin' },
        { title: 'Payments', href: '/admin/payments' },
        {
            title: `Payment #${payment.id}`,
            href: `/admin/payments/${payment.id}`,
        },
    ];

    const handleConfirm = () => {
        if (confirm('Confirm this payment and activate the subscription?')) {
            router.post(`/admin/payments/${payment.id}/confirm`);
        }
    };

    const handleReject = () => {
        if (confirm('Reject this payment?')) {
            router.post(`/admin/payments/${payment.id}/reject`);
        }
    };

    const isPdf = payment.pop_file_path?.endsWith('.pdf') ?? false;
    const discount = parseFloat(payment.discount_amount);
    const wasDiscounted = discount > 0;

    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>
            <Head title="Payment Detail" />

            <div className="flex w-full flex-col gap-4 py-6">
                <Panel>
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-center gap-3">
                            <InitialsAvatar
                                name={payment.user.name}
                                className="h-11 w-11 text-sm"
                            />
                            <div className="min-w-0">
                                <div className="flex items-center gap-2">
                                    <span className="truncate text-base font-semibold">
                                        Payment #{payment.id}
                                    </span>
                                    <StatusPill status={payment.status} />
                                </div>
                                <Link
                                    href={`/admin/users/${payment.user.id}`}
                                    className="truncate text-xs text-muted-foreground hover:text-foreground"
                                >
                                    {payment.user.name} · {payment.user.email}
                                </Link>
                            </div>
                        </div>

                        <Link
                            href="/admin/payments"
                            className={pillButtonClass('ghost', 'sm')}
                        >
                            <ArrowLeft className="h-3.5 w-3.5" />
                            All payments
                        </Link>
                    </div>
                </Panel>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
                    <Panel className="lg:col-span-5">
                        <PanelHeader
                            icon={Receipt}
                            title="Payment details"
                            subtitle="What the subscriber submitted."
                        />

                        <SoftTile className="flex flex-col gap-2">
                            <TotalRow label="Plan" value={payment.plan.name} />
                            {wasDiscounted ? (
                                <>
                                    <TotalRow
                                        label="List price"
                                        value={`K${parseFloat(payment.original_amount ?? payment.amount).toLocaleString()}`}
                                    />
                                    <TotalRow
                                        label={`Coupon ${payment.coupon_code ?? ''}`.trim()}
                                        value={`− K${discount.toLocaleString()}`}
                                    />
                                </>
                            ) : null}
                            <TotalRow
                                label={wasDiscounted ? 'Amount due' : 'Amount'}
                                value={`K${parseFloat(payment.amount).toLocaleString()}`}
                                strong
                            />
                            <TotalRow
                                label="Method"
                                value={
                                    payment.payment_method === 'airtel_money'
                                        ? 'Airtel Money'
                                        : 'Bank Transfer'
                                }
                            />
                            {payment.phone_number ? (
                                <TotalRow
                                    label="Phone number"
                                    value={payment.phone_number}
                                />
                            ) : null}
                            {payment.payment_reference ? (
                                <TotalRow
                                    label="Reference"
                                    value={payment.payment_reference}
                                />
                            ) : null}
                            <TotalRow
                                label="Submitted"
                                value={new Date(
                                    payment.created_at,
                                ).toLocaleString()}
                            />
                            {payment.confirmed_at ? (
                                <TotalRow
                                    label="Confirmed"
                                    value={new Date(
                                        payment.confirmed_at,
                                    ).toLocaleString()}
                                />
                            ) : null}
                            {payment.confirmed_by ? (
                                <TotalRow
                                    label="Confirmed by"
                                    value={payment.confirmed_by.name}
                                />
                            ) : null}
                        </SoftTile>

                        {payment.admin_notes ? (
                            <p className="mt-3 rounded-2xl bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">
                                {payment.admin_notes}
                            </p>
                        ) : null}

                        {payment.status === 'pending' ? (
                            <div className="mt-4 flex flex-wrap items-center gap-2">
                                <PillButton onClick={handleConfirm}>
                                    <CheckCircle2 className="h-4 w-4" />
                                    Confirm payment
                                </PillButton>
                                <PillButton
                                    variant="ghost"
                                    onClick={handleReject}
                                    className="text-destructive"
                                >
                                    <XCircle className="h-4 w-4" />
                                    Reject
                                </PillButton>
                            </div>
                        ) : null}
                    </Panel>

                    <Panel className="lg:col-span-7">
                        <PanelHeader
                            icon={FileText}
                            title="Proof of payment"
                            subtitle="Uploaded by the subscriber at submission."
                        />

                        {!payment.pop_file_path ? (
                            <p className="py-8 text-center text-sm text-muted-foreground">
                                No proof of payment was uploaded.
                            </p>
                        ) : isPdf ? (
                            <a
                                href={`/storage/${payment.pop_file_path}`}
                                target="_blank"
                                rel="noopener noreferrer"
                                className={pillButtonClass('soft', 'md')}
                            >
                                <FileText className="h-4 w-4" />
                                Open PDF
                            </a>
                        ) : (
                            <a
                                href={`/storage/${payment.pop_file_path}`}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="block overflow-hidden rounded-2xl bg-muted/50 dark:bg-white/5"
                            >
                                <img
                                    src={`/storage/${payment.pop_file_path}`}
                                    alt="Proof of payment"
                                    className="h-auto w-full max-w-full"
                                />
                            </a>
                        )}
                    </Panel>
                </div>
            </div>
        </AppSidebarLayout>
    );
}
