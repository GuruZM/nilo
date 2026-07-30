import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import { send } from '@/routes/verification';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Transition } from '@headlessui/react';
import { Form, Head, Link, usePage } from '@inertiajs/react';

import {
    FormField,
    Panel,
    PanelHeader,
    PillButton,
    SoftTile,
    fieldInputClass,
} from '@/components/dashboard/primitives';
import DeleteUser from '@/components/delete-user';
import InputError from '@/components/input-error';
import NiloSpinner from '@/components/nilo-spinner';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { edit } from '@/routes/profile';
import { UserCog } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Profile settings',
        href: edit().url,
    },
];

export default function Profile({
    mustVerifyEmail,
    status,
}: {
    mustVerifyEmail: boolean;
    status?: string;
}) {
    const { auth } = usePage<SharedData>().props;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Profile settings" />

            <SettingsLayout>
                <Panel>
                    <PanelHeader
                        icon={UserCog}
                        title="Profile information"
                        subtitle="Update your name and email address"
                    />

                    <Form
                        {...ProfileController.update.form()}
                        options={{
                            preserveScroll: true,
                        }}
                        className="flex flex-col gap-4"
                    >
                        {({ processing, recentlySuccessful, errors }) => (
                            <>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <FormField label="Name" htmlFor="name">
                                        <input
                                            id="name"
                                            className={fieldInputClass}
                                            defaultValue={auth.user.name}
                                            name="name"
                                            required
                                            autoComplete="name"
                                            placeholder="Full name"
                                        />

                                        <InputError message={errors.name} />
                                    </FormField>

                                    <FormField
                                        label="Email address"
                                        htmlFor="email"
                                    >
                                        <input
                                            id="email"
                                            type="email"
                                            className={fieldInputClass}
                                            defaultValue={auth.user.email}
                                            name="email"
                                            required
                                            autoComplete="username"
                                            placeholder="Email address"
                                        />

                                        <InputError message={errors.email} />
                                    </FormField>
                                </div>

                                {mustVerifyEmail &&
                                    auth.user.email_verified_at === null && (
                                        <SoftTile className="p-4">
                                            <p className="text-sm text-muted-foreground">
                                                Your email address is
                                                unverified.{' '}
                                                <Link
                                                    href={send()}
                                                    as="button"
                                                    className="font-medium text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                                                >
                                                    Click here to resend the
                                                    verification email.
                                                </Link>
                                            </p>

                                            {status ===
                                                'verification-link-sent' && (
                                                <div className="mt-2 text-sm font-medium text-emerald-600 dark:text-emerald-400">
                                                    A new verification link has
                                                    been sent to your email
                                                    address.
                                                </div>
                                            )}
                                        </SoftTile>
                                    )}

                                <div className="flex items-center gap-3">
                                    <PillButton
                                        type="submit"
                                        disabled={processing}
                                        data-test="update-profile-button"
                                    >
                                        {processing && (
                                            <NiloSpinner size={16} />
                                        )}
                                        Save changes
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

                <DeleteUser />
            </SettingsLayout>
        </AppLayout>
    );
}
