import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import {
    FormField,
    Panel,
    PanelHeader,
    PillButton,
    fieldInputClass,
    pillButtonClass,
} from '@/components/dashboard/primitives';
import InputError from '@/components/input-error';
import NiloSpinner from '@/components/nilo-spinner';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Form } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';
import { useRef } from 'react';

export default function DeleteUser() {
    const passwordInput = useRef<HTMLInputElement>(null);

    return (
        <Panel>
            <PanelHeader
                title="Delete account"
                subtitle="Delete your account and all of its resources"
            />

            <div className="flex flex-col gap-3 rounded-2xl bg-destructive/8 p-4 dark:bg-destructive/15">
                <div className="flex items-start gap-2.5">
                    <TriangleAlert
                        className="mt-0.5 h-4 w-4 shrink-0 text-destructive"
                        aria-hidden
                    />
                    <div>
                        <p className="text-sm font-semibold text-destructive">
                            This cannot be undone
                        </p>
                        <p className="mt-0.5 text-xs text-muted-foreground">
                            Your companies, invoices, quotations, and clients go
                            with it. Please proceed with caution.
                        </p>
                    </div>
                </div>

                <Dialog>
                    <DialogTrigger
                        className={pillButtonClass(
                            'ghost',
                            'sm',
                            'w-fit text-destructive hover:bg-destructive/10',
                        )}
                        data-test="delete-user-button"
                    >
                        Delete account
                    </DialogTrigger>
                    <DialogContent className="rounded-3xl">
                        <DialogTitle>
                            Are you sure you want to delete your account?
                        </DialogTitle>
                        <DialogDescription>
                            Once your account is deleted, all of its resources
                            and data will also be permanently deleted. Please
                            enter your password to confirm you would like to
                            permanently delete your account.
                        </DialogDescription>

                        <Form
                            {...ProfileController.destroy.form()}
                            options={{
                                preserveScroll: true,
                            }}
                            onError={() => passwordInput.current?.focus()}
                            resetOnSuccess
                            className="flex flex-col gap-4"
                        >
                            {({ resetAndClearErrors, processing, errors }) => (
                                <>
                                    <FormField
                                        label="Password"
                                        htmlFor="password"
                                    >
                                        <input
                                            id="password"
                                            type="password"
                                            name="password"
                                            ref={passwordInput}
                                            autoComplete="current-password"
                                            className={fieldInputClass}
                                        />

                                        <InputError message={errors.password} />
                                    </FormField>

                                    <DialogFooter className="gap-2">
                                        <DialogClose
                                            className={pillButtonClass('ghost')}
                                            onClick={() =>
                                                resetAndClearErrors()
                                            }
                                        >
                                            Cancel
                                        </DialogClose>

                                        <PillButton
                                            type="submit"
                                            disabled={processing}
                                            data-test="confirm-delete-user-button"
                                            className="bg-destructive text-white shadow-none hover:bg-destructive/90"
                                        >
                                            {processing && (
                                                <NiloSpinner size={16} />
                                            )}
                                            Delete account
                                        </PillButton>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    </DialogContent>
                </Dialog>
            </div>
        </Panel>
    );
}
