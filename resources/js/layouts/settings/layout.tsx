import { Panel } from '@/components/dashboard/primitives';
import { cn } from '@/lib/utils';
import { edit as editPassword } from '@/routes/password';
import { edit } from '@/routes/profile';
import { show as showTwoFactor } from '@/routes/two-factor';
import { type NavItem } from '@/types/index.d';
import { Link, usePage } from '@inertiajs/react';
import {
    CreditCard,
    Fingerprint,
    KeyRound,
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
        title: 'Two-factor auth',
        href: showTwoFactor(),
        icon: ShieldCheck,
    },
    {
        title: 'Passkeys',
        href: '/settings/passkeys',
        icon: Fingerprint,
    },
    {
        title: 'Billing',
        href: '/subscription',
        icon: CreditCard,
    },
];

const toPath = (href: NavItem['href']): string =>
    typeof href === 'string' ? href : href.url;

export default function SettingsLayout({ children }: PropsWithChildren) {
    /**
     * Inertia's page url rather than `window.location`, so the active row is
     * correct on the server render instead of the nav blanking out.
     */
    const currentPath = usePage().url.split('?')[0];

    return (
        <div className="flex w-full flex-col gap-4 py-3 lg:flex-row">
            <aside className="w-full lg:w-56 lg:shrink-0">
                <Panel className="p-2 sm:p-2 lg:sticky lg:top-3">
                    <nav className="grid grid-cols-2 gap-1 sm:grid-cols-4 lg:grid-cols-1">
                        {sidebarNavItems.map((item) => {
                            const href = toPath(item.href);

                            const isActive =
                                currentPath === href ||
                                currentPath.startsWith(href + '/');

                            return (
                                <Link
                                    key={href}
                                    href={item.href}
                                    prefetch
                                    aria-current={isActive ? 'page' : undefined}
                                    className={cn(
                                        'flex items-center gap-2.5 rounded-2xl px-3 py-2 text-sm font-medium transition',
                                        isActive
                                            ? 'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-200'
                                            : 'text-muted-foreground hover:bg-muted/60 hover:text-foreground dark:hover:bg-white/5',
                                    )}
                                >
                                    {item.icon && (
                                        <item.icon className="h-4 w-4 shrink-0" />
                                    )}
                                    <span className="truncate">
                                        {item.title}
                                    </span>
                                </Link>
                            );
                        })}
                    </nav>
                </Panel>
            </aside>

            <section className="flex min-w-0 flex-1 flex-col gap-4">
                {children}
            </section>
        </div>
    );
}
