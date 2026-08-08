import { GoogleIcon } from '@/components/google-login-button';
import InputError from '@/components/input-error';
import NiloSpinner from '@/components/nilo-spinner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { UserInfo } from '@/components/user-info';
import { edit } from '@/routes/password';
import { type SharedData } from '@/types';
import { Form, Link, usePage } from '@inertiajs/react';
import { ShieldQuestion } from 'lucide-react';

export type SensitiveAction =
    | 'enable'
    | 'disable'
    | 'recovery-codes'
    | 'register-passkey'
    | 'delete-passkey';

interface ConfirmIdentityModalProps {
    action: SensitiveAction | null;
    onClose: () => void;
    onConfirmed: () => void;
    hasPassword: boolean;
    reauthProviders: string[];
    /**
     * Which security page started the round trip, so provider
     * re-authentication returns here instead of to the two-factor page.
     */
    returnTo?: 'two-factor' | 'passkeys';
}

const ACTION_DESCRIPTIONS: Record<SensitiveAction, string> = {
    enable: 'Turning on two-factor authentication changes how you sign in.',
    disable:
        'Turning off two-factor authentication lowers your account security.',
    'recovery-codes':
        'Recovery codes can be used to sign in without your phone.',
    'register-passkey': 'Adding a passkey changes how you sign in.',
    'delete-passkey':
        'Removing a passkey means that device can no longer sign you in.',
};

const PROVIDER_LABELS: Record<string, string> = {
    google: 'Google',
};

/**
 * Asks the signed-in user to prove it's still them before a sensitive change.
 *
 * It deliberately keeps the app shell visible behind it and leads with the
 * current account, so it never reads as "you have been logged out" the way a
 * full-page password screen does.
 */
export default function ConfirmIdentityModal({
    action,
    onClose,
    onConfirmed,
    hasPassword,
    reauthProviders,
    returnTo = 'two-factor',
}: ConfirmIdentityModalProps) {
    const { auth } = usePage<SharedData>().props;
    const canReauthenticate = reauthProviders.length > 0;

    return (
        <Dialog
            open={action !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <div className="mx-auto mb-2 flex size-11 items-center justify-center rounded-full bg-muted">
                        <ShieldQuestion
                            className="size-5 text-muted-foreground"
                            aria-hidden="true"
                        />
                    </div>
                    <DialogTitle className="text-center">
                        Confirm it's you
                    </DialogTitle>
                    <DialogDescription className="text-center">
                        {action ? ACTION_DESCRIPTIONS[action] : ''} You're still
                        signed in — we just need to check before continuing.
                    </DialogDescription>
                </DialogHeader>

                <div className="flex items-center gap-3 rounded-lg border border-border bg-muted/40 p-3">
                    <UserInfo user={auth.user} showEmail />
                </div>

                {hasPassword && (
                    <Form
                        action="/settings/confirm-identity/password"
                        method="post"
                        resetOnError={['password']}
                        resetOnSuccess={['password']}
                        onSuccess={onConfirmed}
                    >
                        {({ processing, errors }) => (
                            <div className="space-y-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="confirm-identity-password">
                                        Password
                                    </Label>
                                    <Input
                                        id="confirm-identity-password"
                                        type="password"
                                        name="password"
                                        autoComplete="current-password"
                                        autoFocus
                                    />
                                    <InputError message={errors.password} />
                                </div>

                                <Button
                                    type="submit"
                                    className="w-full"
                                    disabled={processing}
                                    data-test="confirm-identity-button"
                                >
                                    {processing && <NiloSpinner size={16} />}
                                    Confirm
                                </Button>
                            </div>
                        )}
                    </Form>
                )}

                {hasPassword && canReauthenticate && (
                    <div className="flex items-center gap-3">
                        <span className="h-px flex-1 bg-border" />
                        <span className="text-xs text-muted-foreground">
                            or
                        </span>
                        <span className="h-px flex-1 bg-border" />
                    </div>
                )}

                {canReauthenticate && (
                    <div className="flex flex-col gap-2">
                        {reauthProviders.map((provider) => (
                            <a
                                key={provider}
                                href={`/settings/confirm-identity/${provider}?intent=${action ?? ''}&return=${returnTo}`}
                                className="inline-flex h-11 w-full items-center justify-center gap-3 rounded-xl border border-border bg-white px-4 text-[15px] font-medium text-gray-700 shadow-sm transition-all hover:bg-gray-50 hover:shadow-md active:scale-[0.99] dark:border-input dark:bg-white/[0.04] dark:text-foreground dark:hover:bg-white/[0.08]"
                            >
                                <GoogleIcon />
                                Continue with {PROVIDER_LABELS[provider]}
                            </a>
                        ))}
                    </div>
                )}

                {!hasPassword && !canReauthenticate && (
                    <div className="space-y-3 text-sm text-muted-foreground">
                        <p>
                            Your sign-in provider can't re-check your identity,
                            so you'll need a password on this account before
                            changing security settings.
                        </p>
                        <Button asChild className="w-full">
                            <Link href={edit()}>Create a password</Link>
                        </Button>
                    </div>
                )}

                <DialogFooter>
                    <Button
                        variant="ghost"
                        onClick={onClose}
                        className="w-full"
                    >
                        Cancel
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
