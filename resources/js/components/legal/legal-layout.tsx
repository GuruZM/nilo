import Footer from '@/components/hero/Footer';
import Nav from '@/components/hero/Nav';
import { Head } from '@inertiajs/react';
import { type ReactNode } from 'react';

interface LegalLayoutProps {
    title: string;
    lastUpdated: string;
    intro?: string;
    children: ReactNode;
}

export default function LegalLayout({
    title,
    lastUpdated,
    intro,
    children,
}: LegalLayoutProps) {
    return (
        <>
            <Head title={title} />
            <Nav />

            <header className="relative overflow-hidden bg-gradient-to-b from-brand-900 to-brand-950 text-white">
                <div
                    aria-hidden
                    className="absolute -top-24 right-[-6rem] h-80 w-80 rounded-full bg-brand-500/15 blur-3xl"
                />
                <div className="relative mx-auto max-w-3xl px-6 pt-32 pb-16 sm:pt-40">
                    <p className="text-sm font-semibold tracking-wider text-brand-300 uppercase">
                        Legal
                    </p>
                    <h1 className="mt-3 text-4xl font-bold sm:text-5xl">
                        {title}
                    </h1>
                    <p className="mt-4 text-sm text-white/60">
                        Last updated: {lastUpdated}
                    </p>
                    {intro && (
                        <p className="mt-6 max-w-2xl text-base leading-relaxed text-white/80">
                            {intro}
                        </p>
                    )}
                </div>
            </header>

            <main className="bg-background">
                <div className="mx-auto max-w-3xl px-6 py-16 sm:py-20">
                    <div className="flex flex-col gap-10 text-[15px] leading-relaxed text-muted-foreground">
                        {children}
                    </div>
                </div>
            </main>

            <Footer />
        </>
    );
}

interface SectionProps {
    heading: string;
    children: ReactNode;
}

export function Section({ heading, children }: SectionProps) {
    return (
        <section className="flex flex-col gap-3">
            <h2 className="text-xl font-semibold text-foreground">{heading}</h2>
            {children}
        </section>
    );
}

export function List({ items }: { items: ReactNode[] }) {
    return (
        <ul className="flex list-disc flex-col gap-2 pl-5 marker:text-brand">
            {items.map((item, index) => (
                <li key={index}>{item}</li>
            ))}
        </ul>
    );
}
