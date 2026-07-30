import ConfirmIdentityModal, {
    type SensitiveAction,
} from '@/components/confirm-identity-modal';
import {
    Panel,
    PanelHeader,
    PillButton,
    SoftTile,
} from '@/components/dashboard/primitives';
import NiloSpinner from '@/components/nilo-spinner';
import TwoFactorRecoveryCodes from '@/components/two-factor-recovery-codes';
import TwoFactorSetupModal from '@/components/two-factor-setup-modal';
import { useTwoFactorAuth } from '@/hooks/use-two-factor-auth';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { cn } from '@/lib/utils';
import { disable, enable, show } from '@/routes/two-factor';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import {
    KeyRound,
    ScanLine,
    ShieldBan,
    ShieldCheck,
    Smartphone,
} from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

interface TwoFactorProps {
    requiresConfirmation?: boolean;
    twoFactorEnabled?: boolean;
    identityConfirmed?: boolean;
    hasPassword?: boolean;
    reauthProviders?: string[];
    resumeAction?: SensitiveAction | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Two-factor authentication',
        href: show.url(),
    },
];

/** How enrolment works, shown while 2FA is off so the ask is not a black box. */
const setupSteps = [
    {
        icon: Smartphone,
        title: 'Open your authenticator',
        description:
            'Any TOTP app works — Google Authenticator, 1Password, Authy.',
    },
    {
        icon: ScanLine,
        title: 'Scan the QR code',
        description: 'We show it once you start setup, plus a key to type.',
    },
    {
        icon: KeyRound,
        title: 'Save your recovery codes',
        description: 'They get you back in if you lose the device.',
    },
];

function StatePill({ enabled }: { enabled: boolean }) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold',
                enabled
                    ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                    : 'bg-amber-500/10 text-amber-700 dark:text-amber-400',
            )}
        >
            <span
                className={cn(
                    'h-1.5 w-1.5 rounded-full',
                    enabled ? 'bg-emerald-500' : 'bg-amber-500',
                )}
                aria-hidden
            />
            {enabled ? 'Enabled' : 'Not enabled'}
        </span>
    );
}

export default function TwoFactor({
    requiresConfirmation = false,
    twoFactorEnabled = false,
    identityConfirmed = false,
    hasPassword = false,
    reauthProviders = [],
    resumeAction = null,
}: TwoFactorProps) {
    const {
        qrCodeSvg,
        hasSetupData,
        manualSetupKey,
        clearSetupData,
        fetchSetupData,
        recoveryCodesList,
        fetchRecoveryCodes,
        errors,
    } = useTwoFactorAuth();
    const [showSetupModal, setShowSetupModal] = useState<boolean>(false);
    const [showRecoveryCodes, setShowRecoveryCodes] = useState<boolean>(false);
    const [pendingAction, setPendingAction] = useState<SensitiveAction | null>(
        null,
    );
    const [processing, setProcessing] = useState<boolean>(false);

    const runAction = useCallback((action: SensitiveAction): void => {
        if (action === 'enable') {
            setProcessing(true);
            router.post(
                enable.url(),
                {},
                {
                    preserveScroll: true,
                    onSuccess: () => setShowSetupModal(true),
                    onFinish: () => setProcessing(false),
                },
            );

            return;
        }

        if (action === 'disable') {
            setProcessing(true);
            router.delete(disable.url(), {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
            });

            return;
        }

        setShowRecoveryCodes(true);
    }, []);

    /**
     * Sensitive changes need a confirmed session. When it isn't confirmed the
     * modal takes over and replays the action once the user proves it's them.
     */
    const requestAction = useCallback(
        (action: SensitiveAction): void => {
            if (identityConfirmed) {
                runAction(action);

                return;
            }

            setPendingAction(action);
        },
        [identityConfirmed, runAction],
    );

    const handleConfirmed = useCallback((): void => {
        const action = pendingAction;
        setPendingAction(null);

        if (action) {
            runAction(action);
        }
    }, [pendingAction, runAction]);

    /**
     * Provider re-authentication leaves the app, so the action the user asked
     * for is handed back on the return leg and replayed here.
     */
    const resumedRef = useRef<boolean>(false);

    useEffect(() => {
        if (!resumeAction || resumedRef.current || !identityConfirmed) {
            return;
        }

        resumedRef.current = true;
        runAction(resumeAction);
    }, [resumeAction, identityConfirmed, runAction]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Two-factor authentication" />
            <SettingsLayout>
                <Panel>
                    <PanelHeader
                        icon={ShieldCheck}
                        title="Two-factor authentication"
                        subtitle="Ask for a one-time code from your phone on every sign in"
                        action={<StatePill enabled={twoFactorEnabled} />}
                    />

                    {twoFactorEnabled ? (
                        <div className="flex flex-col gap-4">
                            <SoftTile className="p-4">
                                <p className="text-sm text-muted-foreground">
                                    Your account asks for a six-digit code from
                                    your authenticator app after your password.
                                    Even a leaked password isn't enough to get
                                    in.
                                </p>
                            </SoftTile>

                            <TwoFactorRecoveryCodes
                                recoveryCodesList={recoveryCodesList}
                                fetchRecoveryCodes={fetchRecoveryCodes}
                                errors={errors}
                                canReveal={showRecoveryCodes}
                                onRequestReveal={() =>
                                    requestAction('recovery-codes')
                                }
                            />

                            <div className="flex flex-wrap items-center justify-between gap-3 pt-1">
                                <p className="text-xs text-muted-foreground">
                                    Turning this off leaves your password as the
                                    only thing between your account and someone
                                    else.
                                </p>

                                <PillButton
                                    variant="ghost"
                                    disabled={processing}
                                    onClick={() => requestAction('disable')}
                                    className="text-destructive hover:bg-destructive/10"
                                >
                                    {processing ? (
                                        <NiloSpinner size={16} />
                                    ) : (
                                        <ShieldBan className="h-4 w-4" />
                                    )}
                                    Turn off
                                </PillButton>
                            </div>
                        </div>
                    ) : (
                        <div className="flex flex-col gap-4">
                            <p className="text-sm text-muted-foreground">
                                A password alone can be phished or reused. With
                                two-factor on, signing in also needs a code that
                                only your phone can produce.
                            </p>

                            <div className="grid gap-2 sm:grid-cols-3">
                                {setupSteps.map((step, index) => (
                                    <SoftTile
                                        key={step.title}
                                        className="flex flex-col gap-2 p-4"
                                    >
                                        <div className="flex items-center gap-2">
                                            <span className="grid h-7 w-7 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">
                                                <step.icon className="h-3.5 w-3.5" />
                                            </span>
                                            <span className="text-xs font-semibold text-muted-foreground">
                                                Step {index + 1}
                                            </span>
                                        </div>

                                        <div>
                                            <div className="text-sm font-semibold">
                                                {step.title}
                                            </div>
                                            <p className="mt-0.5 text-xs text-muted-foreground">
                                                {step.description}
                                            </p>
                                        </div>
                                    </SoftTile>
                                ))}
                            </div>

                            <div>
                                {hasSetupData ? (
                                    <PillButton
                                        onClick={() => setShowSetupModal(true)}
                                    >
                                        <ShieldCheck className="h-4 w-4" />
                                        Continue setup
                                    </PillButton>
                                ) : (
                                    <PillButton
                                        disabled={processing}
                                        onClick={() => requestAction('enable')}
                                    >
                                        {processing ? (
                                            <NiloSpinner size={16} />
                                        ) : (
                                            <ShieldCheck className="h-4 w-4" />
                                        )}
                                        Enable 2FA
                                    </PillButton>
                                )}
                            </div>
                        </div>
                    )}
                </Panel>

                <ConfirmIdentityModal
                    action={pendingAction}
                    onClose={() => setPendingAction(null)}
                    onConfirmed={handleConfirmed}
                    hasPassword={hasPassword}
                    reauthProviders={reauthProviders}
                />

                <TwoFactorSetupModal
                    isOpen={showSetupModal}
                    onClose={() => setShowSetupModal(false)}
                    requiresConfirmation={requiresConfirmation}
                    twoFactorEnabled={twoFactorEnabled}
                    qrCodeSvg={qrCodeSvg}
                    manualSetupKey={manualSetupKey}
                    clearSetupData={clearSetupData}
                    fetchSetupData={fetchSetupData}
                    errors={errors}
                />
            </SettingsLayout>
        </AppLayout>
    );
}
