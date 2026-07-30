import {
    AnimatePresence,
    motion,
    useInView,
    useReducedMotion,
} from 'framer-motion';
import { FileClock, PencilLine, Send } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

const steps = [
    {
        icon: FileClock,
        title: 'Open what you already sent',
        desc: 'The last version is still on file, attached to the client, exactly as it left you.',
    },
    {
        icon: PencilLine,
        title: 'Change only what changed',
        desc: 'Adjust a line, apply a percentage across the document, or move the dates. Everything else stays put.',
    },
    {
        icon: Send,
        title: 'Send it out again',
        desc: 'It goes out on your template under a new reference, and the original stays untouched on file.',
    },
];

const lines = [
    { name: 'Discovery workshop', from: 46, to: 58 },
    { name: 'Implementation', from: 72, to: 88 },
    { name: 'Support retainer', from: 38, to: 49 },
];

const STEP_DURATION = 4500;

const statusByStep = ['On file since March', 'Repriced, not rebuilt', 'Issued'];

function RecordCard({ step }: { step: number }) {
    const reducedMotion = useReducedMotion();
    const revised = step >= 1;
    const issued = step === 2;

    return (
        <div className="rounded-2xl border border-border bg-card p-6 shadow-2xl shadow-brand-950/15 sm:p-8">
            <motion.span
                aria-hidden
                className={`mb-6 block h-1.5 rounded-full transition-colors duration-500 ${
                    issued ? 'bg-orange-500' : 'bg-border'
                }`}
                animate={{ width: issued ? '100%' : '30%' }}
                transition={{
                    duration: reducedMotion ? 0 : 0.5,
                    ease: 'easeOut',
                }}
            />

            <div className="flex items-start justify-between gap-4">
                <div className="flex items-center gap-3">
                    <span
                        className={`flex h-9 w-9 items-center justify-center rounded-lg text-sm font-bold transition-colors duration-500 ${
                            issued
                                ? 'bg-brand text-white'
                                : 'bg-muted text-muted-foreground'
                        }`}
                    >
                        A
                    </span>
                    <div>
                        <p className="text-sm font-semibold text-foreground">
                            Halcyon Foods
                        </p>
                        <p className="text-xs text-muted-foreground">
                            Quotation for cold storage fit-out
                        </p>
                    </div>
                </div>

                <div className="relative h-7 min-w-[9.5rem]">
                    <AnimatePresence mode="popLayout" initial={false}>
                        <motion.span
                            key={step}
                            initial={{ opacity: 0, y: reducedMotion ? 0 : 8 }}
                            animate={{ opacity: 1, y: 0 }}
                            exit={{ opacity: 0, y: reducedMotion ? 0 : -8 }}
                            transition={{ duration: 0.3, ease: 'easeOut' }}
                            className={`absolute inset-x-0 top-0 rounded-md px-2.5 py-1 text-center text-xs font-semibold ${
                                issued
                                    ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                    : revised
                                      ? 'bg-orange-500/15 text-orange-600 dark:text-orange-400'
                                      : 'bg-muted text-muted-foreground'
                            }`}
                        >
                            {statusByStep[step]}
                        </motion.span>
                    </AnimatePresence>
                </div>
            </div>

            <div className="mt-7 flex flex-col gap-5 border-t border-border pt-5">
                {lines.map((line, i) => (
                    <div key={line.name} className="flex flex-col gap-2">
                        <span className="text-sm text-foreground">
                            {line.name}
                        </span>
                        <span className="block h-2 w-full overflow-hidden rounded-full bg-muted">
                            <motion.span
                                className={`block h-full rounded-full transition-colors duration-500 ${
                                    revised
                                        ? 'bg-orange-500'
                                        : 'bg-brand/60 dark:bg-brand-400/60'
                                }`}
                                animate={{
                                    width: `${revised ? line.to : line.from}%`,
                                }}
                                transition={{
                                    duration: reducedMotion ? 0 : 0.55,
                                    ease: 'easeOut',
                                    delay: reducedMotion ? 0 : i * 0.07,
                                }}
                            />
                        </span>
                    </div>
                ))}
            </div>

            <div className="mt-7 flex flex-wrap items-center gap-2 border-t border-border pt-5">
                <span className="rounded-md bg-muted px-2.5 py-1 font-mono text-xs text-muted-foreground">
                    v1 · kept
                </span>
                <AnimatePresence initial={false}>
                    {issued && (
                        <motion.span
                            initial={{
                                opacity: 0,
                                x: reducedMotion ? 0 : -8,
                            }}
                            animate={{ opacity: 1, x: 0 }}
                            exit={{ opacity: 0 }}
                            transition={{ duration: 0.35, ease: 'easeOut' }}
                            className="rounded-md bg-brand px-2.5 py-1 font-mono text-xs text-white dark:bg-brand-400 dark:text-brand-950"
                        >
                            v2 · sent today
                        </motion.span>
                    )}
                </AnimatePresence>
            </div>
        </div>
    );
}

export default function HowItWorks() {
    const reducedMotion = useReducedMotion();
    const sectionRef = useRef<HTMLElement>(null);
    const inView = useInView(sectionRef, { margin: '-120px' });
    const [step, setStep] = useState(0);
    const [paused, setPaused] = useState(false);

    useEffect(() => {
        if (!inView || paused || reducedMotion) {
            return;
        }

        const timer = window.setInterval(
            () => setStep((current) => (current + 1) % steps.length),
            STEP_DURATION,
        );

        return () => window.clearInterval(timer);
    }, [inView, paused, reducedMotion]);

    return (
        <section
            id="features"
            ref={sectionRef}
            className="relative overflow-hidden bg-background py-28"
        >
            <div className="mx-auto grid max-w-7xl items-center gap-16 px-6 lg:grid-cols-12">
                <motion.div
                    className="lg:col-span-5"
                    initial={{ opacity: 0, y: reducedMotion ? 0 : 24 }}
                    whileInView={{ opacity: 1, y: 0 }}
                    viewport={{ once: true, margin: '-80px' }}
                    transition={{ duration: 0.5, ease: 'easeOut' }}
                    onMouseEnter={() => setPaused(true)}
                    onMouseLeave={() => setPaused(false)}
                    onFocusCapture={() => setPaused(true)}
                    onBlurCapture={() => setPaused(false)}
                >
                    <h2 className="text-3xl font-bold text-balance text-foreground sm:text-4xl">
                        Nothing starts from a blank page
                    </h2>
                    <p className="mt-5 text-lg text-muted-foreground">
                        A client comes back for the same work at today&rsquo;s
                        rates. What you sent them the first time is still on
                        file, so you edit rather than rebuild.
                    </p>

                    <ol className="mt-10 flex flex-col gap-2">
                        {steps.map((item, i) => {
                            const active = i === step;

                            return (
                                <li key={item.title}>
                                    <button
                                        type="button"
                                        aria-current={
                                            active ? 'step' : undefined
                                        }
                                        onClick={() => setStep(i)}
                                        className={`flex w-full cursor-pointer gap-4 rounded-xl border p-4 text-left transition-colors duration-300 focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none ${
                                            active
                                                ? 'border-brand/40 bg-brand-50/70 dark:bg-brand-950/40'
                                                : 'border-transparent hover:border-border hover:bg-muted/50'
                                        }`}
                                    >
                                        <span
                                            className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-lg transition-colors duration-300 ${
                                                active
                                                    ? 'bg-brand text-white dark:bg-brand-400 dark:text-brand-950'
                                                    : 'bg-muted text-muted-foreground'
                                            }`}
                                        >
                                            <item.icon className="h-5 w-5" />
                                        </span>
                                        <span className="flex-1">
                                            <span className="block font-semibold text-foreground">
                                                {item.title}
                                            </span>
                                            <span className="mt-1 block text-sm text-muted-foreground">
                                                {item.desc}
                                            </span>
                                            <span
                                                aria-hidden
                                                className="mt-3 block h-0.5 w-full overflow-hidden rounded-full bg-border"
                                            >
                                                {active && (
                                                    <motion.span
                                                        key={`${step}-${paused}`}
                                                        className="block h-full rounded-full bg-brand dark:bg-brand-400"
                                                        initial={{
                                                            width: reducedMotion
                                                                ? '100%'
                                                                : 0,
                                                        }}
                                                        animate={{
                                                            width: '100%',
                                                        }}
                                                        transition={{
                                                            duration:
                                                                reducedMotion ||
                                                                paused
                                                                    ? 0
                                                                    : STEP_DURATION /
                                                                      1000,
                                                            ease: 'linear',
                                                        }}
                                                    />
                                                )}
                                            </span>
                                        </span>
                                    </button>
                                </li>
                            );
                        })}
                    </ol>
                </motion.div>

                <motion.div
                    className="lg:col-span-7"
                    initial={{ opacity: 0, y: reducedMotion ? 0 : 32 }}
                    whileInView={{ opacity: 1, y: 0 }}
                    viewport={{ once: true, margin: '-80px' }}
                    transition={{ duration: 0.6, ease: 'easeOut', delay: 0.1 }}
                >
                    <div className="relative mx-auto max-w-lg">
                        <div
                            aria-hidden
                            className="absolute -inset-4 -z-10 rounded-3xl bg-gradient-to-br from-orange-500/10 to-brand-500/10 blur-2xl"
                        />
                        <RecordCard step={step} />
                    </div>
                </motion.div>
            </div>
        </section>
    );
}
