import PasswordController from '@/actions/App/Http/Controllers/Settings/PasswordController';
import {
    FormField,
    Panel,
    PanelHeader,
    PillButton,
    fieldInputClass,
} from '@/components/dashboard/primitives';
import InputError from '@/components/input-error';
import NiloSpinner from '@/components/nilo-spinner';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types/index.d';
import { Transition } from '@headlessui/react';
import { Form, Head } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';
import { useRef } from 'react';

import { edit } from '@/routes/password';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Password settings',
        href: edit().url,
    },
];

interface PasswordProps {
    /** False for accounts created through a social provider. */
    hasPassword?: boolean;
}

export default function Password({ hasPassword = true }: PasswordProps) {
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Password settings" />

            <SettingsLayout>
                <Panel>
                    <PanelHeader
                        icon={KeyRound}
                        title={
                            hasPassword
                                ? 'Update password'
                                : 'Create a password'
                        }
                        subtitle={
                            hasPassword
                                ? 'Ensure your account is using a long, random password to stay secure'
                                : 'You signed up with a social provider. Adding a password gives you a second way to sign in and to confirm sensitive changes.'
                        }
                    />

                    <Form
                        {...PasswordController.update.form()}
                        options={{
                            preserveScroll: true,
                        }}
                        resetOnError={[
                            'password',
                            'password_confirmation',
                            'current_password',
                        ]}
                        resetOnSuccess
                        onError={(errors) => {
                            if (errors.password) {
                                passwordInput.current?.focus();
                            }

                            if (errors.current_password) {
                                currentPasswordInput.current?.focus();
                            }
                        }}
                        className="flex flex-col gap-4"
                    >
                        {({ errors, processing, recentlySuccessful }) => (
                            <>
                                {hasPassword && (
                                    <FormField
                                        label="Current password"
                                        htmlFor="current_password"
                                        className="max-w-md"
                                    >
                                        <input
                                            id="current_password"
                                            ref={currentPasswordInput}
                                            name="current_password"
                                            type="password"
                                            className={fieldInputClass}
                                            autoComplete="current-password"
                                            placeholder="Current password"
                                        />

                                        <InputError
                                            message={errors.current_password}
                                        />
                                    </FormField>
                                )}

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <FormField
                                        label="New password"
                                        htmlFor="password"
                                    >
                                        <input
                                            id="password"
                                            ref={passwordInput}
                                            name="password"
                                            type="password"
                                            className={fieldInputClass}
                                            autoComplete="new-password"
                                            placeholder="New password"
                                        />

                                        <InputError message={errors.password} />
                                    </FormField>

                                    <FormField
                                        label="Confirm password"
                                        htmlFor="password_confirmation"
                                    >
                                        <input
                                            id="password_confirmation"
                                            name="password_confirmation"
                                            type="password"
                                            className={fieldInputClass}
                                            autoComplete="new-password"
                                            placeholder="Confirm password"
                                        />

                                        <InputError
                                            message={
                                                errors.password_confirmation
                                            }
                                        />
                                    </FormField>
                                </div>

                                <div className="flex items-center gap-3">
                                    <PillButton
                                        type="submit"
                                        disabled={processing}
                                        data-test="update-password-button"
                                    >
                                        {processing && (
                                            <NiloSpinner size={16} />
                                        )}
                                        Save password
                                    </PillButton>

                                    <Transition
                                        show={recentlySuccessful}
                                        enter="transition ease-in-out"
                                        enterFrom="opacity-0"
                                        leave="transition ease-in-out"
                                        leaveTo="opacity-0"
                                    >
                                        <p className="text-sm text-muted-foreground">
                                            Saved
                                        </p>
                                    </Transition>
                                </div>
                            </>
                        )}
                    </Form>
                </Panel>
            </SettingsLayout>
        </AppLayout>
    );
}
