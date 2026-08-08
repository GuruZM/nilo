import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { SidebarGroup, SidebarMenu } from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';

const resolveHref = (href?: NavItem['href']): string => {
    if (!href) {
        return '';
    }

    return typeof href === 'string' ? href : href.url;
};

const isItemActive = (item: NavItem, currentPath: string): boolean => {
    const href = resolveHref(item.href);

    if (href && href !== '#') {
        const pathWithoutQuery = currentPath.split('?')[0];
        if (
            pathWithoutQuery === href ||
            pathWithoutQuery.startsWith(href + '/')
        ) {
            return true;
        }
    }

    return (item.items ?? []).some((child) => isItemActive(child, currentPath));
};

/**
 * Shared row styling for every nav entry. Top-level rows are pills; nested
 * rows sit against the guide rail drawn by their parent list.
 */
const rowClass = (isActive: boolean, isTopLevel: boolean): string =>
    cn(
        'group/row relative flex w-full items-center gap-3 rounded-2xl transition',
        'focus-visible:ring-2 focus-visible:ring-brand-400 focus-visible:outline-none',
        isTopLevel
            ? 'px-3 py-2.5 text-sm font-medium'
            : 'px-3 py-2 text-[13px]',
        isActive
            ? 'bg-brand-50 font-semibold text-brand-700 dark:bg-brand-500/15 dark:text-brand-200'
            : 'text-muted-foreground hover:bg-muted hover:text-foreground dark:hover:bg-white/5',
        // Icon-collapsed rail: centre the glyph, drop the padding.
        'group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:px-0',
    );

const iconClass = (isActive: boolean): string =>
    cn(
        'h-4.5 w-4.5 shrink-0 transition',
        isActive
            ? 'text-brand-600 dark:text-brand-300'
            : 'text-muted-foreground group-hover/row:text-foreground',
    );

const labelClass =
    'flex-1 truncate text-left group-data-[collapsible=icon]:hidden';

/** Turns in step with the panel below it, so the two read as one movement. */
const chevronClass =
    'h-4 w-4 shrink-0 text-muted-foreground transition-transform duration-150 ease-out group-data-[collapsible=icon]:hidden';

function NavItemNode({
    item,
    currentPath,
    depth = 0,
}: {
    item: NavItem;
    currentPath: string;
    depth?: number;
}) {
    const hasChildren = (item.items?.length ?? 0) > 0;
    const href = resolveHref(item.href);
    const linkHref = item.href;
    const isActive = isItemActive(item, currentPath);
    const isTopLevel = depth === 0;

    if (hasChildren) {
        return (
            <li>
                <Collapsible
                    defaultOpen={isActive}
                    className="group/collapsible"
                >
                    <CollapsibleTrigger
                        className={rowClass(isActive, isTopLevel)}
                        title={item.title}
                    >
                        {item.icon ? (
                            <item.icon className={iconClass(isActive)} />
                        ) : null}
                        <span className={labelClass}>{item.title}</span>
                        <ChevronRight
                            className={cn(
                                chevronClass,
                                'group-data-[state=open]/collapsible:rotate-90',
                            )}
                        />
                    </CollapsibleTrigger>

                    <CollapsibleContent
                        className={cn(
                            // overflow-hidden is what makes the height keyframe
                            // clip the rows instead of letting them spill out.
                            'overflow-hidden',
                            'data-[state=open]:animate-collapsible-down',
                            'data-[state=closed]:animate-collapsible-up',
                            'motion-reduce:animate-none',
                            'group-data-[collapsible=icon]:hidden',
                        )}
                    >
                        <ul className="mt-1 ml-5 space-y-0.5 border-l border-sidebar-border pl-2">
                            {item.items?.map((child) => (
                                <NavItemNode
                                    key={`${item.title}-${child.title}`}
                                    item={child}
                                    currentPath={currentPath}
                                    depth={depth + 1}
                                />
                            ))}
                        </ul>
                    </CollapsibleContent>
                </Collapsible>
            </li>
        );
    }

    if (!href || !linkHref) {
        return null;
    }

    return (
        <li>
            <Link
                href={linkHref}
                prefetch
                title={item.title}
                className={rowClass(isActive, isTopLevel)}
            >
                {item.icon ? (
                    <item.icon className={iconClass(isActive)} />
                ) : (
                    <span
                        className={cn(
                            'h-1.5 w-1.5 shrink-0 rounded-full transition',
                            isActive
                                ? 'bg-brand-600'
                                : 'bg-muted-foreground/40',
                        )}
                    />
                )}
                <span className={labelClass}>{item.title}</span>
            </Link>
        </li>
    );
}

export function NavMain({
    items = [],
    label,
}: {
    items: NavItem[];
    label?: string;
}) {
    const page = usePage();
    const currentPath = page.url;

    return (
        <SidebarGroup className="px-3 py-0">
            {label ? (
                <div className="px-3 pt-4 pb-2 text-[11px] font-semibold tracking-[0.08em] text-muted-foreground uppercase group-data-[collapsible=icon]:hidden">
                    {label}
                </div>
            ) : null}
            <SidebarMenu className="gap-0.5">
                {items.map((item) => (
                    <NavItemNode
                        key={item.title}
                        item={item}
                        currentPath={currentPath}
                    />
                ))}
            </SidebarMenu>
        </SidebarGroup>
    );
}
