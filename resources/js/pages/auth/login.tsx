import AuthenticatedSessionController from '@/actions/App/Http/Controllers/Auth/AuthenticatedSessionController';
import AuthField from '@/components/auth/auth-field';
import FacebookLoginButton from '@/components/facebook-login-button';
import GoogleLoginButton from '@/components/google-login-button';
import LinkedInLoginButton from '@/components/linkedin-login-button';
import NiloSpinner from '@/components/nilo-spinner';
import PasskeyLoginButton from '@/components/passkey-login-button';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';
import { register } from '@/routes';
import { request } from '@/routes/password';
import { type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import { ArrowRight, Lock, Mail } from 'lucide-react';
import { type FormEvent } from 'react';

interface LoginProps {
    status?: string;
    canResetPassword: boolean;
}

export default function Login({ status, canResetPassword }: LoginProps) {
    const { oauth, flash } = usePage<SharedData>().props;
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        post(AuthenticatedSessionController.store.url(), {
            onSuccess: () => reset('password'),
        });
    };

    return (
        <AuthLayout title="Sign in">
            <Head title="Log in" />

            {status && (
                <div className="rounded-xl border border-brand/20 bg-brand-50 px-4 py-3 text-center text-sm font-medium text-brand dark:bg-brand-950/50 dark:text-brand-300">
                    {status}
                </div>
            )}

            {flash.error && (
                <div className="rounded-xl border border-destructive/20 bg-destructive/10 px-4 py-3 text-center text-sm font-medium text-destructive">
                    {flash.error}
                </div>
            )}

            <form onSubmit={submit} className="flex flex-col gap-6">
                <div className="grid gap-5">
                    <AuthField
                        id="email"
                        label="Email address"
                        icon={Mail}
                        type="email"
                        name="email"
                        required
                        autoFocus
                        tabIndex={1}
                        autoComplete="email webauthn"
                        placeholder="you@company.com"
                        value={data.email}
                        onChange={(event) =>
                            setData('email', event.target.value)
                        }
                        error={errors.email}
                    />

                    <AuthField
                        id="password"
                        label="Password"
                        icon={Lock}
                        type="password"
                        name="password"
                        required
                        tabIndex={2}
                        autoComplete="current-password"
                        placeholder="Enter your password"
                        value={data.password}
                        onChange={(event) =>
                            setData('password', event.target.value)
                        }
                        error={errors.password}
                        labelAside={
                            canResetPassword && (
                                <TextLink
                                    href={request()}
                                    className="text-sm font-medium"
                                    tabIndex={5}
                                >
                                    Forgot password?
                                </TextLink>
                            )
                        }
                    />

                    <div className="flex items-center gap-3">
                        <Checkbox
                            id="remember"
                            name="remember"
                            tabIndex={3}
                            checked={data.remember}
                            onCheckedChange={(checked) =>
                                setData('remember', checked === true)
                            }
                        />
                        <Label
                            htmlFor="remember"
                            className="text-sm font-normal text-muted-foreground"
                        >
                            Keep me signed in
                        </Label>
                    </div>

                    <Button
                        type="submit"
                        className="group mt-1 h-12 w-full rounded-xl bg-brand text-[15px] font-semibold text-white shadow-lg shadow-brand-900/20 transition-all duration-200 hover:bg-brand-700 hover:shadow-xl hover:shadow-brand-900/30 active:scale-[0.99]"
                        tabIndex={4}
                        disabled={processing}
                        data-test="login-button"
                    >
                        {processing ? (
                            <NiloSpinner size={16} />
                        ) : (
                            <>
                                Log in
                                <ArrowRight className="size-4 transition-transform duration-200 group-hover:translate-x-0.5" />
                            </>
                        )}
                    </Button>
                </div>

                <p className="text-right text-sm text-muted-foreground">
                    New to Nilo?{' '}
                    <TextLink
                        href={register()}
                        className="font-medium"
                        tabIndex={5}
                    >
                        Create your free account
                    </TextLink>
                </p>
            </form>

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
                <PasskeyLoginButton autofill tabIndex={6} />

                <div className="flex items-center justify-center gap-3">
                    <GoogleLoginButton iconOnly tabIndex={7} />
                    {oauth.facebook && (
                        <FacebookLoginButton iconOnly tabIndex={8} />
                    )}
                    {oauth.linkedin && (
                        <LinkedInLoginButton iconOnly tabIndex={9} />
                    )}
                </div>
            </div>
        </AuthLayout>
    );
}
