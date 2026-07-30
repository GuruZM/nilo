import NiloSpinner from '@/components/nilo-spinner';
import { cn } from '@/lib/utils';
import { type MouseEvent, useState } from 'react';

interface GoogleLoginButtonProps {
    label?: string;
    tabIndex?: number;
    iconOnly?: boolean;
    intent?: 'login' | 'register';
    disabled?: boolean;
    onDisabledClick?: () => void;
}

export const GoogleIcon = () => (
    <svg className="h-5 w-5" viewBox="0 0 24 24" aria-hidden="true">
        <path
            fill="#4285F4"
            d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z"
        />
        <path
            fill="#34A853"
            d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84A11 11 0 0 0 12 23z"
        />
        <path
            fill="#FBBC05"
            d="M5.84 14.1a6.6 6.6 0 0 1 0-4.2V7.06H2.18a11 11 0 0 0 0 9.88l3.66-2.84z"
        />
        <path
            fill="#EA4335"
            d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1A11 11 0 0 0 2.18 7.06l3.66 2.84C6.71 7.31 9.14 5.38 12 5.38z"
        />
    </svg>
);

export default function GoogleLoginButton({
    label = 'Continue with Google',
    tabIndex,
    iconOnly = false,
    intent = 'login',
    disabled = false,
    onDisabledClick,
}: GoogleLoginButtonProps) {
    const [loading, setLoading] = useState(false);

    const href =
        intent === 'register'
            ? '/auth/google?intent=register&terms=1'
            : '/auth/google';

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
                {loading ? <NiloSpinner size={22} /> : <GoogleIcon />}
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
                    <GoogleIcon />
                    {label}
                </>
            )}
        </a>
    );
}
