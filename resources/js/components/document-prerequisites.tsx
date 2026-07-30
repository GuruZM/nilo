import {
    Panel,
    PillButton,
    SoftTile,
    pillButtonClass,
} from '@/components/dashboard/primitives';
import { Link } from '@inertiajs/react';
import { ArrowRight, CircleAlert } from 'lucide-react';

export type PrerequisiteBlocker = {
    key: 'company' | 'template' | 'client' | 'currency';
    title: string;
    description: string;
    action_label: string;
    action_href: string;
};

export type DocumentPrerequisites = {
    can_create: boolean;
    has_active_company: boolean;
    has_templates: boolean;
    has_clients: boolean;
    has_currencies: boolean;
    blockers: PrerequisiteBlocker[];
};

/**
 * Fallback for pages rendered before the server started sending the prop.
 */
export const permissivePrerequisites: DocumentPrerequisites = {
    can_create: true,
    has_active_company: true,
    has_templates: true,
    has_clients: true,
    has_currencies: true,
    blockers: [],
};

/**
 * Replaces a create form when prerequisites are unmet. Each blocker links to the
 * page that resolves it, in the order they have to be resolved.
 */
export function PrerequisiteGate({
    documentLabel,
    blockers,
}: {
    documentLabel: string;
    blockers: PrerequisiteBlocker[];
}) {
    if (blockers.length === 0) {
        return null;
    }

    return (
        <Panel>
            <div className="flex items-start gap-3">
                <div className="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-amber-500/10 text-amber-600 dark:text-amber-400">
                    <CircleAlert className="h-6 w-6" />
                </div>

                <div className="min-w-0 space-y-1">
                    <div className="text-sm font-semibold">
                        Finish setting up before you create a {documentLabel}
                    </div>
                    <p className="text-sm text-muted-foreground">
                        {blockers.length === 1
                            ? 'One thing is still missing:'
                            : `${blockers.length} things are still missing:`}
                    </p>
                </div>
            </div>

            <ul className="mt-5 flex flex-col gap-2">
                {blockers.map((blocker, index) => (
                    <li key={blocker.key}>
                        <SoftTile className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex min-w-0 items-start gap-3">
                                <span className="mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-full bg-brand-50 text-xs font-semibold text-brand-700 dark:bg-brand-500/15 dark:text-brand-200">
                                    {index + 1}
                                </span>
                                <div className="min-w-0">
                                    <div className="text-sm font-medium">
                                        {blocker.title}
                                    </div>
                                    <div className="text-sm text-muted-foreground">
                                        {blocker.description}
                                    </div>
                                </div>
                            </div>

                            <Link
                                href={blocker.action_href}
                                className={pillButtonClass(
                                    'solid',
                                    'sm',
                                    'shrink-0',
                                )}
                            >
                                {blocker.action_label}
                                <ArrowRight className="h-4 w-4" />
                            </Link>
                        </SoftTile>
                    </li>
                ))}
            </ul>
        </Panel>
    );
}

/**
 * Create button that becomes a genuinely disabled button when blocked. Never
 * renders `asChild` with `disabled`, which leaves the underlying anchor clickable.
 */
export function CreateDocumentButton({
    href,
    label,
    canCreate,
    icon,
    className,
}: {
    href: string;
    label: string;
    canCreate: boolean;
    icon?: React.ReactNode;
    className?: string;
}) {
    if (!canCreate) {
        return (
            <PillButton
                disabled
                title="Finish setting up before you can create this."
                className={className}
            >
                {icon}
                {label}
            </PillButton>
        );
    }

    return (
        <Link href={href} className={pillButtonClass('solid', 'md', className)}>
            {icon}
            {label}
        </Link>
    );
}
