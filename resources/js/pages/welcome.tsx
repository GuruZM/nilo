import About from '@/components/hero/About';
import Footer from '@/components/hero/Footer';
import Hero from '@/components/hero/Hero';
import HowItWorks from '@/components/hero/HowItWorks';
import Nav from '@/components/hero/Nav';
import Pricing from '@/components/hero/Pricing';
import { type SharedData } from '@/types';
import { Head, usePage } from '@inertiajs/react';

export default function Welcome() {
    const { auth } = usePage<SharedData>().props;

    return (
        <>
            <Head title="Welcome">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link
                    href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600"
                    rel="stylesheet"
                />
            </Head>
            <Nav />
            <Hero />
            <About />
            <HowItWorks />
            <Pricing />
            <Footer />
        </>
    );
}
