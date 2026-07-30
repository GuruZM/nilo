import { login, register } from '@/routes';
import { Link } from '@inertiajs/react';
import {
    motion,
    useReducedMotion,
    useScroll,
    useTransform,
} from 'framer-motion';
import { ArrowRight } from 'lucide-react';
import { CheckedDocument, PaperSheet } from './PaperGlyphs';

export default function Hero() {
    const reducedMotion = useReducedMotion();
    const { scrollY } = useScroll();
    const shapeY = useTransform(scrollY, [0, 600], [0, reducedMotion ? 0 : 90]);
    const contentY = useTransform(
        scrollY,
        [0, 600],
        [0, reducedMotion ? 0 : -40],
    );

    const container = {
        hidden: {},
        visible: { transition: { staggerChildren: reducedMotion ? 0 : 0.08 } },
    };
    const item = {
        hidden: { opacity: 0, y: reducedMotion ? 0 : 28 },
        visible: {
            opacity: 1,
            y: 0,
            transition: { duration: 0.5, ease: 'easeOut' as const },
        },
    };
    const float = (duration: number, distance: number) =>
        reducedMotion
            ? {}
            : {
                  animate: { y: [0, -distance, 0] },
                  transition: {
                      duration,
                      repeat: Infinity,
                      ease: 'easeInOut' as const,
                  },
              };

    return (
        <section className="relative overflow-hidden bg-gradient-to-br from-brand-700 via-brand-800 to-brand-950">
            <motion.div
                aria-hidden
                style={{ y: shapeY }}
                className="absolute inset-0"
            >
                <motion.div
                    {...float(10, 24)}
                    className="absolute -top-32 -right-24 h-[28rem] w-[28rem] rounded-full bg-gradient-to-br from-brand-400/30 to-brand-600/10 blur-3xl"
                />
                <motion.div
                    {...float(14, 32)}
                    className="absolute -bottom-40 left-1/4 h-[24rem] w-[24rem] rounded-full bg-gradient-to-tr from-brand-300/20 to-transparent blur-3xl"
                />
                <motion.div
                    {...float(11, 22)}
                    className="absolute top-[14%] right-[6%] hidden rotate-[24deg] opacity-30 lg:block"
                >
                    <PaperSheet className="h-24 w-20" />
                </motion.div>
                <motion.div
                    {...float(14, 18)}
                    className="absolute top-[30%] right-[22%] hidden -rotate-12 opacity-45 lg:block"
                >
                    <PaperSheet className="h-28 w-24" />
                </motion.div>
                <motion.div
                    {...float(17, 14)}
                    className="absolute top-[52%] right-[30%] hidden rotate-[8deg] opacity-60 lg:block"
                >
                    <PaperSheet className="h-24 w-20" />
                </motion.div>
                <motion.div
                    {...float(13, 16)}
                    className="absolute top-[46%] right-[8%] hidden drop-shadow-[0_20px_40px_rgba(0,18,38,0.5)] lg:block"
                >
                    <CheckedDocument className="h-56 w-44" />
                </motion.div>
            </motion.div>

            <motion.div
                className="relative mx-auto flex min-h-svh w-full max-w-7xl flex-col justify-center px-6 pt-24 pb-16"
                style={{ y: contentY }}
                variants={container}
                initial="hidden"
                animate="visible"
            >
                <motion.h1
                    variants={item}
                    className="max-w-3xl text-5xl leading-tight font-bold text-white sm:text-6xl lg:text-7xl"
                >
                    The paperwork lives{' '}
                    <span className="animate-shimmer bg-[linear-gradient(110deg,var(--color-orange-500),45%,var(--color-orange-200),55%,var(--color-orange-500))] bg-[length:200%_100%] bg-clip-text text-transparent">
                        here.
                    </span>
                </motion.h1>

                <motion.p
                    variants={item}
                    className="mt-6 max-w-xl text-lg leading-relaxed text-brand-100"
                >
                    Nilo is the business repository for SMEs: invoices,
                    quotations, clients, and payments, with no system to manage.
                    When your business needs more, you ask and we build it.
                </motion.p>

                <motion.div
                    variants={item}
                    className="mt-10 flex flex-wrap items-center gap-4"
                >
                    <Link
                        href={register()}
                        className="group inline-flex items-center gap-2 rounded-lg bg-white px-8 py-4 text-base font-semibold text-brand-900 shadow-lg shadow-brand-950/40 transition-all duration-300 hover:-translate-y-0.5 hover:bg-brand-50 hover:shadow-xl hover:shadow-brand-950/50 active:translate-y-0"
                    >
                        Start free
                        <ArrowRight className="h-4 w-4 transition-transform duration-300 group-hover:translate-x-1" />
                    </Link>
                    <Link
                        href={login()}
                        className="inline-flex items-center rounded-lg border border-white/40 px-8 py-4 text-base font-semibold text-white backdrop-blur-sm transition-all duration-300 hover:-translate-y-0.5 hover:border-white hover:bg-white/10 active:translate-y-0"
                    >
                        Log in
                    </Link>
                </motion.div>
            </motion.div>
        </section>
    );
}
