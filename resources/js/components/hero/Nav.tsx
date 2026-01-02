import type { Auth } from '@/types';
import { usePage } from '@inertiajs/react';
import { Menu, Moon, Sun, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import AppLogo from '../app-logo';

// Dummy NavLink for demonstration; replace with your router/link component if needed
const NavLink = ({ href, children, ...props }: any) => (
    <a
        href={href}
        className="transition-colors duration-200 hover:text-[#00417d]"
        {...props}
    >
        {children}
    </a>
);

const Nav: React.FC = () => {
    const { auth } = usePage<{ auth?: Auth }>().props;
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const [scrollY, setScrollY] = useState(0);
    const [darkMode, setDarkMode] = useState(false);

    useEffect(() => {
        const handleScroll = () => setScrollY(window.scrollY);
        window.addEventListener('scroll', handleScroll);
        return () => window.removeEventListener('scroll', handleScroll);
    }, []);

    useEffect(() => {
        const savedTheme =
            typeof window !== 'undefined'
                ? localStorage.getItem('theme')
                : null;
        const prefersDark =
            typeof window !== 'undefined'
                ? window.matchMedia('(prefers-color-scheme: dark)').matches
                : false;
        if (savedTheme === 'dark' || (!savedTheme && prefersDark)) {
            setDarkMode(true);
            if (typeof document !== 'undefined') {
                document.documentElement.classList.add('dark');
            }
        }
    }, []);

    const toggleDarkMode = () => {
        setDarkMode(!darkMode);
        if (typeof window !== 'undefined' && typeof document !== 'undefined') {
            if (!darkMode) {
                document.documentElement.classList.add('dark');
                localStorage.setItem('theme', 'dark');
            } else {
                document.documentElement.classList.remove('dark');
                localStorage.setItem('theme', 'light');
            }
        }
    };

    const scrollToSection = (sectionId: string) => {
        document
            .getElementById(sectionId)
            ?.scrollIntoView({ behavior: 'smooth' });
        setMobileMenuOpen(false);
    };

    return (
        <div className="bg-white transition-colors duration-300 dark:bg-slate-900">
            {/* Navigation */}
            <nav
                className={`fixed top-0 z-50 w-full transition-all duration-300 ${
                    scrollY > 50
                        ? 'bg-white/95 shadow-lg backdrop-blur-md dark:bg-slate-900/95'
                        : 'bg-transparent'
                }`}
            >
                <div className="mx-auto max-w-7xl px-6 py-4">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center space-x-3">
                            <AppLogo />
                        </div>

                        {/* Desktop Navigation */}
                        <div className="hidden items-center space-x-6 md:flex">
                            <NavLink href="#about">About</NavLink>
                            <NavLink href="#features">How It Works</NavLink>
                            <NavLink href="#pricing">Pricing</NavLink>
                            <NavLink href="#contact">Contact</NavLink>
                            {auth && auth.user ? (
                                <>
                                    <NavLink
                                        href="/dashboard"
                                        className="bg- rounded-lg bg-[#00417d] px-4 py-2 font-semibold text-white transition-colors duration-200 hover:bg-blue-800 dark:bg-[#00417d]"
                                    >
                                        Dashboard
                                    </NavLink>
                                </>
                            ) : (
                                <>
                                    <NavLink
                                        href="/login"
                                        className="rounded-lg border border-[#00417d] px-4 py-2 font-semibold text-[#00417d] transition-colors duration-200 hover:bg-[#00417d] hover:text-white"
                                    >
                                        Login
                                    </NavLink>
                                    <NavLink
                                        href="/register"
                                        className="rounded-lg bg-[#00417d] px-4 py-2 font-semibold text-white transition-colors duration-200 hover:bg-blue-800"
                                    >
                                        Register
                                    </NavLink>
                                </>
                            )}
                            <button
                                onClick={toggleDarkMode}
                                className="rounded-lg bg-slate-100 p-2 transition-colors duration-300 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700"
                            >
                                {darkMode ? (
                                    <Sun className="h-5 w-5 text-yellow-500" />
                                ) : (
                                    <Moon className="h-5 w-5 text-slate-600" />
                                )}
                            </button>
                        </div>

                        {/* Mobile Menu Button */}
                        <button
                            className="md:hidden"
                            onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                        >
                            {mobileMenuOpen ? (
                                <X className="h-6 w-6 text-slate-800 dark:text-white" />
                            ) : (
                                <Menu className="h-6 w-6 text-slate-800 dark:text-white" />
                            )}
                        </button>
                    </div>

                    {/* Mobile Menu */}
                    {mobileMenuOpen && (
                        <div className="mt-4 rounded-2xl border border-slate-100 bg-white py-4 shadow-xl md:hidden dark:border-slate-700 dark:bg-slate-800">
                            <div className="flex flex-col space-y-4 px-6">
                                {auth && auth.user ? (
                                    <>
                                        <NavLink
                                            href="/dashboard"
                                            className="rounded-lg bg-[#00417d] px-4 py-2 font-semibold text-white transition-colors duration-200 hover:bg-blue-800"
                                        >
                                            Dashboard
                                        </NavLink>
                                    </>
                                ) : (
                                    <>
                                        <NavLink
                                            href="/login"
                                            className="rounded-lg border border-[#00417d] px-4 py-2 font-semibold text-[#00417d] transition-colors duration-200 hover:bg-[#00417d] hover:text-white"
                                        >
                                            Login
                                        </NavLink>
                                        <NavLink
                                            href="/register"
                                            className="rounded-lg bg-[#00417d] px-4 py-2 font-semibold text-white transition-colors duration-200 hover:bg-blue-800"
                                        >
                                            Register
                                        </NavLink>
                                    </>
                                )}
                                <NavLink
                                    href="#about"
                                    onClick={() => scrollToSection('about')}
                                >
                                    About
                                </NavLink>
                                <NavLink
                                    href="#features"
                                    onClick={() => scrollToSection('features')}
                                >
                                    How It Works
                                </NavLink>
                                <NavLink
                                    href="#pricing"
                                    onClick={() => scrollToSection('pricing')}
                                >
                                    Pricing
                                </NavLink>
                                <NavLink
                                    href="#contact"
                                    onClick={() => scrollToSection('contact')}
                                >
                                    Contact
                                </NavLink>
                                <div className="flex items-center justify-between border-t border-slate-200 pt-4 dark:border-slate-600">
                                    <span className="text-slate-600 dark:text-slate-300">
                                        Dark Mode
                                    </span>
                                    <button
                                        onClick={toggleDarkMode}
                                        className="rounded-lg bg-slate-100 p-2 transition-colors duration-300 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600"
                                    >
                                        {darkMode ? (
                                            <Sun className="h-5 w-5 text-yellow-500" />
                                        ) : (
                                            <Moon className="h-5 w-5 text-slate-600 dark:text-slate-300" />
                                        )}
                                    </button>
                                </div>
                                {/* Removed duplicate button */}
                                <button
                                    className="mt-4 rounded-full bg-[#00417d] px-6 py-3 font-semibold text-white transition-all duration-300 hover:bg-blue-800 dark:bg-[#00417d] dark:hover:bg-blue-900"
                                    onClick={() => scrollToSection('pricing')}
                                >
                                    Start Free Trial
                                </button>
                            </div>
                        </div>
                    )}
                </div>
            </nav>
        </div>
    );
};

export default Nav;
