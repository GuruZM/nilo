import AuthenticatedSessionController from '@/actions/App/Http/Controllers/Auth/AuthenticatedSessionController';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { register } from '@/routes';
import { request } from '@/routes/password';
import { Head, useForm } from '@inertiajs/react';
import { Bot, LoaderCircle, Shield, Zap } from 'lucide-react';
import { type FormEvent } from 'react';

interface LoginProps {
    status?: string;
    canResetPassword: boolean;
}

export default function Login({ status, canResetPassword }: LoginProps) {
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
        <div className="relative min-h-screen w-full overflow-hidden bg-gradient-to-br from-slate-950 via-blue-950 to-slate-900">
            <Head title="Log in" />

            {/* Animated abstract background objects */}
            <div className="fixed inset-0 -z-10">
                {/* Large floating orbs */}
                <div
                    className="absolute -top-40 -right-40 h-96 w-96 animate-pulse rounded-full bg-gradient-to-br from-blue-500/30 to-cyan-500/30 blur-3xl"
                    style={{ animation: 'float 20s ease-in-out infinite' }}
                />
                <div
                    className="absolute -bottom-40 -left-40 h-96 w-96 animate-pulse rounded-full bg-gradient-to-br from-purple-500/30 to-pink-500/30 blur-3xl"
                    style={{
                        animation: 'float 25s ease-in-out infinite reverse',
                    }}
                />
                <div
                    className="absolute top-1/3 right-1/4 h-64 w-64 animate-pulse rounded-full bg-gradient-to-br from-indigo-500/20 to-blue-500/20 blur-3xl"
                    style={{ animation: 'float 18s ease-in-out infinite' }}
                />

                {/* Moving geometric shapes */}
                <div
                    className="absolute top-20 left-1/4 h-32 w-32 animate-spin rounded-lg bg-gradient-to-br from-blue-400/10 to-transparent blur-xl"
                    style={{
                        animation:
                            'spin 30s linear infinite, drift 15s ease-in-out infinite',
                    }}
                />
                <div
                    className="absolute right-1/3 bottom-1/4 h-40 w-40 rounded-full bg-gradient-to-br from-purple-400/10 to-transparent blur-xl"
                    style={{
                        animation:
                            'pulse 4s ease-in-out infinite, drift 20s ease-in-out infinite reverse',
                    }}
                />
                <div
                    className="absolute top-1/2 left-1/3 h-24 w-24 rotate-45 bg-gradient-to-br from-cyan-400/10 to-transparent blur-xl"
                    style={{ animation: 'float 22s ease-in-out infinite' }}
                />

                {/* Grid pattern overlay */}
                <div className="absolute inset-0 bg-[linear-gradient(rgba(255,255,255,0.02)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.02)_1px,transparent_1px)] [mask-image:radial-gradient(ellipse_80%_80%_at_50%_50%,black,transparent)] bg-[size:50px_50px]" />

                {/* Floating abstract lines */}
                <svg
                    className="absolute inset-0 h-full w-full opacity-20"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <defs>
                        <linearGradient
                            id="grad1"
                            x1="0%"
                            y1="0%"
                            x2="100%"
                            y2="100%"
                        >
                            <stop
                                offset="0%"
                                style={{
                                    stopColor: 'rgb(59, 130, 246)',
                                    stopOpacity: 0.3,
                                }}
                            />
                            <stop
                                offset="100%"
                                style={{
                                    stopColor: 'rgb(147, 51, 234)',
                                    stopOpacity: 0.1,
                                }}
                            />
                        </linearGradient>
                    </defs>
                    <path
                        d="M0,200 Q400,100 800,200 T1600,200"
                        stroke="url(#grad1)"
                        strokeWidth="2"
                        fill="none"
                        style={{ animation: 'dashOffset 10s linear infinite' }}
                        strokeDasharray="20,10"
                    />
                    <path
                        d="M0,400 Q400,300 800,400 T1600,400"
                        stroke="url(#grad1)"
                        strokeWidth="2"
                        fill="none"
                        style={{
                            animation: 'dashOffset 15s linear infinite reverse',
                        }}
                        strokeDasharray="20,10"
                    />
                </svg>
            </div>

            {/* Main container */}
            <div className="flex min-h-screen items-center justify-center px-4 py-12">
                <div className="w-full max-w-md">
                    {/* Logo and branding */}
                    <div className="mb-8 text-center">
                        <h1 className="mb-2 bg-gradient-to-r from-blue-200 via-blue-100 to-purple-200 bg-clip-text text-4xl font-bold tracking-tight text-transparent">
                            Nilo AI
                        </h1>
                        <p className="text-sm text-blue-200/70">
                            Intelligent Invoice Management
                        </p>
                    </div>

                    {/* Main card */}
                    <div className="relative overflow-hidden rounded-2xl border border-white/10 bg-white/5 p-8 shadow-2xl backdrop-blur-xl">
                        {/* Card glow effect */}
                        <div className="absolute inset-0 rounded-2xl bg-gradient-to-br from-blue-500/10 via-transparent to-purple-500/10" />

                        {/* Status message */}
                        {status && (
                            <div className="relative mb-6 overflow-hidden rounded-lg border border-green-400/30 bg-green-500/10 p-4 text-center text-sm font-medium text-green-300 shadow-lg shadow-green-500/20 backdrop-blur-sm duration-500 animate-in fade-in slide-in-from-top-2">
                                <div className="absolute inset-0 animate-pulse bg-gradient-to-r from-green-400/10 via-transparent to-green-400/10" />
                                <span className="relative flex items-center justify-center gap-2">
                                    <Shield className="h-4 w-4" />
                                    {status}
                                </span>
                            </div>
                        )}

                        {/* Title */}
                        <div className="relative mb-6 text-center">
                            <h2 className="mb-1 text-2xl font-semibold text-white">
                                Welcome back
                            </h2>
                            <p className="text-sm text-blue-200/60">
                                Sign in to access your invoice dashboard
                            </p>
                        </div>

                        {/* Premium badges */}
                        <div className="relative mb-6 flex items-center justify-center gap-2 text-xs">
                            <span className="flex items-center gap-1.5 rounded-full border border-blue-400/30 bg-blue-500/10 px-3 py-1.5 text-blue-200 backdrop-blur-sm transition-all hover:scale-105 hover:bg-blue-500/20">
                                <Bot className="h-3 w-3" />
                                AI-Powered
                            </span>
                            <span className="flex items-center gap-1.5 rounded-full border border-green-400/30 bg-green-500/10 px-3 py-1.5 text-green-200 backdrop-blur-sm transition-all hover:scale-105 hover:bg-green-500/20">
                                <Shield className="h-3 w-3" />
                                Secure
                            </span>
                            <span className="flex items-center gap-1.5 rounded-full border border-yellow-400/30 bg-yellow-500/10 px-3 py-1.5 text-yellow-200 backdrop-blur-sm transition-all hover:scale-105 hover:bg-yellow-500/20">
                                <Zap className="h-3 w-3" />
                                Fast
                            </span>
                        </div>

                        <form
                            onSubmit={submit}
                            className="relative flex flex-col gap-6"
                        >
                            <div className="grid gap-5">
                                {/* Email field */}
                                <div className="group grid gap-2">
                                    <Label
                                        htmlFor="email"
                                        className="text-sm font-medium text-blue-100 transition-colors group-focus-within:text-blue-300"
                                    >
                                        Email address
                                    </Label>
                                    <div className="relative">
                                        <Input
                                            id="email"
                                            type="email"
                                            name="email"
                                            required
                                            autoFocus
                                            tabIndex={1}
                                            autoComplete="email"
                                            placeholder="email@example.com"
                                            value={data.email}
                                            onChange={(event) =>
                                                setData(
                                                    'email',
                                                    event.target.value,
                                                )
                                            }
                                            className="peer border-white/20 bg-white/5 text-white shadow-sm backdrop-blur-sm transition-all duration-200 placeholder:text-blue-200/40 hover:border-white/30 hover:bg-white/10 focus:border-blue-400/50 focus:bg-white/10 focus:ring-2 focus:ring-blue-400/30"
                                        />
                                        <div className="absolute inset-0 -z-10 rounded-md bg-gradient-to-r from-blue-500/0 via-blue-400/20 to-purple-500/0 opacity-0 blur transition-opacity peer-focus:opacity-100" />
                                    </div>
                                    <InputError message={errors.email} />
                                </div>

                                {/* Password field */}
                                <div className="group grid gap-2">
                                    <div className="flex items-center">
                                        <Label
                                            htmlFor="password"
                                            className="text-sm font-medium text-blue-100 transition-colors group-focus-within:text-blue-300"
                                        >
                                            Password
                                        </Label>
                                        {canResetPassword && (
                                            <TextLink
                                                href={request()}
                                                className="ml-auto text-sm text-blue-300/70 transition-all hover:translate-x-0.5 hover:text-blue-300"
                                                tabIndex={5}
                                            >
                                                Forgot password?
                                            </TextLink>
                                        )}
                                    </div>
                                    <div className="relative">
                                        <Input
                                            id="password"
                                            type="password"
                                            name="password"
                                            required
                                            tabIndex={2}
                                            autoComplete="current-password"
                                            placeholder="••••••••"
                                            value={data.password}
                                            onChange={(event) =>
                                                setData(
                                                    'password',
                                                    event.target.value,
                                                )
                                            }
                                            className="peer border-white/20 bg-white/5 text-white shadow-sm backdrop-blur-sm transition-all duration-200 placeholder:text-blue-200/40 hover:border-white/30 hover:bg-white/10 focus:border-purple-400/50 focus:bg-white/10 focus:ring-2 focus:ring-purple-400/30"
                                        />
                                        <div className="absolute inset-0 -z-10 rounded-md bg-gradient-to-r from-purple-500/0 via-purple-400/20 to-blue-500/0 opacity-0 blur transition-opacity peer-focus:opacity-100" />
                                    </div>
                                    <InputError message={errors.password} />
                                </div>

                                {/* Remember me */}
                                <div className="flex items-center space-x-3 rounded-lg border border-white/10 bg-white/5 p-3 backdrop-blur-sm transition-all hover:border-white/20 hover:bg-white/10">
                                    <Checkbox
                                        id="remember"
                                        name="remember"
                                        tabIndex={3}
                                        checked={data.remember}
                                        onCheckedChange={(checked) =>
                                            setData(
                                                'remember',
                                                checked === true,
                                            )
                                        }
                                        className="border-white/30 transition-all data-[state=checked]:scale-110 data-[state=checked]:border-blue-400 data-[state=checked]:bg-blue-500"
                                    />
                                    <Label
                                        htmlFor="remember"
                                        className="cursor-pointer text-sm font-medium text-blue-100 select-none"
                                    >
                                        Remember me for 30 days
                                    </Label>
                                </div>

                                {/* Login button */}
                                <Button
                                    type="submit"
                                    className="group relative mt-2 w-full overflow-hidden bg-gradient-to-r from-blue-600 via-blue-500 to-blue-800 shadow-lg shadow-blue-500/50 transition-all hover:scale-[1.02] hover:shadow-xl hover:shadow-blue-500/60 active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:scale-100"
                                    tabIndex={4}
                                    disabled={processing}
                                    data-test="login-button"
                                >
                                    <div className="absolute inset-0 -translate-x-full bg-gradient-to-r from-transparent via-white/30 to-transparent transition-transform duration-1000 group-hover:translate-x-full" />
                                    <span className="relative flex items-center justify-center gap-2 font-semibold text-white">
                                        {processing ? (
                                            <>
                                                <LoaderCircle className="h-4 w-4 animate-spin" />
                                                Logging in...
                                            </>
                                        ) : (
                                            <>
                                                <Shield className="h-4 w-4" />
                                                Log in
                                            </>
                                        )}
                                    </span>
                                </Button>
                            </div>

                            {/* Divider */}
                            <div className="relative">
                                <div className="absolute inset-0 flex items-center">
                                    <span className="w-full border-t border-white/10" />
                                </div>
                                <div className="relative flex justify-center text-xs uppercase">
                                    <span className="bg-white/5 px-3 text-blue-200/60 backdrop-blur-sm">
                                        New to Nilo?
                                    </span>
                                </div>
                            </div>

                            {/* Sign up link */}
                            <div className="text-center">
                                <TextLink
                                    href={register()}
                                    tabIndex={5}
                                    className="inline-flex items-center gap-2 rounded-lg border border-white/20 bg-white/5 px-6 py-2.5 text-sm font-medium text-blue-100 backdrop-blur-sm transition-all hover:scale-105 hover:border-white/30 hover:bg-white/10 hover:text-white hover:shadow-lg hover:shadow-blue-500/20 active:scale-95"
                                >
                                    Create your free account
                                </TextLink>
                            </div>
                        </form>
                    </div>

                    {/* Footer */}
                    <div className="mt-8 text-center text-xs text-blue-200/40">
                        <p>Protected by enterprise-grade encryption</p>
                    </div>
                </div>
            </div>
        </div>
    );
}
