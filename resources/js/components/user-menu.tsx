import { Link, router } from '@inertiajs/react';
import { ChevronDown, LogOut, Settings } from 'lucide-react';

import { FloatingMenu, menuRowClass } from '@/components/floating-menu';
import { useInitials } from '@/hooks/use-initials';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { cn } from '@/lib/utils';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';
import { type User } from '@/types';

function Avatar({ user, className }: { user: User; className?: string }) {
    const getInitials = useInitials();

    if (user.avatar) {
        return (
            <img
                src={user.avatar}
                alt={user.name}
                className={cn('shrink-0 rounded-full object-cover', className)}
            />
        );
    }

    return (
        <span
            className={cn(
                'flex shrink-0 items-center justify-center rounded-full',
                'bg-brand-600 text-[11px] font-semibold text-brand-foreground',
                className,
            )}
        >
            {getInitials(user.name)}
        </span>
    );
}

/**
 * Account chip for the floating top bar — same borderless dropdown language as
 * the workspace switchers rather than the stock menu chrome.
 */
export function UserMenu({ user }: { user: User }) {
    const cleanup = useMobileNavigation();

    const handleLogout = () => {
        cleanup();
        router.flushAll();
    };

    return (
        <FloatingMenu
            placement="bottom-end"
            panelClassName="w-60"
            trigger={({ open, toggle }) => (
                <button
                    type="button"
                    aria-haspopup="menu"
                    aria-expanded={open}
                    onClick={toggle}
                    className={cn(
                        'flex items-center gap-2 rounded-full py-1 pr-2 pl-1 transition',
                        'focus-visible:ring-2 focus-visible:ring-brand-400 focus-visible:outline-none',
                        open
                            ? 'bg-muted dark:bg-white/10'
                            : 'hover:bg-muted dark:hover:bg-white/5',
                    )}
                >
                    <Avatar user={user} className="h-8 w-8" />
                    <span className="hidden max-w-[140px] truncate text-[13px] font-medium text-foreground sm:block">
                        {user.name}
                    </span>
                    <ChevronDown
                        className={cn(
                            'h-4 w-4 shrink-0 text-muted-foreground transition-transform duration-200',
                            open && 'rotate-180',
                        )}
                    />
                </button>
            )}
        >
            {(close) => (
                <>
                    <div className="flex items-center gap-2.5 px-2.5 py-2.5">
                        <Avatar user={user} className="h-9 w-9" />
                        <div className="min-w-0">
                            <p className="truncate text-[13px] font-medium text-foreground">
                                {user.name}
                            </p>
                            <p className="truncate text-xs text-muted-foreground">
                                {user.email}
                            </p>
                        </div>
                    </div>

                    <div className="my-1 h-px bg-border" />

                    <Link
                        href={edit()}
                        prefetch
                        onClick={() => {
                            close();
                            cleanup();
                        }}
                        className={menuRowClass()}
                    >
                        <Settings className="h-4 w-4 shrink-0" />
                        Settings
                    </Link>

                    <Link
                        href={logout()}
                        as="button"
                        onClick={() => {
                            close();
                            handleLogout();
                        }}
                        data-test="logout-button"
                        className={menuRowClass()}
                    >
                        <LogOut className="h-4 w-4 shrink-0" />
                        Log out
                    </Link>
                </>
            )}
        </FloatingMenu>
    );
}
