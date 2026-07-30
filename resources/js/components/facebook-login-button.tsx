import NiloSpinner from '@/components/nilo-spinner';
import { cn } from '@/lib/utils';
import { type MouseEvent, useState } from 'react';

interface FacebookLoginButtonProps {
    label?: string;
    tabIndex?: number;
    iconOnly?: boolean;
    intent?: 'login' | 'register';
    disabled?: boolean;
    onDisabledClick?: () => void;
}

const FacebookIcon = () => (
    <svg className="h-5 w-5" viewBox="0 0 24 24" aria-hidden="true">
        <path
            fill="#1877F2"
            d="M24 12.07C24 5.4 18.63 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.1 10.13 24v-8.44H7.08v-3.49h3.05V9.41c0-3.02 1.79-4.69 4.53-4.69 1.31 0 2.68.24 2.68.24v2.97h-1.51c-1.49 0-1.96.93-1.96 1.89v2.25h3.33l-.53 3.49h-2.8V24C19.61 23.1 24 18.1 24 12.07z"
        />
    </svg>
);

export default function FacebookLoginButton({
    label = 'Continue with Facebook',
    tabIndex,
    iconOnly = false,
    intent = 'login',
    disabled = false,
    onDisabledClick,
}: FacebookLoginButtonProps) {
    const [loading, setLoading] = useState(false);

    const href =
        intent === 'register'
            ? '/auth/facebook?intent=register&terms=1'
            : '/auth/facebook';

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
                {loading ? <NiloSpinner size={22} /> : <FacebookIcon />}
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
                    <FacebookIcon />
                    {label}
                </>
            )}
        </a>
    );
}
