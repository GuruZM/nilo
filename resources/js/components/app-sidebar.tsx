import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    edit as editAppearance,
} from '@/routes/appearance';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { show as showTwoFactor } from '@/routes/two-factor';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import { LayoutGrid } from 'lucide-react';

import AppLogo from './app-logo';
import { CompanySwitcher } from './company-switcher';

import {
    Building2,
    Coins,
    FileSignature,
    FileText,
    LayoutTemplate,
    Palette,
    Settings2,
    ShieldCheck,
    Users,
} from 'lucide-react';

const navigationItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Companies',
        href: '/companies',
        icon: Building2,
    },
    {
        title: 'Invoices',
        href: '/invoices',
        icon: FileText,
    },
    {
        title: 'Quotations',
        href: '/quotations',
        icon: FileSignature,
    },
    {
        title: 'Clients',
        href: '/clients',
        icon: Users,
    },
    {
        title: 'Configurations',
        href: '#',
        icon: Settings2,
        items: [
            {
                title: 'Templates',
                href: '#',
                icon: LayoutTemplate,
                items: [
                    {
                        title: 'Invoice Templates',
                        href: '/settings/invoice-templates',
                    },
                    {
                        title: 'Quotation Templates',
                        href: '/settings/quotation-templates',
                    },
                ],
            },
            {
                title: 'Currencies',
                href: '/settings/currencies',
                icon: Coins,
            },
            {
                title: 'Appearance',
                href: editAppearance(),
                icon: Palette,
            },
            {
                title: 'Two-Factor Auth',
                href: showTwoFactor(),
                icon: ShieldCheck,
            },
        ],
    },
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <div className="px-2 pt-2 pb-1">
                    <CompanySwitcher />
                </div>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={navigationItems} />
            </SidebarContent>

            <SidebarFooter>
                {/* <NavFooter items={footerNavItems} className="mt-auto" /> */}
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
