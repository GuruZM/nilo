import RegisteredUserController from '@/actions/App/Http/Controllers/Auth/RegisteredUserController';
import { cn } from '@/lib/utils';
import { login } from '@/routes';
import { type SharedData } from '@/types';
import { Form, Head, usePage } from '@inertiajs/react';
import { ArrowRight, Lock, Mail, Pointer, User } from 'lucide-react';
import { useState } from 'react';

import AuthField from '@/components/auth/auth-field';
import GoogleLoginButton from '@/components/google-login-button';
import InputError from '@/components/input-error';
import LinkedInLoginButton from '@/components/linkedin-login-button';
import NiloSpinner from '@/components/nilo-spinner';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';

export default function Register() {
    const { oauth, flash } = usePage<SharedData>().props;
    const [agreedToTerms, setAgreedToTerms] = useState(false);
    const [nudgeTerms, setNudgeTerms] = useState(false);

    return (
        <AuthLayout title="Sign up" tagline="With you every step of the way.">
            <Head title="Register" />

            {flash.error && (
                <div className="rounded-xl border border-destructive/20 bg-destructive/10 px-4 py-3 text-center text-sm font-medium text-destructive">
                    {flash.error}
                </div>
            )}

            <Form
                {...RegisteredUserController.store.form()}
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-5">
                            <AuthField
                                id="name"
                                label="Full name"
                                icon={User}
                                type="text"
                                name="name"
                                required
                                autoFocus
                                tabIndex={1}
                                autoComplete="name"
                                error={errors.name}
                            />

                            <AuthField
                                id="email"
                                label="Email address"
                                icon={Mail}
                                type="email"
                                name="email"
                                required
                                tabIndex={2}
                                autoComplete="email"
                                error={errors.email}
                            />

                            <AuthField
                                id="password"
                                label="Password"
                                icon={Lock}
                                type="password"
                                name="password"
                                required
                                tabIndex={3}
                                autoComplete="new-password"
                                hint="Use at least 8 characters."
                                error={errors.password}
                            />

                            <AuthField
                                id="password_confirmation"
                                label="Confirm password"
                                icon={Lock}
                                type="password"
                                name="password_confirmation"
                                required
                                tabIndex={4}
                                autoComplete="new-password"
                                error={errors.password_confirmation}
                            />

                            <div className="flex flex-col gap-2">
                                <div className="relative flex items-start gap-3">
                                    {nudgeTerms && !agreedToTerms && (
                                        <Pointer
                                            aria-hidden
                                            className="absolute -top-6 -left-4 size-6 rotate-[135deg] animate-bounce text-destructive drop-shadow-sm"
                                        />
                                    )}
                                    <Checkbox
                                        id="terms"
                                        name="terms"
                                        required
                                        checked={agreedToTerms}
                                        onCheckedChange={(checked) => {
                                            setAgreedToTerms(checked === true);
                                            if (checked === true) {
                                                setNudgeTerms(false);
                                            }
                                        }}
                                        tabIndex={5}
                                        aria-invalid={
                                            errors.terms ? true : undefined
                                        }
                                        className={cn(
                                            'mt-0.5',
                                            nudgeTerms &&
                                                !agreedToTerms &&
                                                'ring-2 ring-destructive ring-offset-2',
                                        )}
                                    />
                                    <Label
                                        htmlFor="terms"
                                        className="text-sm leading-snug font-normal text-muted-foreground"
                                    >
                                        I agree to the{' '}
                                        <a
                                            href="/terms"
                                            className="font-medium text-brand underline underline-offset-4 hover:text-brand-700"
                                        >
                                            Terms of Service
                                        </a>{' '}
                                        and{' '}
                                        <a
                                            href="/privacy"
                                            className="font-medium text-brand underline underline-offset-4 hover:text-brand-700"
                                        >
                                            Privacy Policy
                                        </a>
                                        .
                                    </Label>
                                </div>
                                <InputError message={errors.terms} />
                            </div>

                            <Button
                                type="submit"
                                className="group mt-1 h-12 w-full rounded-xl bg-brand text-[15px] font-semibold text-white shadow-lg shadow-brand-900/20 transition-all duration-200 hover:bg-brand-700 hover:shadow-xl hover:shadow-brand-900/30 active:scale-[0.99]"
                                tabIndex={6}
                                data-test="register-user-button"
                            >
                                {processing ? (
                                    <NiloSpinner size={16} />
                                ) : (
                                    <>
                                        Create account
                                        <ArrowRight className="size-4 transition-transform duration-200 group-hover:translate-x-0.5" />
                                    </>
                                )}
                            </Button>
                        </div>

                        <p className="text-right text-sm text-muted-foreground">
                            Already have an account?{' '}
                            <TextLink
                                href={login()}
                                className="font-medium"
                                tabIndex={7}
                            >
                                Log in
                            </TextLink>
                        </p>
                    </>
                )}
            </Form>

            <div className="flex flex-col items-stretch gap-4">
                <div className="relative w-full">
                    <div className="absolute inset-0 flex items-center">
                        <span className="w-full border-t border-border" />
                    </div>
                    <div className="relative flex justify-center">
                        <span className="bg-background px-3 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                            or
                        </span>
                    </div>
                </div>
                <div className="flex items-center justify-center gap-3">
                    <GoogleLoginButton
                        iconOnly
                        intent="register"
                        disabled={!agreedToTerms}
                        onDisabledClick={() => setNudgeTerms(true)}
                        tabIndex={8}
                    />
                    {oauth.linkedin && (
                        <LinkedInLoginButton
                            iconOnly
                            intent="register"
                            disabled={!agreedToTerms}
                            onDisabledClick={() => setNudgeTerms(true)}
                            tabIndex={9}
                        />
                    )}
                </div>
                {!agreedToTerms && (
                    <p
                        className={cn(
                            'text-center text-xs transition-colors',
                            nudgeTerms
                                ? 'font-medium text-destructive'
                                : 'text-muted-foreground',
                        )}
                    >
                        Agree to the terms above to continue with a social
                        account.
                    </p>
                )}
            </div>
        </AuthLayout>
    );
}
