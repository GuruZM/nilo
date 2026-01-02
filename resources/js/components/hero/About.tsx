import { motion } from 'framer-motion';
import React from 'react';

const About: React.FC = () => (
    <section
        id="about"
        className="relative bg-white py-24 transition-colors duration-300 dark:bg-slate-900"
    >
        <div className="relative z-10 mx-auto max-w-5xl px-6 text-center">
            <motion.h2
                className="mb-6 text-4xl font-bold text-[#00417d] md:text-5xl"
                initial={{ opacity: 0, y: 40 }}
                whileInView={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.7 }}
                viewport={{ once: true }}
            >
                About Nilo
            </motion.h2>
            <motion.p
                className="mb-10 text-lg text-slate-600 md:text-xl dark:text-slate-300"
                initial={{ opacity: 0, y: 40 }}
                whileInView={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.9, delay: 0.2 }}
                viewport={{ once: true }}
            >
                Nilo is your all-in-one invoicing platform, designed to help
                freelancers and businesses create, send, and manage invoices
                with ease. Enjoy a seamless experience with real-time tracking,
                beautiful templates, and smart automation.
            </motion.p>
            <motion.div
                className="mt-8 flex flex-wrap justify-center gap-8"
                initial="hidden"
                whileInView="visible"
                viewport={{ once: true }}
                variants={{
                    hidden: {},
                    visible: { transition: { staggerChildren: 0.2 } },
                }}
            >
                {[
                    {
                        title: 'Fast & Easy',
                        desc: 'Create invoices in seconds with our intuitive interface.',
                    },
                    {
                        title: 'Customizable',
                        desc: 'Personalize your invoices to match your brand.',
                    },
                    {
                        title: 'Automated',
                        desc: 'Send reminders and get paid faster with automation.',
                    },
                ].map((item, i) => (
                    <motion.div
                        key={item.title}
                        className="w-72 rounded-2xl border border-slate-100 bg-slate-50 p-6 shadow-lg dark:border-slate-700 dark:bg-slate-800"
                        initial={{ opacity: 0, y: 40 }}
                        whileInView={{ opacity: 1, y: 0 }}
                        transition={{ duration: 0.7, delay: 0.2 + i * 0.1 }}
                    >
                        <h3 className="mb-2 text-xl font-semibold text-[#00417d]">
                            {item.title}
                        </h3>
                        <p className="text-slate-600 dark:text-slate-300">
                            {item.desc}
                        </p>
                    </motion.div>
                ))}
            </motion.div>
        </div>
    </section>
);

export default About;
