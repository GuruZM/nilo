import NiloSpinner from '@/components/nilo-spinner';
import { cn } from '@/lib/utils';
import { router } from '@inertiajs/react';
import { usePasskeyVerify } from '@laravel/passkeys/react';
import { Fingerprint } from 'lucide-react';
import { useState } from 'react';

interface PasskeyLoginButtonProps {
    label?: string;
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
                    'inline-flex h-12 w-full items-center justify-center gap-3 rounded-xl border border-border bg-white px-4 text-[15px] font-medium text-gray-700 shadow-sm transition-all hover:bg-gray-50 hover:shadow-md active:scale-[0.99] dark:border-input dark:bg-white/[0.04] dark:text-foreground dark:hover:bg-white/[0.08]',
                    verifying && 'cursor-not-allowed opacity-50',
                )}
            >
                {verifying ? (
                    <>
                        <NiloSpinner size={20} />
                        Waiting for your device…
                    </>
                ) : (
                    <>
                        <Fingerprint className="size-5 text-brand" />
                        {label}
                    </>
                )}
            </button>

            {error && (
                <p className="text-center text-sm text-destructive">{error}</p>
            )}
        </div>
    );
}
