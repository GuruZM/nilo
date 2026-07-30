import {
    EmptyState,
    InitialsAvatar,
    Pagination,
    Panel,
    PanelHeader,
    PillButton,
    SoftTile,
    StatusPill,
    type PaginationLink,
} from '@/components/dashboard/primitives';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Check, MessageSquare } from 'lucide-react';

interface Inquiry {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    company_name: string | null;
    message: string;
    is_handled: boolean;
    created_at: string;
}

interface InquiriesIndexProps {
    inquiries: {
        data: Inquiry[];
        current_page: number;
        last_page: number;
        links: PaginationLink[];
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Inquiries', href: '/admin/inquiries' },
];

export default function InquiriesIndex({ inquiries }: InquiriesIndexProps) {
    const handleMarkHandled = (id: number) => {
        router.post(`/admin/inquiries/${id}/handle`);
    };

    const openCount = inquiries.data.filter(
        (inquiry) => !inquiry.is_handled,
    ).length;

    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin - Enterprise Inquiries" />

            <div className="flex w-full flex-col gap-4 py-6">
                <Panel>
                    <PanelHeader
                        icon={MessageSquare}
                        title="Enterprise inquiries"
                        subtitle={
                            inquiries.data.length > 0
                                ? `${openCount} awaiting a reply on this page.`
                                : 'Messages sent from the enterprise contact form.'
                        }
                    />

                    {inquiries.data.length === 0 ? (
                        <EmptyState
                            icon={MessageSquare}
                            title="No inquiries yet"
                            description="Enterprise enquiries submitted from the pricing page land here."
                        />
                    ) : (
                        <div className="flex flex-col gap-2">
                            {inquiries.data.map((inquiry) => (
                                <SoftTile
                                    key={inquiry.id}
                                    className={cn(
                                        'flex flex-col gap-3 p-4',
                                        inquiry.is_handled && 'opacity-60',
                                    )}
                                >
                                    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div className="flex min-w-0 items-start gap-3">
                                            <InitialsAvatar
                                                name={inquiry.name}
                                            />
                                            <div className="min-w-0">
                                                <div className="truncate text-sm font-semibold">
                                                    {inquiry.name}
                                                </div>
                                                <div className="mt-0.5 truncate text-xs text-muted-foreground">
                                                    {[
                                                        inquiry.email,
                                                        inquiry.phone,
                                                        inquiry.company_name,
                                                    ]
                                                        .filter(Boolean)
                                                        .join(' · ')}
                                                </div>
                                            </div>
                                        </div>

                                        <div className="flex shrink-0 items-center gap-2">
                                            <span className="text-xs text-muted-foreground">
                                                {new Date(
                                                    inquiry.created_at,
                                                ).toLocaleDateString()}
                                            </span>
                                            {inquiry.is_handled ? (
                                                <StatusPill status="handled" />
                                            ) : (
                                                <PillButton
                                                    variant="soft"
                                                    size="sm"
                                                    onClick={() =>
                                                        handleMarkHandled(
                                                            inquiry.id,
                                                        )
                                                    }
                                                >
                                                    <Check className="h-3.5 w-3.5" />
                                                    Mark handled
                                                </PillButton>
                                            )}
                                        </div>
                                    </div>

                                    <p className="text-sm whitespace-pre-line text-muted-foreground">
                                        {inquiry.message}
                                    </p>
                                </SoftTile>
                            ))}
                        </div>
                    )}
                </Panel>

                <Pagination links={inquiries.links} />
            </div>
        </AppSidebarLayout>
    );
}
