import AppLogoIcon from '@/components/app-logo-icon';
import ResonanttLogo from '@/components/resonantt-logo';
import { home } from '@/routes';
import { Link } from '@inertiajs/react';
import { motion, useReducedMotion } from 'framer-motion';
import { type PropsWithChildren } from 'react';

interface AuthLayoutProps {
    title?: string;
    description?: string;
    tagline?: string;
}

export default function AuthSplitLayout({
    children,
    title,
    description,
    tagline = 'The busywork handled, your focus where it counts.',
}: PropsWithChildren<AuthLayoutProps>) {
    const reducedMotion = useReducedMotion();

    const hour = new Date().getHours();
    const period = hour < 12 ? 'morning' : hour < 18 ? 'afternoon' : 'evening';

    const fadeUp = (delay: number) => ({
        initial: { opacity: 0, y: reducedMotion ? 0 : 16 },
        animate: { opacity: 1, y: 0 },
        transition: { duration: 0.5, delay, ease: 'easeOut' as const },
    });

    return (
        <div className="grid min-h-dvh lg:grid-cols-[1.05fr_1fr]">
            {/* Brand panel */}
            <div className="relative hidden flex-col justify-start overflow-hidden rounded-r-[2.5rem] bg-gradient-to-br from-brand-700 via-brand-900 to-brand-950 py-10 pr-10 pl-[10vw] text-white shadow-2xl shadow-brand-950/40 lg:flex xl:py-14 xl:pr-14">
                <Link
                    href={home()}
                    aria-label="Nilo home"
                    className="relative z-10 w-fit"
                >
                    <AppLogoIcon className="h-5 w-auto brightness-0 invert" />
                </Link>

                <div className="relative z-10 flex flex-1 items-center justify-start">
                    <div className="flex items-stretch gap-6">
                        <div className="shrink-0 self-center">
                            <h2 className="text-[clamp(2.25rem,4vw,3.5rem)] leading-[1.05] font-bold tracking-tight">
                                Good{' '}
                                <span className="animate-shimmer bg-[linear-gradient(110deg,#ffffff,45%,rgba(255,255,255,0.55),55%,#ffffff)] bg-[length:200%_100%] bg-clip-text text-transparent">
                                    {period}
                                </span>
                                ,
                            </h2>
                            <span className="mt-3 block h-1 w-12 rounded-full bg-orange-400" />
                        </div>
                        <div className="w-px shrink-0 bg-white/15" />
                        <p className="max-w-[16rem] self-center text-sm leading-relaxed text-brand-100/80">
                            {tagline}
                        </p>
                    </div>
                </div>

                <div className="relative z-10 flex items-center justify-between text-xs text-white/60">
                    <span className="inline-flex items-center gap-2 text-white/80">
                        <ResonanttLogo className="h-5 w-auto" />
                        By Resonantt
                    </span>
                    <nav className="flex items-center gap-4" aria-label="Legal">
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
                        <a
                            href="/#pricing"
                            className="transition-colors hover:text-white"
                        >
                            Plans
                        </a>
                        <a
                            href="/#contact"
                            className="transition-colors hover:text-white"
                        >
                            Support
                        </a>
                    </nav>
                </div>
            </div>

            {/* Form panel */}
            <div className="relative flex flex-col overflow-hidden bg-background">
                {/* Abstract corner shapes */}
                <div
                    aria-hidden
                    className="pointer-events-none absolute top-0 right-0 h-28 w-28 rounded-bl-[3.5rem] bg-orange-400/60"
                />
                <div
                    aria-hidden
                    className="pointer-events-none absolute right-0 bottom-0 h-24 w-24 rounded-tl-full bg-orange-400/60"
                />

                <div className="relative z-10 flex items-center bg-gradient-to-r from-brand-800 to-brand-900 p-6 lg:hidden">
                    <Link href={home()} aria-label="Nilo home">
                        <AppLogoIcon className="h-5 w-auto brightness-0 invert" />
                    </Link>
                </div>

                <div className="relative z-10 flex flex-1 items-center justify-center px-8 py-12 sm:px-12 lg:px-16">
                    <motion.div
                        {...fadeUp(0)}
                        className="flex w-full max-w-md flex-col gap-8"
                    >
                        <div className="flex flex-col gap-2">
                            <h1 className="text-3xl font-bold tracking-tight text-foreground">
                                {title}
                            </h1>
                            {description && (
                                <p className="text-[15px] text-muted-foreground">
                                    {description}
                                </p>
                            )}
                        </div>
                        {children}
                    </motion.div>
                </div>
            </div>
        </div>
    );
}
