import { motion } from 'framer-motion';
import React from 'react';

const Footer: React.FC = () => (
    <footer className="bg-[#00417d] py-12 text-white transition-colors duration-300">
        <motion.div
            className="mx-auto flex max-w-6xl flex-col items-center justify-between gap-6 px-6 md:flex-row"
            initial={{ opacity: 0, y: 40 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.7 }}
            viewport={{ once: true }}
        >
            <div className="text-2xl font-bold tracking-tight">Nilo</div>
            <div className="flex flex-wrap gap-6 text-sm">
                <a href="#about" className="hover:underline">
                    About
                </a>
                <a href="#features" className="hover:underline">
                    How It Works
                </a>
                <a href="#pricing" className="hover:underline">
                    Pricing
                </a>
                <a href="#" className="hover:underline">
                    Contact
                </a>
            </div>
            <div className="text-xs text-white/70">
                &copy; {new Date().getFullYear()} Nilo. All rights reserved.
            </div>
        </motion.div>
    </footer>
);

export default Footer;
