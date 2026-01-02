import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editPassword } from '@/routes/password';
import { edit } from '@/routes/profile';
import { show } from '@/routes/two-factor';
import { type NavItem } from '@/types/index.d';
import { Link } from '@inertiajs/react';
import {
    Coins,
    KeyRound,
    LayoutTemplate,
    Palette,
    ShieldCheck,
    User,
} from 'lucide-react';
import { type PropsWithChildren } from 'react';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profile',
        href: edit(),
        icon: User,
    },
    {
        title: 'Password',
        href: editPassword(),
        icon: KeyRound,
    },
    {
        title: 'Two-Factor Auth',
        href: show(),
        icon: ShieldCheck,
    },
    {
        title: 'Appearance',
        href: editAppearance(),
        icon: Palette,
    },
    {
        title: 'Currencies',
        href: '/settings/currencies',
        icon: Coins,
    },

    // ✅ Invoice Templates (explicit)
    {
        title: 'Invoice Templates',
        href: '/settings/invoice-templates',
        icon: LayoutTemplate,
    },
];

export default function SettingsLayout({ children }: PropsWithChildren) {
    // Prevent SSR mismatch
    if (typeof window === 'undefined') {
        return null;
    }

    const currentPath = window.location.pathname;

    return (
        <div className="px-4 py-6">
            <Heading
                title="Settings"
                description="Manage your profile and account settings"
            />

            <div className="flex flex-col lg:flex-row lg:space-x-12">
                <aside className="w-full max-w-xl lg:w-56">
                    <nav className="flex flex-col space-y-1">
                        {sidebarNavItems.map((item, index) => {
                            const href =
                                typeof item.href === 'string'
                                    ? item.href
                                    : item.href.url;

                            const isActive =
                                currentPath === href ||
                                currentPath.startsWith(href + '/');

                            return (
                                <Button
                                    key={`${href}-${index}`}
                                    size="sm"
                                    variant="ghost"
                                    asChild
                                    className={cn(
                                        'w-full justify-start gap-3 rounded-xl px-3 py-2 transition-colors',
                                        {
                                            'bg-muted font-medium': isActive,
                                        },
                                    )}
                                >
                                    <Link href={item.href}>
                                        {item.icon && (
                                            <item.icon className="h-4 w-4 opacity-80" />
                                        )}
                                        <span>{item.title}</span>
                                    </Link>
                                </Button>
                            );
                        })}
                    </nav>
                </aside>

                <Separator className="my-6 lg:hidden" />

                <div className="flex-1">
                    <section className="space-y-12">{children}</section>
                </div>
            </div>
        </div>
    );
}
