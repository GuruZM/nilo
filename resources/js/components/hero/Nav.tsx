import AppLogo from '@/components/app-logo';
import { cn } from '@/lib/utils';
import { dashboard, home, register } from '@/routes';
import type { Auth } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Menu, X } from 'lucide-react';
import { useEffect, useState } from 'react';

const sections = [
    { href: '#features', label: 'How it works' },
    { href: '#pricing', label: 'Pricing' },
    { href: '#contact', label: 'Support' },
];

export default function Nav() {
    const { auth } = usePage<{ auth?: Auth }>().props;
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const [scrolled, setScrolled] = useState(false);

    useEffect(() => {
        const handleScroll = () => setScrolled(window.scrollY > 50);
        handleScroll();
        window.addEventListener('scroll', handleScroll, { passive: true });
        return () => window.removeEventListener('scroll', handleScroll);
    }, []);

    const onDark = !scrolled;
    const linkClass = onDark
        ? 'text-white/80 hover:text-white'
        : 'text-foreground/70 hover:text-foreground';

    return (
        <nav
            className={`fixed top-0 z-50 w-full transition-all duration-300 ${
                scrolled
                    ? 'border-b border-border bg-background/90 shadow-md shadow-brand-950/5 backdrop-blur-xl'
                    : 'bg-transparent'
            }`}
        >
            <div className="mx-auto max-w-7xl px-6 pt-2">
                <div className="flex h-16 items-center justify-between">
                    <Link href={home()} aria-label="Nilo home">
                        <AppLogo
                            className={cn(
                                'transition-all duration-300',
                                onDark && 'brightness-0 invert',
                            )}
                        />
                    </Link>

                    <div className="hidden items-center gap-8 md:flex">
                        {sections.map((item) => (
                            <a
                                key={item.href}
                                href={item.href}
                                className={`text-sm font-medium transition-colors duration-200 ${linkClass}`}
                            >
                                {item.label}
                            </a>
                        ))}

                        {auth?.user ? (
                            <Link
                                href={dashboard()}
                                className={`rounded-lg px-5 py-2.5 text-sm font-semibold shadow-md shadow-brand-950/20 transition-all duration-300 hover:-translate-y-0.5 active:translate-y-0 ${
                                    onDark
                                        ? 'bg-white text-brand-900 hover:bg-brand-100'
                                        : 'bg-brand text-white hover:bg-brand-700'
                                }`}
                            >
                                Dashboard
                            </Link>
                        ) : (
                            <Link
                                href={register()}
                                className={`rounded-lg px-5 py-2.5 text-sm font-semibold shadow-md shadow-brand-950/20 transition-all duration-300 hover:-translate-y-0.5 active:translate-y-0 ${
                                    onDark
                                        ? 'bg-white text-brand-900 hover:bg-brand-100'
                                        : 'bg-brand text-white hover:bg-brand-700'
                                }`}
                            >
                                Get started
                            </Link>
                        )}
                    </div>

                    <button
                        type="button"
                        className={`cursor-pointer p-2 md:hidden ${onDark ? 'text-white' : 'text-foreground'}`}
                        onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                        aria-label={mobileMenuOpen ? 'Close menu' : 'Open menu'}
                    >
                        {mobileMenuOpen ? (
                            <X className="h-6 w-6" />
                        ) : (
                            <Menu className="h-6 w-6" />
                        )}
                    </button>
                </div>

                {mobileMenuOpen && (
                    <div className="mb-4 rounded-2xl border border-border bg-background py-4 shadow-xl shadow-brand-950/10 md:hidden">
                        <div className="flex flex-col gap-4 px-6">
                            {sections.map((item) => (
                                <a
                                    key={item.href}
                                    href={item.href}
                                    onClick={() => setMobileMenuOpen(false)}
                                    className="text-sm font-medium text-foreground/80 transition-colors hover:text-foreground"
                                >
                                    {item.label}
                                </a>
                            ))}

                            <div className="flex items-center justify-between border-t border-border pt-4">
                                {auth?.user ? (
                                    <Link
                                        href={dashboard()}
                                        className="rounded-lg bg-brand px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-brand-900/20 transition-all duration-300 hover:-translate-y-0.5 hover:bg-brand-700 active:translate-y-0"
                                    >
                                        Dashboard
                                    </Link>
                                ) : (
                                    <Link
                                        href={register()}
                                        className="rounded-lg bg-brand px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-brand-900/20 transition-all duration-300 hover:-translate-y-0.5 hover:bg-brand-700 active:translate-y-0"
                                    >
                                        Get started
                                    </Link>
                                )}
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </nav>
    );
}
