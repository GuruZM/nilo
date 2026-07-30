import { Head, Link, router, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { CheckCircle2, LayoutTemplate, Plus, Star } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types/index.d';

import {
    Chip,
    EmptyState,
    Panel,
    PanelHeader,
    PillButton,
    pillButtonClass,
} from '@/components/dashboard/primitives';
import { cn } from '@/lib/utils';

type Template = {
    id: number;
    name: string;
    is_default: boolean;
    created_at: string;
};

type FlashMessages = {
    success?: string;
    error?: string;
    info?: string;
};

type TemplateModule = {
    type: 'invoice' | 'quotation';
    singularTitle: string;
    pluralTitle: string;
    basePath: string;
    createPath: string;
};

export default function InvoiceTemplatesIndex({
    templates,
    module,
}: {
    templates: Template[];
    module: TemplateModule;
}) {
    const { flash } = usePage<{ flash?: FlashMessages }>().props;
    const singularLabel = module.singularTitle.toLowerCase();
    const pluralLabel = module.pluralTitle.toLowerCase();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Settings', href: '/settings/profile' },
        { title: module.pluralTitle, href: module.basePath },
    ];

    React.useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
        if (flash?.info) toast.message(flash.info);
    }, [flash?.success, flash?.error, flash?.info]);

    const makeDefault = (id: number) => {
        router.post(
            `${module.basePath}/${id}/default`,
            {},
            {
                preserveScroll: true,
                onSuccess: () =>
                    toast.success(`Default ${singularLabel} updated.`),
                onError: (errors) =>
                    toast.error(errors?.template || 'Failed to set default.'),
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={module.pluralTitle} />

            <motion.div
                initial={{ opacity: 0, y: 14 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.25, ease: 'easeOut' }}
                className="flex w-full flex-col gap-4 py-6"
            >
                <Panel>
                    <PanelHeader
                        icon={LayoutTemplate}
                        title={module.pluralTitle}
                        subtitle={`Create and customize ${pluralLabel} for the active company.`}
                        action={
                            <Link
                                href={module.createPath}
                                className={pillButtonClass('solid', 'sm')}
                            >
                                <Plus className="h-3.5 w-3.5" />
                                New template
                            </Link>
                        }
                    />

                    {templates.length === 0 ? (
                        <EmptyState
                            icon={LayoutTemplate}
                            title={`No ${pluralLabel} yet`}
                            description={`Create your first ${singularLabel} to keep your ${
                                module.type === 'quotation'
                                    ? 'quotations'
                                    : 'invoices'
                            } consistently branded.`}
                            action={
                                <Link
                                    href={module.createPath}
                                    className={pillButtonClass('solid', 'md')}
                                >
                                    <Plus className="h-4 w-4" />
                                    Create template
                                </Link>
                            }
                        />
                    ) : (
                        <div className="grid grid-cols-1 gap-2 md:grid-cols-2">
                            {templates.map((t, index) => (
                                <motion.div
                                    key={t.id}
                                    initial={{ opacity: 0, y: 8 }}
                                    animate={{ opacity: 1, y: 0 }}
                                    transition={{
                                        duration: 0.2,
                                        delay: Math.min(0.03 * index, 0.18),
                                    }}
                                    className={cn(
                                        'rounded-2xl p-4 transition',
                                        t.is_default
                                            ? 'bg-brand-50/70 dark:bg-brand-500/10'
                                            : 'bg-muted/50 dark:bg-white/5',
                                    )}
                                >
                                    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div className="min-w-0">
                                            <div className="flex items-center gap-2">
                                                <div className="truncate text-sm font-semibold">
                                                    {t.name}
                                                </div>
                                                {t.is_default ? (
                                                    <Chip
                                                        interactive={false}
                                                        active
                                                    >
                                                        <CheckCircle2 className="h-3.5 w-3.5" />
                                                        Default
                                                    </Chip>
                                                ) : null}
                                            </div>
                                            <div className="mt-1 text-xs text-muted-foreground">
                                                Created{' '}
                                                {new Date(
                                                    t.created_at,
                                                ).toLocaleDateString()}
                                            </div>
                                        </div>

                                        <div className="flex shrink-0 items-center gap-2">
                                            <Link
                                                href={`${module.basePath}/${t.id}/edit`}
                                                className={pillButtonClass(
                                                    'ghost',
                                                    'sm',
                                                )}
                                            >
                                                Edit
                                            </Link>

                                            <PillButton
                                                variant="soft"
                                                size="sm"
                                                onClick={() =>
                                                    makeDefault(t.id)
                                                }
                                                disabled={t.is_default}
                                            >
                                                <Star className="h-3.5 w-3.5" />
                                                Default
                                            </PillButton>
                                        </div>
                                    </div>
                                </motion.div>
                            ))}
                        </div>
                    )}
                </Panel>
            </motion.div>
        </AppLayout>
    );
}
