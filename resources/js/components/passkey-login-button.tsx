import NiloSpinner from '@/components/nilo-spinner';
import { cn } from '@/lib/utils';
import { router } from '@inertiajs/react';
import { usePasskeyVerify } from '@laravel/passkeys/react';
import { ChevronRight, Fingerprint } from 'lucide-react';
import { useState } from 'react';

interface PasskeyLoginButtonProps {
    label?: string;
    hint?: string;
    tabIndex?: number;
    /**
     * Anchors the browser's passkey picker to an input marked
     * `autocomplete="email webauthn"`, so a returning user can pick a passkey
     * straight from the email field without pressing anything.
     */
    autofill?: boolean;
}

/**
 * Signs a user in with a passkey via Fortify's WebAuthn endpoints.
 *
 * Rendered only when the browser can actually do it — an unsupported browser
 * gets nothing rather than a button that fails on click.
 */
export default function PasskeyLoginButton({
    label = 'Sign in with a passkey',
    hint = 'Use your face, fingerprint or device PIN',
    tabIndex,
    autofill = false,
}: PasskeyLoginButtonProps) {
    /**
     * Owned here rather than read from the hook's `isLoading`, which is shared
     * with the autofill ceremony. With `autofill` on, the hook arms a
     * conditional-mediation request on mount and stays "loading" until the user
     * picks a passkey from the email field — the whole life of the page, if
     * they never do. Rendering from it left this button reading "Waiting for
     * your device…" and disabled from first paint, so it could never be
     * pressed. Autofill is meant to wait invisibly; only a press should show.
     */
    const [verifying, setVerifying] = useState<boolean>(false);

    const { verify, error, isSupported } = usePasskeyVerify({
        autofill,
        onSuccess: (response) =>
            router.visit(response.redirect ?? '/dashboard'),
    });

    if (!isSupported) {
        return null;
    }

    /**
     * Pressing the button cancels any pending autofill ceremony, so the two
     * never race. `verify` reports failures through `error` rather than
     * rejecting, hence the plain await.
     */
    const handleClick = async (): Promise<void> => {
        setVerifying(true);

        try {
            await verify();
        } finally {
            setVerifying(false);
        }
    };

    return (
        <div className="flex w-full flex-col gap-2">
            <button
                type="button"
                onClick={handleClick}
                tabIndex={tabIndex}
                disabled={verifying}
                aria-busy={verifying}
                data-test="passkey-login-button"
                className={cn(
                    'group relative isolate flex h-14 w-full items-center gap-3.5 overflow-hidden rounded-2xl border border-brand/15 bg-gradient-to-br from-brand-50 via-white to-brand-50/50 px-3.5 text-left shadow-sm transition-all duration-200 outline-none hover:-translate-y-px hover:border-brand/30 hover:shadow-lg hover:shadow-brand-900/10 focus-visible:ring-2 focus-visible:ring-brand-400 focus-visible:ring-offset-2 focus-visible:ring-offset-background active:translate-y-0 active:scale-[0.995] dark:border-white/10 dark:from-brand-950/60 dark:via-white/[0.03] dark:to-brand-950/30 dark:hover:border-brand-400/30 dark:hover:shadow-black/30',
                    verifying && 'pointer-events-none opacity-70',
                )}
            >
                {/* A sheen that sweeps across on hover, so the recommended path feels alive next to the plain provider buttons. */}
                <span
                    aria-hidden="true"
                    className="pointer-events-none absolute inset-y-0 -left-full z-10 w-1/2 skew-x-12 bg-gradient-to-r from-transparent via-white/50 to-transparent transition-all duration-700 ease-out group-hover:left-full dark:via-white/10"
                />

                {verifying ? (
                    <>
                        <span className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-brand/10 dark:bg-white/10">
                            <NiloSpinner size={20} />
                        </span>
                        <span className="text-[15px] font-semibold text-foreground">
                            Waiting for your device…
                        </span>
                    </>
                ) : (
                    <>
                        <span className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-brand-700 text-white shadow-sm shadow-brand-900/30 transition-transform duration-200 group-hover:scale-105">
                            <Fingerprint className="size-[18px]" />
                        </span>
                        <span className="flex min-w-0 flex-col">
                            <span className="text-[15px] leading-tight font-semibold text-foreground">
                                {label}
                            </span>
                            <span className="truncate text-xs leading-tight text-muted-foreground">
                                {hint}
                            </span>
                        </span>
                        <ChevronRight className="ml-auto size-4 shrink-0 text-brand/50 transition-transform duration-200 group-hover:translate-x-0.5 dark:text-brand-300/50" />
                    </>
                )}
            </button>

            {error && (
                <p className="text-center text-sm text-destructive">{error}</p>
            )}
        </div>
    );
}
