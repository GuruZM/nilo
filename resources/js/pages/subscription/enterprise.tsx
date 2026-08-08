import {
    FormField,
    Panel,
    PanelHeader,
    PillButton,
    fieldInputClass,
} from '@/components/dashboard/primitives';
import NiloSpinner from '@/components/nilo-spinner';
import { GateShell } from '@/components/subscription/gate-shell';
import { cn } from '@/lib/utils';
import { Head, useForm, usePage } from '@inertiajs/react';
import { Mail, MessageCircle, MessageSquare, Phone } from 'lucide-react';
import { type FormEvent } from 'react';

const CONTACT_CHANNELS = [
    {
        icon: MessageCircle,
        label: 'WhatsApp',
        detail: 'Chat with us on WhatsApp',
        href: 'https://wa.me/260970000000',
        external: true,
    },
    {
        icon: Mail,
        label: 'Email',
        detail: 'enterprise@nilo.co.zm',
        href: 'mailto:enterprise@nilo.co.zm',
        external: false,
    },
    {
        icon: Phone,
        label: 'Phone',
        detail: '+260 97 000 0000',
        href: 'tel:+260970000000',
        external: false,
    },
];

export default function Enterprise() {
    const { flash } = usePage().props as { flash?: { success?: string } };

    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        phone: '',
        company_name: '',
        message: '',
    });

    const submit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        post('/subscription/enterprise', { onSuccess: () => reset() });
    };

    return (
        <GateShell
            title="Enterprise plan"
            subtitle="Get a custom solution tailored to your business needs."
            backHref="/subscription/select"
        >
            <Head title="Enterprise Plan" />

            <Panel>
                <PanelHeader
                    icon={MessageCircle}
                    title="Get in touch"
                    subtitle="Reach us on whichever channel suits you."
                />

                <div className="flex flex-col gap-2">
                    {CONTACT_CHANNELS.map((channel) => (
                        <a
                            key={channel.label}
                            href={channel.href}
                            target={channel.external ? '_blank' : undefined}
                            rel={
                                channel.external
                                    ? 'noopener noreferrer'
                                    : undefined
                            }
                            className={cn(
                                'flex items-center gap-3 rounded-2xl bg-muted/50 p-3 transition dark:bg-white/5',
                                'hover:bg-brand-50/70 dark:hover:bg-brand-500/10',
                            )}
                        >
                            <span className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">
                                <channel.icon className="h-4 w-4" />
                            </span>
                            <span className="min-w-0">
                                <span className="block text-sm font-semibold">
                                    {channel.label}
                                </span>
                                <span className="block truncate text-xs text-muted-foreground">
                                    {channel.detail}
                                </span>
                            </span>
                        </a>
                    ))}
                </div>
            </Panel>

            <Panel>
                <PanelHeader
                    icon={MessageSquare}
                    title="Or send us a message"
                    subtitle="We usually reply within one business day."
                />

                {flash?.success ? (
                    <p className="mb-4 rounded-2xl bg-emerald-500/10 px-3 py-2 text-xs text-emerald-700 dark:text-emerald-400">
                        {flash.success}
                    </p>
                ) : null}

                <form onSubmit={submit} className="flex flex-col gap-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField label="Name" htmlFor="name" required>
                            <input
                                id="name"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                className={fieldInputClass}
                            />
                            {errors.name ? (
                                <p className="text-xs text-destructive">
                                    {errors.name}
                                </p>
                            ) : null}
                        </FormField>

                        <FormField label="Email" htmlFor="email" required>
                            <input
                                id="email"
                                type="email"
                                value={data.email}
                                onChange={(e) =>
                                    setData('email', e.target.value)
                                }
                                className={fieldInputClass}
                            />
                            {errors.email ? (
                                <p className="text-xs text-destructive">
                                    {errors.email}
                                </p>
                            ) : null}
                        </FormField>

                        <FormField label="Phone" htmlFor="phone">
                            <input
                                id="phone"
                                type="tel"
                                value={data.phone}
                                onChange={(e) =>
                                    setData('phone', e.target.value)
                                }
                                className={fieldInputClass}
                            />
                            {errors.phone ? (
                                <p className="text-xs text-destructive">
                                    {errors.phone}
                                </p>
                            ) : null}
                        </FormField>

                        <FormField label="Company" htmlFor="company_name">
                            <input
                                id="company_name"
                                value={data.company_name}
                                onChange={(e) =>
                                    setData('company_name', e.target.value)
                                }
                                className={fieldInputClass}
                            />
                            {errors.company_name ? (
                                <p className="text-xs text-destructive">
                                    {errors.company_name}
                                </p>
                            ) : null}
                        </FormField>
                    </div>

                    <FormField label="Message" htmlFor="message" required>
                        <textarea
                            id="message"
                            rows={4}
                            value={data.message}
                            onChange={(e) => setData('message', e.target.value)}
                            className={cn(fieldInputClass, 'h-auto py-2')}
                        />
                        {errors.message ? (
                            <p className="text-xs text-destructive">
                                {errors.message}
                            </p>
                        ) : null}
                    </FormField>

                    <PillButton
                        type="submit"
                        className="w-full"
                        disabled={processing}
                    >
                        {processing ? (
                            <>
                                <NiloSpinner size={16} />
                                Sending…
                            </>
                        ) : (
                            'Send inquiry'
                        )}
                    </PillButton>
                </form>
            </Panel>
        </GateShell>
    );
}
