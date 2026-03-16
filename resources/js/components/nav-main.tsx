import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';

const resolveHref = (href?: NavItem['href']): string => {
    if (! href) {
        return '';
    }

    return typeof href === 'string' ? href : href.url;
};

const isItemActive = (item: NavItem, currentPath: string): boolean => {
    const href = resolveHref(item.href);

    if (href && currentPath.startsWith(href)) {
        return true;
    }

    return (item.items ?? []).some((child) => isItemActive(child, currentPath));
};

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

    if (hasChildren) {
        if (depth === 0) {
            return (
                <SidebarMenuItem>
                    <Collapsible
                        defaultOpen={isActive}
                        className="group/collapsible"
                    >
                        <CollapsibleTrigger asChild>
                            <SidebarMenuButton
                                isActive={isActive}
                                tooltip={{ children: item.title }}
                            >
                                {item.icon && <item.icon />}
                                <span>{item.title}</span>
                                <ChevronRight className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                            </SidebarMenuButton>
                        </CollapsibleTrigger>

                        <CollapsibleContent>
                            <SidebarMenuSub>
                                {item.items?.map((child) => (
                                    <NavItemNode
                                        key={`${item.title}-${child.title}`}
                                        item={child}
                                        currentPath={currentPath}
                                        depth={depth + 1}
                                    />
                                ))}
                            </SidebarMenuSub>
                        </CollapsibleContent>
                    </Collapsible>
                </SidebarMenuItem>
            );
        }

        return (
            <SidebarMenuSubItem>
                <Collapsible
                    defaultOpen={isActive}
                    className="group/sub-collapsible"
                >
                    <CollapsibleTrigger asChild>
                        <SidebarMenuSubButton asChild isActive={isActive}>
                            <button type="button">
                                {item.icon && <item.icon />}
                                <span>{item.title}</span>
                                <ChevronRight className="ml-auto transition-transform duration-200 group-data-[state=open]/sub-collapsible:rotate-90" />
                            </button>
                        </SidebarMenuSubButton>
                    </CollapsibleTrigger>

                    <CollapsibleContent>
                        <SidebarMenuSub>
                            {item.items?.map((child) => (
                                <NavItemNode
                                    key={`${item.title}-${child.title}`}
                                    item={child}
                                    currentPath={currentPath}
                                    depth={depth + 1}
                                />
                            ))}
                        </SidebarMenuSub>
                    </CollapsibleContent>
                </Collapsible>
            </SidebarMenuSubItem>
        );
    }

    if (! href || ! linkHref) {
        return null;
    }

    if (depth === 0) {
        return (
            <SidebarMenuItem>
                <SidebarMenuButton
                    asChild
                    isActive={isActive}
                    tooltip={{ children: item.title }}
                >
                    <Link href={linkHref} prefetch>
                        {item.icon && <item.icon />}
                        <span>{item.title}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        );
    }

    return (
        <SidebarMenuSubItem>
            <SidebarMenuSubButton asChild isActive={isActive}>
                <Link href={linkHref} prefetch>
                    {item.icon && <item.icon />}
                    <span>{item.title}</span>
                </Link>
            </SidebarMenuSubButton>
        </SidebarMenuSubItem>
    );
}

export function NavMain({
    items = [],
    label = 'Platform',
}: {
    items: NavItem[];
    label?: string;
}) {
    const page = usePage();
    const currentPath = page.url;

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>{label}</SidebarGroupLabel>
            <SidebarMenu>
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
