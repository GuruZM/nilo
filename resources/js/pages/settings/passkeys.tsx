import ConfirmIdentityModal, {
    type SensitiveAction,
} from '@/components/confirm-identity-modal';
import {
    EmptyState,
    FormField,
    IconButton,
    Panel,
    PanelHeader,
    PillButton,
    SoftTile,
    fieldInputClass,
} from '@/components/dashboard/primitives';
import InputError from '@/components/input-error';
import NiloSpinner from '@/components/nilo-spinner';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { usePasskeyRegister } from '@laravel/passkeys/react';
import { Fingerprint, Info, Plus, Trash2 } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

interface Passkey {
    id: number;
    name: string;
    created_at: string | null;
    last_used_at: string | null;
}

interface PasskeysProps {
    passkeys?: Passkey[];
    identityConfirmed?: boolean;
    hasPassword?: boolean;
    reauthProviders?: string[];
    resumeAction?: SensitiveAction | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Passkeys',
        href: '/settings/passkeys',
    },
];

const formatDate = (value: string | null): string =>
    value
        ? new Date(value).toLocaleDateString(undefined, {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
          })
        : 'Never';

/**
 * Suggests a name for the passkey based on the device registering it, so the
 * list stays readable when someone enrols more than one.
 */
const suggestPasskeyName = (): string => {
    const agent = window.navigator.userAgent;

    if (/iPhone|iPad/.test(agent)) {
        return 'iPhone';
    }

    if (/Macintosh/.test(agent)) {
        return 'Mac';
    }

    if (/Android/.test(agent)) {
        return 'Android device';
    }

    if (/Windows/.test(agent)) {
        return 'Windows PC';
    }

    return 'My device';
};

export default function Passkeys({
    passkeys = [],
    identityConfirmed = false,
    hasPassword = false,
    reauthProviders = [],
    resumeAction = null,
}: PasskeysProps) {
    const [pendingAction, setPendingAction] = useState<SensitiveAction | null>(
        null,
    );
    const [showNameDialog, setShowNameDialog] = useState<boolean>(false);
    const [name, setName] = useState<string>('');
    const [deleting, setDeleting] = useState<number | null>(null);
    const pendingDeleteRef = useRef<number | null>(null);

    const { register, isLoading, error, isSupported } = usePasskeyRegister({
        onSuccess: () => {
            setShowNameDialog(false);
            router.reload({ only: ['passkeys'] });
        },
    });

    const runAction = useCallback((action: SensitiveAction): void => {
        if (action === 'register-passkey') {
            setName(suggestPasskeyName());
            setShowNameDialog(true);

            return;
        }

        if (action === 'delete-passkey') {
            const id = pendingDeleteRef.current;

            if (id === null) {
                return;
            }

            pendingDeleteRef.current = null;
            setDeleting(id);

            router.delete(`/user/passkeys/${id}`, {
                preserveScroll: true,
                onFinish: () => setDeleting(null),
            });
        }
    }, []);

    /**
     * Fortify guards its passkey routes with `password.confirm`. When the
     * session isn't confirmed the modal takes over and replays the action once
     * the user proves it's them.
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

    const requestDelete = useCallback(
        (id: number): void => {
            pendingDeleteRef.current = id;
            requestAction('delete-passkey');
        },
        [requestAction],
    );

    /**
     * Provider re-authentication leaves the app, so the action the user asked
     * for is handed back on the return leg and replayed here. Deletes are not
     * replayed: which passkey was targeted does not survive the round trip, so
     * the user re-picks it from a now-confirmed session.
     */
    const resumedRef = useRef<boolean>(false);

    useEffect(() => {
        if (
            resumeAction !== 'register-passkey' ||
            resumedRef.current ||
            !identityConfirmed
        ) {
            return;
        }

        resumedRef.current = true;
        runAction(resumeAction);
    }, [resumeAction, identityConfirmed, runAction]);

    const addPasskey = (
        <PillButton
            size="sm"
            disabled={!isSupported}
            onClick={() => requestAction('register-passkey')}
            data-test="add-passkey-button"
        >
            <Plus className="h-4 w-4" />
            Add a passkey
        </PillButton>
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Passkeys" />
            <SettingsLayout>
                <Panel>
                    <PanelHeader
                        icon={Fingerprint}
                        title="Passkeys"
                        subtitle="Sign in with Face ID, Touch ID, Windows Hello, or a security key"
                        action={passkeys.length > 0 ? addPasskey : undefined}
                    />

                    <div className="flex flex-col gap-3">
                        {!isSupported && (
                            <SoftTile className="flex items-start gap-2.5 p-4">
                                <Info
                                    className="mt-0.5 h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400"
                                    aria-hidden
                                />
                                <p className="text-sm text-muted-foreground">
                                    This browser can't create passkeys. You can
                                    still manage existing ones here, and add new
                                    ones from a browser that supports them.
                                </p>
                            </SoftTile>
                        )}

                        {passkeys.length === 0 ? (
                            <EmptyState
                                icon={Fingerprint}
                                title="No passkeys yet"
                                description="A passkey lets you sign in with the same face, fingerprint, or PIN you use to unlock your device. Nothing is typed, so there's no password to leak or forget."
                                action={addPasskey}
                            />
                        ) : (
                            <ul className="flex flex-col gap-2">
                                {passkeys.map((passkey) => (
                                    <li key={passkey.id}>
                                        <SoftTile className="flex items-center justify-between gap-4 p-3">
                                            <div className="flex min-w-0 items-center gap-3">
                                                <span className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">
                                                    <Fingerprint
                                                        className="h-4 w-4"
                                                        aria-hidden
                                                    />
                                                </span>

                                                <div className="min-w-0">
                                                    <p className="truncate text-sm font-semibold">
                                                        {passkey.name}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        Added{' '}
                                                        {formatDate(
                                                            passkey.created_at,
                                                        )}
                                                        {' · Last used '}
                                                        {formatDate(
                                                            passkey.last_used_at,
                                                        )}
                                                    </p>
                                                </div>
                                            </div>

                                            <IconButton
                                                label={`Remove ${passkey.name}`}
                                                disabled={
                                                    deleting === passkey.id
                                                }
                                                onClick={() =>
                                                    requestDelete(passkey.id)
                                                }
                                                className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                            >
                                                {deleting === passkey.id ? (
                                                    <NiloSpinner size={16} />
                                                ) : (
                                                    <Trash2 className="h-4 w-4" />
                                                )}
                                            </IconButton>
                                        </SoftTile>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </Panel>

                <ConfirmIdentityModal
                    action={pendingAction}
                    onClose={() => {
                        pendingDeleteRef.current = null;
                        setPendingAction(null);
                    }}
                    onConfirmed={handleConfirmed}
                    hasPassword={hasPassword}
                    reauthProviders={reauthProviders}
                    returnTo="passkeys"
                />

                <Dialog
                    open={showNameDialog}
                    onOpenChange={(open) => !open && setShowNameDialog(false)}
                >
                    <DialogContent className="rounded-3xl sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>Name this passkey</DialogTitle>
                            <DialogDescription>
                                Your device will ask you to confirm. The name is
                                only so you can tell your passkeys apart later.
                            </DialogDescription>
                        </DialogHeader>

                        <FormField label="Name" htmlFor="passkey-name">
                            <input
                                id="passkey-name"
                                value={name}
                                autoFocus
                                maxLength={255}
                                onChange={(event) =>
                                    setName(event.target.value)
                                }
                                className={fieldInputClass}
                            />
                            <InputError message={error ?? undefined} />
                        </FormField>

                        <DialogFooter>
                            <PillButton
                                variant="ghost"
                                onClick={() => setShowNameDialog(false)}
                            >
                                Cancel
                            </PillButton>
                            <PillButton
                                disabled={isLoading || name.trim() === ''}
                                onClick={() => register(name.trim())}
                            >
                                {isLoading && <NiloSpinner size={16} />}
                                Create passkey
                            </PillButton>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </SettingsLayout>
        </AppLayout>
    );
}
