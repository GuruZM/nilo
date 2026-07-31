import { NavMain } from '@/components/nav-main';
import {
    Sidebar,
    SidebarContent,
    SidebarHeader,
} from '@/components/ui/sidebar';
import { WorkspaceSwitchers } from '@/components/workspace-switchers';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';

import AppLogo from './app-logo';

import {
    ArrowLeft,
    Building2,
    Coins,
    CreditCard,
    Factory,
    FileMinus,
    FileSignature,
    FileText,
    Files,
    LayoutGrid,
    LayoutTemplate,
    MessageSquare,
    Palette,
    Shield,
    ShoppingCart,
    Tag,
    TicketPercent,
    Truck,
    UserCog,
    Users,
} from 'lucide-react';

const mainNavItems: NavItem[] = [
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
    /**
     * The document register: every type the app can raise, in the order a job
     * moves through them - quote, bill, credit, dispatch.
     *
     * Purchase orders point at a supplier rather than a client, so they are the
     * one entry here whose money runs the other way, and splitting them out
     * into a "Purchasing" group beside Suppliers was the obvious alternative.
     * They stay because the question a user arrives with is "where do I raise a
     * purchase order", and the answer has to be the same place as every other
     * document they raise. Direction of trade is an accounting taxonomy, not a
     * navigation one. Being last in the list carries the distinction far enough.
     */
    {
        title: 'Documents',
        href: '#',
        icon: Files,
        items: [
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
                title: 'Credit Notes',
                href: '/credit-notes',
                icon: FileMinus,
            },
            {
                title: 'Delivery Notes',
                href: '/delivery-notes',
                icon: Truck,
            },
            {
                title: 'Purchase Orders',
                href: '/purchase-orders',
                icon: ShoppingCart,
            },
        ],
    },
    /**
     * Clients and suppliers are the two address books, not documents, so they
     * sit outside the group above and next to each other. Both stay top level:
     * nesting suppliers under a purchasing section would cost it a click that
     * the identically-shaped client list does not pay.
     */
    {
        title: 'Clients',
        href: '/clients',
        icon: Users,
    },
    {
        title: 'Suppliers',
        href: '/suppliers',
        icon: Factory,
    },
];

const settingsNavItems: NavItem[] = [
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
        title: 'Profile & Security',
        href: editProfile(),
        icon: UserCog,
    },
];

const adminNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/admin',
        icon: Shield,
    },
    {
        title: 'Users',
        href: '/admin/users',
        icon: Users,
    },
    {
        title: 'Plans',
        href: '/admin/plans',
        icon: Tag,
    },
    {
        title: 'Coupons',
        href: '/admin/coupons',
        icon: TicketPercent,
    },
    {
        title: 'Payments',
        href: '/admin/payments',
        icon: CreditCard,
    },
    {
        title: 'Inquiries',
        href: '/admin/inquiries',
        icon: MessageSquare,
    },
];

/** Shown only to staff who also hold a plan, so the link always resolves. */
const backToAppNavItems: NavItem[] = [
    {
        title: 'Back to App',
        href: dashboard(),
        icon: ArrowLeft,
    },
];

/**
 * Admin mode is a property of the page being viewed, not of the account: a
 * super admin who also runs a company still gets the full tenant rail
 * everywhere outside /admin.
 */
const isAdminPath = (url: string): boolean => {
    const path = url.split('?')[0];

    return path === '/admin' || path.startsWith('/admin/');
};

/** Soft, borderless panel shared by every detached block in the left rail. */
const panelClass = cn(
    'rounded-3xl bg-sidebar',
    'shadow-[0_1px_2px_0_rgb(16_24_40/0.04),0_16px_40px_-18px_rgb(16_24_40/0.22)]',
    'dark:shadow-none dark:ring-1 dark:ring-white/10',
);

export function AppSidebar() {
    const page = usePage<SharedData>();
    const { auth, subscription } = page.props;
    const isAdmin = auth?.roles?.includes('super-admin');
    const inAdminMode = isAdmin && isAdminPath(page.url);
    // Staff without a plan would only be bounced back here by the subscription
    // middleware, so the way out is offered only when the app is reachable.
    const canReturnToApp = subscription?.status === 'active';

    return (
        <Sidebar
            collapsible="icon"
            variant="floating"
            className={cn(
                // pt-4 (vs the variant's default p-2) lines the panel's top edge
                // up with the floating top bar, which sits at m-2 + mt-2.
                'md:left-[10%] md:h-fit md:pt-4',
                // The rail itself is only a column: each block below carries its
                // own panel so they read as detached cards, not one sidebar.
                '[&>[data-sidebar=sidebar]]:gap-3 [&>[data-sidebar=sidebar]]:border-none',
                '[&>[data-sidebar=sidebar]]:bg-transparent [&>[data-sidebar=sidebar]]:shadow-none',
            )}
        >
            <div className={cn(panelClass, 'flex flex-col')}>
                <SidebarHeader className="px-5 pt-5 pb-3 group-data-[collapsible=icon]:px-2">
                    <Link
                        href={inAdminMode ? '/admin' : dashboard()}
                        prefetch
                        className="flex items-center rounded-xl transition group-data-[collapsible=icon]:justify-center hover:opacity-80"
                    >
                        <AppLogo className="h-6" />
                    </Link>
                </SidebarHeader>

                <SidebarContent className="gap-0 pb-4">
                    {!inAdminMode && (
                        <>
                            <NavMain items={mainNavItems} label="Menu" />
                            <NavMain
                                items={settingsNavItems}
                                label="Settings"
                            />
                        </>
                    )}
                    {isAdmin && <NavMain items={adminNavItems} label="Admin" />}
                    {inAdminMode && canReturnToApp && (
                        <NavMain items={backToAppNavItems} label="App" />
                    )}
                </SidebarContent>
            </div>

            {/* Company and currency are tenant scope, meaningless in admin. */}
            {!inAdminMode && (
                <WorkspaceSwitchers
                    className={cn(
                        panelClass,
                        'p-2 group-data-[collapsible=icon]:p-1.5',
                    )}
                />
            )}
        </Sidebar>
    );
}
