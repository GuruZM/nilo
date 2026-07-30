import About from '@/components/hero/About';
import Footer from '@/components/hero/Footer';
import Hero from '@/components/hero/Hero';
import HowItWorks from '@/components/hero/HowItWorks';
import Nav from '@/components/hero/Nav';
import Pricing from '@/components/hero/Pricing';
import Support from '@/components/hero/Support';
import { type Plan } from '@/types';
import { Head } from '@inertiajs/react';

export default function Welcome({ plans }: { plans: Plan[] }) {
    return (
        <>
            <Head title="Invoicing, quotations, and payments in one place" />
            <Nav />
            <Hero />
            <About />
            <HowItWorks />
            <Pricing plans={plans} />
            <Support />
            <Footer />
        </>
    );
}
