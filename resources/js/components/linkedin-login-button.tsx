import NiloSpinner from '@/components/nilo-spinner';
import { cn } from '@/lib/utils';
import { type MouseEvent, useState } from 'react';

interface LinkedInLoginButtonProps {
    label?: string;
    tabIndex?: number;
    iconOnly?: boolean;
    intent?: 'login' | 'register';
    disabled?: boolean;
    onDisabledClick?: () => void;
}

const LinkedInIcon = () => (
    <svg className="h-5 w-5" viewBox="0 0 24 24" aria-hidden="true">
        <path
            fill="#0A66C2"
            d="M20.45 20.45h-3.56v-5.57c0-1.33-.03-3.04-1.85-3.04-1.85 0-2.14 1.45-2.14 2.94v5.67H9.35V9h3.42v1.56h.05c.48-.9 1.64-1.85 3.37-1.85 3.6 0 4.27 2.37 4.27 5.46v6.28zM5.34 7.43a2.07 2.07 0 1 1 0-4.14 2.07 2.07 0 0 1 0 4.14zM7.12 20.45H3.56V9h3.56v11.45zM22.22 0H1.77C.79 0 0 .77 0 1.72v20.56C0 23.23.79 24 1.77 24h20.45c.98 0 1.78-.77 1.78-1.72V1.72C24 .77 23.2 0 22.22 0z"
        />
    </svg>
);

export default function LinkedInLoginButton({
    label = 'Continue with LinkedIn',
    tabIndex,
    iconOnly = false,
    intent = 'login',
    disabled = false,
    onDisabledClick,
}: LinkedInLoginButtonProps) {
    const [loading, setLoading] = useState(false);

    const href =
        intent === 'register'
            ? '/auth/linkedin?intent=register&terms=1'
            : '/auth/linkedin';

    const handleClick = (event: MouseEvent): void => {
        if (disabled || loading) {
            event.preventDefault();
            if (disabled) {
                onDisabledClick?.();
            }

            return;
        }

        setLoading(true);
    };

    if (iconOnly) {
        return (
            <a
                href={href}
                onClick={handleClick}
                tabIndex={disabled ? -1 : tabIndex}
                aria-label={label}
                aria-disabled={disabled || loading}
                aria-busy={loading}
                title={label}
                className={cn(
                    'inline-flex size-12 items-center justify-center rounded-xl border border-border bg-white shadow-sm transition-all hover:bg-gray-50 hover:shadow-md active:scale-[0.99] dark:border-input dark:bg-white/[0.04] dark:hover:bg-white/[0.08]',
                    (disabled || loading) && 'cursor-not-allowed opacity-50',
                )}
            >
                {loading ? <NiloSpinner size={22} /> : <LinkedInIcon />}
            </a>
        );
    }

    return (
        <a
            href={href}
            onClick={handleClick}
            tabIndex={disabled ? -1 : tabIndex}
            aria-disabled={disabled || loading}
            aria-busy={loading}
            className={cn(
                'inline-flex h-12 w-full items-center justify-center gap-3 rounded-xl border border-border bg-white px-4 text-[15px] font-medium text-gray-700 shadow-sm transition-all hover:bg-gray-50 hover:shadow-md active:scale-[0.99] dark:border-input dark:bg-white/[0.04] dark:text-foreground dark:hover:bg-white/[0.08]',
                (disabled || loading) && 'cursor-not-allowed opacity-50',
            )}
        >
            {loading ? (
                <>
                    <NiloSpinner size={20} />
                    Connecting…
                </>
            ) : (
                <>
                    <LinkedInIcon />
                    {label}
                </>
            )}
        </a>
    );
}
