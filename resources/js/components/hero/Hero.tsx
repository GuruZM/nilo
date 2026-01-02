import Chatbot from '@/components/chatbot/Chatbot';
import { motion } from 'framer-motion';
import { ArrowRight, Bot, Shield, Star, Users } from 'lucide-react';
import React, { useEffect, useState } from 'react';

const words = ['Invoices', 'Quotes', 'Payments', 'Clients', 'Your Business'];

const Hero: React.FC = () => {
    const [currentWord, setCurrentWord] = useState(0);
    const [displayed, setDisplayed] = useState('');
    const [deleting, setDeleting] = useState(false);
    const [loopNum, setLoopNum] = useState(0);
    const [typingSpeed, setTypingSpeed] = useState(120);

    useEffect(() => {
        let timer: NodeJS.Timeout;
        const fullWord = words[currentWord];
        if (!deleting && displayed.length < fullWord.length) {
            timer = setTimeout(() => {
                setDisplayed(fullWord.substring(0, displayed.length + 1));
            }, typingSpeed);
        } else if (deleting && displayed.length > 0) {
            timer = setTimeout(() => {
                setDisplayed(fullWord.substring(0, displayed.length - 1));
            }, 60);
        } else if (!deleting && displayed.length === fullWord.length) {
            timer = setTimeout(() => setDeleting(true), 1200);
        } else if (deleting && displayed.length === 0) {
            setDeleting(false);
            setCurrentWord((prev) => (prev + 1) % words.length);
            setLoopNum(loopNum + 1);
        }
        return () => clearTimeout(timer);
    }, [displayed, deleting, currentWord, loopNum]);

    return (
        <section className="relative flex min-h-screen items-center justify-center overflow-hidden bg-gradient-to-br from-[#00417d1a] via-white to-[#00417d1a] transition-colors duration-300 dark:from-slate-900 dark:via-slate-800 dark:to-[#00417d4d]">
            {/* Animated Background Elements */}
            <div className="absolute inset-0 overflow-hidden">
                <motion.div
                    className="absolute -top-40 -right-40 h-80 w-80 rounded-full bg-[#00417d4d] opacity-60 mix-blend-multiply blur-2xl filter"
                    animate={{ y: [0, 30, 0] }}
                    transition={{ duration: 8, repeat: Infinity }}
                />
                <motion.div
                    className="absolute -bottom-40 -left-40 h-80 w-80 rounded-full bg-blue-400/30 opacity-60 mix-blend-multiply blur-2xl filter"
                    animate={{ y: [0, -30, 0] }}
                    transition={{ duration: 10, repeat: Infinity }}
                />
                <motion.div
                    className="absolute top-40 left-40 h-80 w-80 rounded-full bg-pink-400/30 opacity-60 mix-blend-multiply blur-2xl filter"
                    animate={{ x: [0, 40, 0] }}
                    transition={{ duration: 12, repeat: Infinity }}
                />
            </div>

            <div className="relative z-10 mx-auto max-w-7xl px-6 py-20 text-center">
                <div className="mx-auto max-w-4xl">
                    <motion.h1
                        className="mb-8 flex flex-col items-center text-5xl leading-tight font-bold text-slate-800 md:text-7xl dark:text-white"
                        initial={{ opacity: 0, y: 40 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ duration: 1 }}
                    >
                        <span className="flex items-center justify-center gap-2">
                            Hello from{' '}
                            <span className="text-[#00417d]">Nilo</span>
                        </span>
                        <span className="mt-4 block bg-gradient-to-r from-[#00417d] to-blue-600 bg-clip-text text-3xl font-extrabold text-transparent md:text-5xl">
                            Effortless{' '}
                            <span className="inline-block min-w-[120px] border-b-2 border-[#00417d]">
                                {displayed}
                            </span>
                        </span>
                    </motion.h1>
                    <motion.p
                        className="mb-12 text-xl leading-relaxed text-slate-600 md:text-2xl dark:text-slate-300"
                        initial={{ opacity: 0, y: 40 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ duration: 1, delay: 0.3 }}
                    >
                        The all-in-one platform to manage, automate, and elevate
                        your business finances. Create, send, and track
                        invoices, quotes, and payments with a touch of magic.
                    </motion.p>
                    <motion.p
                        className="mb-4 flex items-center justify-center gap-2 text-lg font-medium text-[#00417d]"
                        initial={{ opacity: 0, y: 20 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ duration: 1, delay: 0.1 }}
                    >
                        <Bot className="h-5 w-5" /> Nilo, your AI invoicing
                        assistant
                    </motion.p>
                    <div className="mb-16 flex flex-col justify-center gap-4 sm:flex-row">
                        <motion.button
                            className="flex transform items-center justify-center space-x-2 rounded-full bg-[#00417d] px-8 py-4 text-lg font-semibold text-white transition-all duration-300 hover:-translate-y-2 hover:bg-blue-800 hover:shadow-xl dark:bg-[#00417d] dark:hover:bg-blue-900"
                            whileHover={{ scale: 1.07 }}
                            whileTap={{ scale: 0.97 }}
                        >
                            <span>Start Free Trial</span>
                            <ArrowRight className="h-5 w-5" />
                        </motion.button>
                        <motion.button
                            className="rounded-full border-2 border-slate-300 px-8 py-4 text-lg font-semibold text-slate-700 transition-all duration-300 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800"
                            whileHover={{ scale: 1.07 }}
                            whileTap={{ scale: 0.97 }}
                        >
                            Watch Demo
                        </motion.button>
                    </div>
                    {/* Floating Cards */}
                    <div className="relative mt-12 flex flex-wrap items-center justify-center gap-8">
                        <motion.div
                            className="flex flex-col items-center rounded-2xl border border-slate-100 bg-white/80 px-8 py-6 shadow-xl dark:border-slate-700 dark:bg-slate-800/80"
                            initial={{ opacity: 0, y: 40 }}
                            animate={{ opacity: 1, y: 0 }}
                            transition={{ duration: 1, delay: 0.5 }}
                            whileHover={{ y: -10, scale: 1.05 }}
                        >
                            <Star className="mb-2 h-6 w-6 text-yellow-500" />
                            <span className="text-lg font-bold">
                                4.9/5 Rating
                            </span>
                            <span className="text-xs text-slate-500 dark:text-slate-400">
                                from 10,000+ users
                            </span>
                        </motion.div>
                        <motion.div
                            className="flex flex-col items-center rounded-2xl border border-slate-100 bg-white/80 px-8 py-6 shadow-xl dark:border-slate-700 dark:bg-slate-800/80"
                            initial={{ opacity: 0, y: 40 }}
                            animate={{ opacity: 1, y: 0 }}
                            transition={{ duration: 1, delay: 0.7 }}
                            whileHover={{ y: -10, scale: 1.05 }}
                        >
                            <Users className="mb-2 h-6 w-6 text-[#00417d]" />
                            <span className="text-lg font-bold">
                                10,000+ Users
                            </span>
                            <span className="text-xs text-slate-500 dark:text-slate-400">
                                growing every day
                            </span>
                        </motion.div>
                        <motion.div
                            className="flex flex-col items-center rounded-2xl border border-slate-100 bg-white/80 px-8 py-6 shadow-xl dark:border-slate-700 dark:bg-slate-800/80"
                            initial={{ opacity: 0, y: 40 }}
                            animate={{ opacity: 1, y: 0 }}
                            transition={{ duration: 1, delay: 0.9 }}
                            whileHover={{ y: -10, scale: 1.05 }}
                        >
                            <Shield className="mb-2 h-6 w-6 text-blue-500" />
                            <span className="text-lg font-bold">
                                Bank-Level Security
                            </span>
                            <span className="text-xs text-slate-500 dark:text-slate-400">
                                your data is safe
                            </span>
                        </motion.div>
                    </div>
                    <Chatbot />
                </div>
            </div>
        </section>
    );
};

export default Hero;
