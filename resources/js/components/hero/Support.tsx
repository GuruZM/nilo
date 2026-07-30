import { motion, useReducedMotion } from 'framer-motion';
import { Building2, Mail, MessageCircle } from 'lucide-react';

const channels = [
    {
        icon: MessageCircle,
        label: 'Chat with us',
        value: 'Weekdays, 8am to 6pm',
        href: '#',
    },
    {
        icon: Mail,
        label: 'Email support',
        value: 'help@nilo.app',
        href: 'mailto:help@nilo.app',
    },
    {
        icon: Building2,
        label: 'Enterprise',
        value: 'Talk to the team',
        href: '/subscription/enterprise',
    },
];

export default function Support() {
    const reducedMotion = useReducedMotion();

    return (
        <section id="contact" className="bg-background pb-28">
            <div className="mx-auto max-w-7xl px-6">
                <motion.div
                    className="grid gap-10 rounded-3xl border border-border bg-card p-10 sm:p-14 lg:grid-cols-2 lg:items-center"
                    initial={{ opacity: 0, y: reducedMotion ? 0 : 24 }}
                    whileInView={{ opacity: 1, y: 0 }}
                    viewport={{ once: true, margin: '-80px' }}
                    transition={{ duration: 0.5, ease: 'easeOut' }}
                >
                    <div>
                        <h2 className="text-3xl font-bold text-foreground sm:text-4xl">
                            Stuck on something? Ask us.
                        </h2>
                        <p className="mt-4 max-w-md text-lg text-muted-foreground">
                            A real person answers. Whether it is a broken
                            template or a feature you wish existed, tell us and
                            we look into it.
                        </p>
                    </div>

                    <div className="flex flex-col divide-y divide-border">
                        {channels.map((channel) => (
                            <a
                                key={channel.label}
                                href={channel.href}
                                className="group flex items-center gap-4 py-4 transition-colors first:pt-0 last:pb-0"
                            >
                                <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-orange-500/10 text-orange-500 transition-colors group-hover:bg-orange-500 group-hover:text-white">
                                    <channel.icon className="h-5 w-5" />
                                </span>
                                <div>
                                    <p className="font-semibold text-foreground">
                                        {channel.label}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {channel.value}
                                    </p>
                                </div>
                            </a>
                        ))}
                    </div>
                </motion.div>
            </div>
        </section>
    );
}
