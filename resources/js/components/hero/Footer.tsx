import AppLogo from '@/components/app-logo';
import { login, register } from '@/routes';
import { Link } from '@inertiajs/react';
import { motion, useReducedMotion } from 'framer-motion';
import { ArrowRight } from 'lucide-react';

const links = [
    { href: '#about', label: 'About' },
    { href: '#features', label: 'How it works' },
    { href: '#pricing', label: 'Pricing' },
];

export default function Footer() {
    const reducedMotion = useReducedMotion();

    return (
        <footer className="relative overflow-hidden bg-gradient-to-b from-brand-900 to-brand-950 text-white">
            <div
                aria-hidden
                className="absolute -top-32 right-[-8rem] h-96 w-96 rounded-full bg-brand-500/15 blur-3xl"
            />
            <div
                aria-hidden
                className="absolute bottom-[-10rem] left-[-6rem] h-80 w-80 rounded-full bg-brand-400/10 blur-3xl"
            />
            <motion.div
                className="relative mx-auto max-w-7xl px-6 py-20"
                initial={{ opacity: 0, y: reducedMotion ? 0 : 32 }}
                whileInView={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.4, ease: 'easeOut' }}
                viewport={{ once: true }}
            >
                <div className="flex flex-col gap-8 border-b border-white/10 pb-16 lg:flex-row lg:items-end lg:justify-between">
                    <h2 className="max-w-xl text-3xl leading-tight font-bold sm:text-4xl">
                        Ready to send{' '}
                        <span className="text-brand-300">
                            your first invoice?
                        </span>
                    </h2>
                    <Link
                        href={register()}
                        className="group inline-flex w-fit items-center gap-2 rounded-lg bg-white px-8 py-4 text-sm font-semibold tracking-wider text-brand-900 uppercase shadow-lg shadow-brand-950/40 transition-all duration-300 hover:-translate-y-0.5 hover:bg-brand-50 hover:shadow-xl active:translate-y-0"
                    >
                        Start free
                        <ArrowRight className="h-4 w-4 transition-transform duration-200 group-hover:translate-x-1" />
                    </Link>
                </div>

                <div className="flex flex-col gap-10 pt-16 md:flex-row md:justify-between">
                    <AppLogo className="self-start brightness-0 invert" />

                    <div className="flex flex-wrap gap-x-16 gap-y-10">
                        <nav
                            className="flex flex-col gap-3 text-sm"
                            aria-label="Footer"
                        >
                            {links.map((link) => (
                                <a
                                    key={link.href}
                                    href={link.href}
                                    className="text-white/70 transition-colors hover:text-white"
                                >
                                    {link.label}
                                </a>
                            ))}
                        </nav>
                        <div className="flex flex-col gap-3 text-sm">
                            <Link
                                href={login()}
                                className="text-white/70 transition-colors hover:text-white"
                            >
                                Log in
                            </Link>
                            <Link
                                href={register()}
                                className="text-white/70 transition-colors hover:text-white"
                            >
                                Register
                            </Link>
                        </div>
                        <div className="flex flex-col gap-3 text-sm">
                            <span className="font-semibold text-white">
                                Contact
                            </span>
                            <Link
                                href="/subscription/enterprise"
                                className="text-white/70 transition-colors hover:text-white"
                            >
                                Enterprise inquiries
                            </Link>
                        </div>
                    </div>
                </div>

                <div className="mt-16 flex flex-col gap-4 border-t border-white/10 pt-6 text-xs text-white/50 sm:flex-row sm:items-center sm:justify-between">
                    <span>
                        &copy; {new Date().getFullYear()} Nilo. All rights
                        reserved.
                    </span>
                    <div className="flex items-center gap-6">
                        <nav
                            className="flex items-center gap-4"
                            aria-label="Legal"
                        >
                            <a
                                href="/terms"
                                className="transition-colors hover:text-white"
                            >
                                Terms
                            </a>
                            <a
                                href="/privacy"
                                className="transition-colors hover:text-white"
                            >
                                Privacy
                            </a>
                        </nav>
                        <span>Crafted by Resonantt</span>
                    </div>
                </div>
            </motion.div>
        </footer>
    );
}
