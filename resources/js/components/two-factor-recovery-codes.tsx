import { PillButton, SoftTile } from '@/components/dashboard/primitives';
import NiloSpinner from '@/components/nilo-spinner';
import { regenerateRecoveryCodes } from '@/routes/two-factor';
import { Form } from '@inertiajs/react';
import { Eye, EyeOff, LockKeyhole, RefreshCw } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import AlertError from './alert-error';

interface TwoFactorRecoveryCodesProps {
    recoveryCodesList: string[];
    fetchRecoveryCodes: () => Promise<void>;
    errors: string[];
    /** Whether the session is confirmed enough to read the codes. */
    canReveal: boolean;
    onRequestReveal: () => void;
}

export default function TwoFactorRecoveryCodes({
    recoveryCodesList,
    fetchRecoveryCodes,
    errors,
    canReveal,
    onRequestReveal,
}: TwoFactorRecoveryCodesProps) {
    const [codesAreVisible, setCodesAreVisible] = useState<boolean>(false);
    const codesSectionRef = useRef<HTMLDivElement | null>(null);
    const canRegenerateCodes = recoveryCodesList.length > 0 && codesAreVisible;

    const revealCodes = useCallback(async () => {
        if (!recoveryCodesList.length) {
            await fetchRecoveryCodes();
        }

        setCodesAreVisible(true);

        setTimeout(() => {
            codesSectionRef.current?.scrollIntoView({
                behavior: 'smooth',
                block: 'nearest',
            });
        });
    }, [recoveryCodesList.length, fetchRecoveryCodes]);

    const toggleCodesVisibility = useCallback(async () => {
        if (codesAreVisible) {
            setCodesAreVisible(false);

            return;
        }

        if (!canReveal) {
            onRequestReveal();

            return;
        }

        await revealCodes();
    }, [codesAreVisible, canReveal, onRequestReveal, revealCodes]);

    /**
     * The codes endpoint requires a confirmed session, so they are never
     * fetched on mount — only once the page says the session qualifies.
     */
    const autoRevealedRef = useRef<boolean>(false);

    useEffect(() => {
        if (!canReveal) {
            autoRevealedRef.current = false;

            return;
        }

        if (autoRevealedRef.current) {
            return;
        }

        autoRevealedRef.current = true;
        revealCodes();
    }, [canReveal, revealCodes]);

    const RecoveryCodeIconComponent = codesAreVisible ? EyeOff : Eye;

    return (
        <SoftTile className="p-4">
            <div className="flex items-start gap-2.5">
                <span className="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">
                    <LockKeyhole className="h-4 w-4" aria-hidden="true" />
                </span>
                <div>
                    <div className="text-sm font-semibold">Recovery codes</div>
                    <p className="mt-0.5 text-xs text-muted-foreground">
                        Recovery codes let you regain access if you lose your
                        2FA device. Store them in a secure password manager.
                    </p>
                </div>
            </div>

            <div className="mt-3 flex flex-col gap-2 select-none sm:flex-row sm:items-center sm:justify-between">
                <PillButton
                    variant="soft"
                    size="sm"
                    onClick={toggleCodesVisibility}
                    aria-expanded={codesAreVisible}
                    aria-controls="recovery-codes-section"
                >
                    <RecoveryCodeIconComponent
                        className="h-4 w-4"
                        aria-hidden="true"
                    />
                    {codesAreVisible ? 'Hide' : 'View'} recovery codes
                </PillButton>

                {canRegenerateCodes && (
                    <Form
                        {...regenerateRecoveryCodes.form()}
                        options={{ preserveScroll: true }}
                        onSuccess={fetchRecoveryCodes}
                    >
                        {({ processing }) => (
                            <PillButton
                                variant="ghost"
                                size="sm"
                                type="submit"
                                disabled={processing}
                                aria-describedby="regenerate-warning"
                            >
                                {processing ? (
                                    <NiloSpinner size={16} />
                                ) : (
                                    <RefreshCw className="h-4 w-4" />
                                )}{' '}
                                Regenerate codes
                            </PillButton>
                        )}
                    </Form>
                )}
            </div>

            <div
                id="recovery-codes-section"
                className={`relative overflow-hidden transition-all duration-300 ${codesAreVisible ? 'h-auto opacity-100' : 'h-0 opacity-0'}`}
                aria-hidden={!codesAreVisible}
            >
                <div className="mt-3 space-y-3">
                    {errors?.length ? (
                        <AlertError errors={errors} />
                    ) : (
                        <>
                            <div
                                ref={codesSectionRef}
                                className="grid gap-1 rounded-2xl bg-background p-4 font-mono text-sm dark:bg-black/20"
                                role="list"
                                aria-label="Recovery codes"
                            >
                                {recoveryCodesList.length ? (
                                    recoveryCodesList.map((code, index) => (
                                        <div
                                            key={index}
                                            role="listitem"
                                            className="select-text"
                                        >
                                            {code}
                                        </div>
                                    ))
                                ) : (
                                    <div
                                        className="space-y-2"
                                        aria-label="Loading recovery codes"
                                    >
                                        {Array.from(
                                            { length: 8 },
                                            (_, index) => (
                                                <div
                                                    key={index}
                                                    className="h-4 animate-pulse rounded bg-muted-foreground/20"
                                                    aria-hidden="true"
                                                />
                                            ),
                                        )}
                                    </div>
                                )}
                            </div>

                            <p
                                id="regenerate-warning"
                                className="text-xs text-muted-foreground select-none"
                            >
                                Each recovery code can be used once to access
                                your account and will be removed after use. If
                                you need more, click{' '}
                                <span className="font-semibold">
                                    Regenerate codes
                                </span>{' '}
                                above.
                            </p>
                        </>
                    )}
                </div>
            </div>
        </SoftTile>
    );
}
