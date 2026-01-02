import { motion } from 'framer-motion';
import React from 'react';

const plans = [
    {
        name: 'Free',
        price: 0,
        features: ['Unlimited invoices', 'Basic templates', 'Email support'],
        highlight: false,
    },
    {
        name: 'Pro',
        price: 19,
        features: [
            'All Free features',
            'Custom branding',
            'Payment reminders',
            'Priority support',
        ],
        highlight: true,
    },
    {
        name: 'Enterprise',
        price: 49,
        features: [
            'All Pro features',
            'API access',
            'Dedicated support',
            'Advanced analytics',
        ],
        highlight: false,
    },
];

const Pricing: React.FC = () => (
    <section
        id="pricing"
        className="bg-white py-24 transition-colors duration-300 dark:bg-slate-900"
    >
        <div className="mx-auto max-w-6xl px-6 text-center">
            <motion.h2
                className="text-primeblue mb-6 text-4xl font-bold md:text-5xl"
                initial={{ opacity: 0, y: 40 }}
                whileInView={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.7 }}
                viewport={{ once: true }}
            >
                Pricing
            </motion.h2>
            <motion.p
                className="mb-10 text-lg text-slate-600 md:text-xl dark:text-slate-300"
                initial={{ opacity: 0, y: 40 }}
                whileInView={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.9, delay: 0.2 }}
                viewport={{ once: true }}
            >
                Simple, transparent pricing for every business size.
            </motion.p>
            <div className="mt-12 flex flex-col justify-center gap-10 md:flex-row">
                {plans.map((plan, i) => (
                    <motion.div
                        key={plan.name}
                        className={`flex w-80 flex-col items-center rounded-2xl border-2 p-8 shadow-xl transition-all duration-300 ${plan.highlight ? 'border-primeblue bg-primeblue/5 scale-105' : 'border-slate-100 bg-white dark:border-slate-700 dark:bg-slate-800'}`}
                        initial={{ opacity: 0, y: 40 }}
                        whileInView={{ opacity: 1, y: 0 }}
                        transition={{ duration: 0.7, delay: 0.2 + i * 0.1 }}
                        viewport={{ once: true }}
                    >
                        <h3
                            className={`mb-2 text-2xl font-bold ${plan.highlight ? 'text-primeblue' : 'text-slate-700 dark:text-white'}`}
                        >
                            {plan.name}
                        </h3>
                        <div className="mb-4 text-4xl font-extrabold">
                            {plan.price === 0 ? 'Free' : `$${plan.price}/mo`}
                        </div>
                        <ul className="mb-6 space-y-2 text-slate-600 dark:text-slate-300">
                            {plan.features.map((feature) => (
                                <li key={feature}>• {feature}</li>
                            ))}
                        </ul>
                        <button
                            className={`mt-auto rounded-full px-6 py-3 font-semibold transition-all duration-300 ${plan.highlight ? 'bg-primeblue text-white hover:bg-blue-800 dark:hover:bg-blue-900' : 'bg-slate-200 text-slate-700 hover:bg-slate-300 dark:bg-slate-700 dark:text-white dark:hover:bg-slate-600'}`}
                        >
                            {plan.price === 0
                                ? 'Get Started'
                                : 'Start Free Trial'}
                        </button>
                    </motion.div>
                ))}
            </div>
        </div>
    </section>
);

export default Pricing;
