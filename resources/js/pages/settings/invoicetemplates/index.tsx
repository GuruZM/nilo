import { Head, Link, router, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { CheckCircle2, LayoutTemplate, Plus, Star } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types/index.d';

import HeadingSmall from '@/components/heading-small';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Settings', href: '/settings/profile' },
    { title: 'Invoice templates', href: '/settings/invoice-templates' },
];

type Template = {
    id: number;
    name: string;
    is_default: boolean;
    created_at: string;
};

export default function InvoiceTemplatesIndex({
    templates,
}: {
    templates: Template[];
}) {
    const page = usePage() as any;

    // Optional: show flash toasts globally if you aren’t already
    React.useEffect(() => {
        const s = page.props?.flash?.success;
        const e = page.props?.flash?.error;
        const i = page.props?.flash?.info;

        if (s) toast.success(s);
        if (e) toast.error(e);
        if (i) toast.message(i);
    }, [
        page.props?.flash?.success,
        page.props?.flash?.error,
        page.props?.flash?.info,
    ]);

    const makeDefault = (id: number) => {
        router.post(
            `/settings/invoice-templates/${id}/default`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => toast.success('Default template updated.'),
                onError: (errors) =>
                    toast.error(errors?.template || 'Failed to set default.'),
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Invoice templates" />

            <SettingsLayout>
                <motion.div
                    initial={{ opacity: 0, y: 14 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ duration: 0.25, ease: 'easeOut' }}
                    className="space-y-6"
                >
                    <div className="flex items-start justify-between gap-4">
                        <div className="space-y-1">
                            <HeadingSmall
                                title="Invoice templates"
                                description="Create and customize invoice templates for the active company."
                            />
                        </div>

                        <Button
                            asChild
                            className="gap-2 rounded-xl transition-transform hover:scale-[1.02] active:scale-[0.98]"
                        >
                            <Link href="/settings/invoice-templates/create">
                                <Plus className="h-4 w-4" />
                                New template
                            </Link>
                        </Button>
                    </div>

                    <Separator />

                    {templates.length === 0 ? (
                        <Card className="rounded-2xl border bg-background p-6 shadow-sm">
                            <div className="flex items-start gap-3">
                                <div className="grid h-11 w-11 place-items-center rounded-2xl bg-muted">
                                    <LayoutTemplate className="h-6 w-6 opacity-80" />
                                </div>
                                <div className="space-y-1">
                                    <div className="text-sm font-semibold">
                                        No invoice templates yet
                                    </div>
                                    <div className="text-sm text-muted-foreground">
                                        Create your first template to unlock
                                        invoice ; and PDF generation later.
                                    </div>
                                    <Button
                                        className="mt-3 gap-2 rounded-xl"
                                        onClick={() =>
                                            router.visit(
                                                '/settings/invoice-templates/create',
                                            )
                                        }
                                    >
                                        <Plus className="h-4 w-4" />
                                        Create template
                                    </Button>
                                </div>
                            </div>
                        </Card>
                    ) : (
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            {templates.map((t) => (
                                <motion.div
                                    key={t.id}
                                    initial={{ opacity: 0, y: 10 }}
                                    animate={{ opacity: 1, y: 0 }}
                                    transition={{ duration: 0.2 }}
                                >
                                    <Card
                                        className={cn(
                                            'rounded-2xl border bg-background p-5 shadow-sm',
                                            t.is_default &&
                                                'border-foreground/20 bg-muted/20',
                                        )}
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="min-w-0">
                                                <div className="flex items-center gap-2">
                                                    <div className="truncate text-sm font-semibold">
                                                        {t.name}
                                                    </div>
                                                    {t.is_default && (
                                                        <span className="inline-flex items-center gap-1 rounded-full bg-muted px-2 py-0.5 text-xs">
                                                            <CheckCircle2 className="h-3.5 w-3.5" />
                                                            Default
                                                        </span>
                                                    )}
                                                </div>
                                                <div className="mt-1 text-xs text-muted-foreground">
                                                    Created{' '}
                                                    {new Date(
                                                        t.created_at,
                                                    ).toLocaleDateString()}
                                                </div>
                                            </div>

                                            <div className="flex items-center gap-2">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    className="rounded-xl"
                                                    asChild
                                                >
                                                    <Link
                                                        href={`/settings/invoice-templates/${t.id}/edit`}
                                                    >
                                                        Edit
                                                    </Link>
                                                </Button>

                                                <Button
                                                    variant={
                                                        t.is_default
                                                            ? 'secondary'
                                                            : 'outline'
                                                    }
                                                    size="sm"
                                                    className="gap-2 rounded-xl"
                                                    onClick={() =>
                                                        makeDefault(t.id)
                                                    }
                                                    disabled={t.is_default}
                                                >
                                                    <Star className="h-4 w-4" />
                                                    Default
                                                </Button>
                                            </div>
                                        </div>
                                    </Card>
                                </motion.div>
                            ))}
                        </div>
                    )}
                </motion.div>
            </SettingsLayout>
        </AppLayout>
    );
}
