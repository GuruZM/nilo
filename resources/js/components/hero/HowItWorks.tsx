import { motion } from 'framer-motion';
import React from 'react';

const steps = [
    {
        title: 'Sign Up',
        desc: 'Create your free account in seconds and set up your business profile.',
        icon: '📝',
    },
    {
        title: 'Create Invoice',
        desc: 'Use our beautiful templates to generate invoices quickly and easily.',
        icon: '🧾',
    },
    {
        title: 'Send & Track',
        desc: 'Send invoices to clients and track payments in real time.',
        icon: '📬',
    },
];

const HowItWorks: React.FC = () => (
    <section
        id="features"
        className="bg-gradient-to-b from-white to-blue-50 py-24 transition-colors duration-300 dark:from-slate-900 dark:to-slate-800"
    >
        <div className="mx-auto max-w-5xl px-6 text-center">
            <motion.h2
                className="mb-6 text-4xl font-bold text-[#00417d] md:text-5xl"
                initial={{ opacity: 0, y: 40 }}
                whileInView={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.7 }}
                viewport={{ once: true }}
            >
                How It Works
            </motion.h2>
            <motion.p
                className="mb-10 text-lg text-slate-600 md:text-xl dark:text-slate-300"
                initial={{ opacity: 0, y: 40 }}
                whileInView={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.9, delay: 0.2 }}
                viewport={{ once: true }}
            >
                Get started in just a few steps. Nilo makes invoicing effortless
                for everyone.
            </motion.p>
            <div className="mt-12 flex flex-col justify-center gap-10 md:flex-row">
                {steps.map((step, i) => (
                    <motion.div
                        key={step.title}
                        className="flex w-80 flex-col items-center rounded-2xl border border-slate-100 bg-white p-8 shadow-lg dark:border-slate-700 dark:bg-slate-800"
                        initial={{ opacity: 0, y: 40 }}
                        whileInView={{ opacity: 1, y: 0 }}
                        transition={{ duration: 0.7, delay: 0.2 + i * 0.1 }}
                        viewport={{ once: true }}
                    >
                        <span className="animate-bounce-slow mb-4 text-5xl">
                            {step.icon}
                        </span>
                        <h3 className="mb-2 text-xl font-semibold text-[#00417d]">
                            {step.title}
                        </h3>
                        <p className="text-slate-600 dark:text-slate-300">
                            {step.desc}
                        </p>
                    </motion.div>
                ))}
            </div>
        </div>
    </section>
);

export default HowItWorks;
