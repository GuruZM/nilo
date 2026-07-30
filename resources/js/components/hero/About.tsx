import { motion, useReducedMotion } from 'framer-motion';
import { ArrowRight, Check, Search, X } from 'lucide-react';
import { useEffect, useState } from 'react';

const records = [
    { ref: 'QTE-0088', client: 'Halcyon Foods', status: 'Accepted' },
    { ref: 'INV-0142', client: 'Halcyon Foods', status: 'Paid' },
    { ref: 'QTE-0087', client: 'Northgate Ltd', status: 'Sent' },
    { ref: 'INV-0141', client: 'Beacon Health', status: 'Overdue' },
];

const statusStyles: Record<string, string> = {
    Paid: 'text-emerald-600 dark:text-emerald-400',
    Accepted: 'text-emerald-600 dark:text-emerald-400',
    Sent: 'text-brand dark:text-brand-300',
    Overdue: 'text-orange-600 dark:text-orange-400',
};

const scatteredPlaces = [
    'Someone’s laptop',
    'A chat thread',
    'An inbox that left',
];

const currencies = ['USD', 'EUR', 'GBP', 'ZMW', 'JPY', 'INR', 'AED'];

const templates = [
    { name: 'Classic', bar: 'bg-brand-600' },
    { name: 'Compact', bar: 'bg-orange-500' },
    { name: 'Letterhead', bar: 'bg-emerald-600' },
];

function Block({
    title,
    desc,
    children,
    delay = 0,
    className = '',
}: {
    title: string;
    desc: string;
    children?: React.ReactNode;
    delay?: number;
    className?: string;
}) {
    const reducedMotion = useReducedMotion();

    return (
        <motion.div
            initial={{ opacity: 0, y: reducedMotion ? 0 : 24 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true, margin: '-60px' }}
            transition={{
                duration: 0.5,
                ease: 'easeOut',
                delay: reducedMotion ? 0 : delay,
            }}
            className={`group flex flex-col ${className}`}
        >
            <h3 className="text-lg font-bold text-balance text-foreground">
                {title}
            </h3>
            <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
                {desc}
            </p>
            {children}
        </motion.div>
    );
}

function RecordTrail() {
    const reducedMotion = useReducedMotion();

    return (
        <div className="mt-8">
            <div className="grid grid-cols-[auto_1fr_auto] gap-x-6 pb-3 text-[11px] font-semibold tracking-[0.15em] text-muted-foreground uppercase">
                <span>Reference</span>
                <span>Client</span>
                <span className="text-right">Where it stands</span>
            </div>
            <div className="divide-y divide-border border-t border-border">
                {records.map((record, i) => (
                    <motion.div
                        key={record.ref}
                        initial={{ opacity: 0, x: reducedMotion ? 0 : -12 }}
                        whileInView={{ opacity: 1, x: 0 }}
                        viewport={{ once: true, margin: '-60px' }}
                        transition={{
                            duration: 0.4,
                            ease: 'easeOut',
                            delay: reducedMotion ? 0 : 0.15 + i * 0.09,
                        }}
                        className="grid grid-cols-[auto_1fr_auto] items-center gap-x-6 py-4"
                    >
                        <span className="font-mono text-xs text-muted-foreground">
                            {record.ref}
                        </span>
                        <span className="truncate text-sm font-medium text-foreground">
                            {record.client}
                        </span>
                        <span
                            className={`text-right text-sm font-semibold ${statusStyles[record.status]}`}
                        >
                            {record.status}
                        </span>
                    </motion.div>
                ))}
            </div>
        </div>
    );
}

function FindItLater() {
    const reducedMotion = useReducedMotion();

    return (
        <div className="mt-8">
            <div className="flex items-center gap-3 border-b border-border pb-3">
                <Search className="h-4 w-4 shrink-0 text-muted-foreground" />
                <motion.span
                    initial={{ opacity: 0 }}
                    whileInView={{ opacity: 1 }}
                    viewport={{ once: true, margin: '-60px' }}
                    transition={{
                        duration: 0.4,
                        delay: reducedMotion ? 0 : 0.2,
                    }}
                    className="text-base text-foreground"
                >
                    halcyon
                    {!reducedMotion && (
                        <motion.span
                            aria-hidden
                            className="ml-0.5 inline-block h-4 w-px translate-y-0.5 bg-brand dark:bg-brand-300"
                            animate={{ opacity: [1, 0, 1] }}
                            transition={{ duration: 1.1, repeat: Infinity }}
                        />
                    )}
                </motion.span>
            </div>
            <motion.p
                initial={{ opacity: 0, y: reducedMotion ? 0 : 8 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true, margin: '-60px' }}
                transition={{ duration: 0.4, delay: reducedMotion ? 0 : 0.5 }}
                className="mt-4 flex items-center justify-between border-l-2 border-brand py-1 pl-4 text-sm dark:border-brand-300"
            >
                <span className="font-medium text-foreground">
                    Halcyon Foods
                </span>
                <span className="font-mono text-xs text-muted-foreground">
                    QTE-0088
                </span>
            </motion.p>
        </div>
    );
}

function ScatteredVersusKept() {
    const reducedMotion = useReducedMotion();

    return (
        <div className="mt-6 flex flex-col gap-2">
            {scatteredPlaces.map((place, i) => (
                <motion.p
                    key={place}
                    initial={{ opacity: 0, x: reducedMotion ? 0 : -8 }}
                    whileInView={{ opacity: 1, x: 0 }}
                    viewport={{ once: true, margin: '-60px' }}
                    transition={{
                        duration: 0.35,
                        ease: 'easeOut',
                        delay: reducedMotion ? 0 : i * 0.1,
                    }}
                    className="flex items-center gap-2 text-sm text-muted-foreground line-through decoration-orange-500/60"
                >
                    <X className="h-3.5 w-3.5 shrink-0 text-orange-500" />
                    {place}
                </motion.p>
            ))}
            <motion.p
                initial={{ opacity: 0, x: reducedMotion ? 0 : -8 }}
                whileInView={{ opacity: 1, x: 0 }}
                viewport={{ once: true, margin: '-60px' }}
                transition={{
                    duration: 0.35,
                    ease: 'easeOut',
                    delay: reducedMotion ? 0 : 0.35,
                }}
                className="flex items-center gap-2 pt-1 text-sm font-semibold text-foreground"
            >
                <Check className="h-3.5 w-3.5 shrink-0 text-brand dark:text-brand-300" />
                Kept in Nilo
            </motion.p>
        </div>
    );
}

function CurrencyRail() {
    const reducedMotion = useReducedMotion();
    const [index, setIndex] = useState(0);

    useEffect(() => {
        if (reducedMotion) {
            return;
        }

        const timer = window.setInterval(
            () => setIndex((current) => (current + 1) % currencies.length),
            1600,
        );

        return () => window.clearInterval(timer);
    }, [reducedMotion]);

    return (
        <div className="mt-6 flex flex-wrap gap-x-4 gap-y-2">
            {currencies.map((code, i) => (
                <span
                    key={code}
                    className={`font-mono text-sm font-semibold transition-colors duration-500 ${
                        i === index
                            ? 'text-brand dark:text-brand-300'
                            : 'text-muted-foreground/50'
                    }`}
                >
                    {code}
                </span>
            ))}
        </div>
    );
}

function TemplateSwatches() {
    return (
        <div className="mt-6 flex gap-5">
            {templates.map((template, i) => (
                <div
                    key={template.name}
                    className={`flex-1 transition-opacity duration-300 ${
                        i === 1 ? 'opacity-100' : 'opacity-45'
                    }`}
                >
                    <span
                        className={`block h-1.5 rounded-full ${template.bar}`}
                    />
                    <span className="mt-2.5 block h-1 rounded-full bg-muted-foreground/25" />
                    <span className="mt-1.5 block h-1 w-2/3 rounded-full bg-muted-foreground/20" />
                    <span className="mt-3 block text-[11px] font-medium text-muted-foreground">
                        {template.name}
                    </span>
                </div>
            ))}
        </div>
    );
}

function RequestFlow() {
    return (
        <div className="mt-6 flex items-center gap-3 text-sm">
            <span className="font-medium text-muted-foreground">You ask</span>
            <ArrowRight className="h-4 w-4 shrink-0 text-muted-foreground transition-transform duration-300 group-hover:translate-x-1" />
            <span className="inline-flex items-center gap-1.5 font-semibold text-brand dark:text-brand-300">
                <Check className="h-3.5 w-3.5" />
                We build
            </span>
        </div>
    );
}

export default function About() {
    const reducedMotion = useReducedMotion();
    const reveal = {
        initial: { opacity: 0, y: reducedMotion ? 0 : 28 },
        whileInView: { opacity: 1, y: 0 },
        viewport: { once: true, margin: '-80px' },
    };

    return (
        <section
            id="about"
            className="relative overflow-hidden bg-background py-24"
        >
            <div className="relative mx-auto max-w-7xl px-6">
                <motion.h2
                    {...reveal}
                    transition={{ duration: 0.5, ease: 'easeOut' }}
                    className="max-w-2xl text-3xl font-bold text-balance text-foreground sm:text-4xl"
                >
                    Where the business keeps what it has done
                </motion.h2>
                <motion.p
                    {...reveal}
                    transition={{ duration: 0.5, ease: 'easeOut', delay: 0.12 }}
                    className="mt-5 max-w-xl text-lg text-muted-foreground"
                >
                    What you sent, who you sent it to, and where it landed,
                    filed together and kept. Nothing to install, nothing to keep
                    running, and nothing that leaves when a person does.
                </motion.p>

                <div className="mt-16 grid gap-12 lg:grid-cols-12 lg:gap-16">
                    <Block
                        className="lg:col-span-7"
                        title="The whole trail, not loose documents"
                        desc="Each document stays attached to the client it belongs to and to everything that came before it, so the history reads in one line."
                    >
                        <RecordTrail />
                    </Block>

                    <Block
                        className="lg:col-span-5"
                        delay={0.08}
                        title="Findable years later"
                        desc="Search a client, a reference, or a date and the file comes back whole."
                    >
                        <FindItLater />
                    </Block>
                </div>

                <div className="mt-20 grid gap-x-12 gap-y-14 sm:grid-cols-2 lg:grid-cols-4">
                    <Block
                        className="border-t border-border pt-8"
                        title="Nothing walks out the door"
                        desc="Records belong to the business, not to whoever happened to make them."
                    >
                        <ScatteredVersusKept />
                    </Block>

                    <Block
                        className="border-t border-border pt-8"
                        delay={0.06}
                        title="Kept in the currency it was agreed in"
                        desc="Records hold the currency they were issued in, so the history stays true."
                    >
                        <CurrencyRail />
                    </Block>

                    <Block
                        className="border-t border-border pt-8"
                        delay={0.12}
                        title="Your branding, not ours"
                        desc="Pick a template or bring your own letterhead. Everything leaves looking like you."
                    >
                        <TemplateSwatches />
                    </Block>

                    <Block
                        className="border-t border-border pt-8"
                        delay={0.18}
                        title="Ask, and we build it"
                        desc="Missing something the business needs? Send the request from inside your account."
                    >
                        <RequestFlow />
                    </Block>
                </div>
            </div>
        </section>
    );
}
