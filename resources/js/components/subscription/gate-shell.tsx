import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import * as React from 'react';

/**
 * The shell for the pre-subscription pages, which render outside the app
 * sidebar. It puts the same grey canvas under them that the dashboard uses, so
 * the white panels lift the same way here as they do once the user is inside.
 */
export function GateShell({
    title,
    subtitle,
    width = 'md',
    backHref,
    backLabel = 'Back to plans',
    children,
}: {
    title: string;
    subtitle?: React.ReactNode;
    width?: 'md' | 'lg' | 'xl';
    backHref?: string;
    backLabel?: string;
    children: React.ReactNode;
}) {
    const maxWidth = {
        md: 'max-w-lg',
        lg: 'max-w-3xl',
        xl: 'max-w-6xl',
    }[width];

    return (
        <div className="flex min-h-svh flex-col items-center justify-center bg-canvas px-4 py-10">
            <div className={`w-full ${maxWidth}`}>
                <div className="mb-8 flex flex-col items-center gap-3 text-center">
                    <Link href={home()}>
                        <AppLogoIcon className="h-12 w-auto" />
                    </Link>
                    <h1 className="text-2xl tracking-tight">{title}</h1>
                    {subtitle ? (
                        <p className="max-w-lg text-sm text-muted-foreground">
                            {subtitle}
                        </p>
                    ) : null}
                </div>

                <div className="flex flex-col gap-4">{children}</div>

                {backHref ? (
                    <div className="mt-6 text-center">
                        <Link
                            href={backHref}
                            className="inline-flex items-center gap-1.5 text-sm text-muted-foreground transition hover:text-foreground"
                        >
                            <ArrowLeft className="h-4 w-4" />
                            {backLabel}
                        </Link>
                    </div>
                ) : null}
            </div>
        </div>
    );
}
