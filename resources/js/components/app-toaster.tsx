import { Toaster } from 'sonner';
import type { CSSProperties } from 'react';

/**
 * Toasts use the same solid brand blue as primary buttons. Colours are driven by
 * the app's CSS variables so they follow light / dark mode automatically.
 * Errors keep the destructive red so failures still read as failures.
 */
const brandToastColors = {
    '--normal-bg': 'var(--primary)',
    '--normal-border': 'var(--primary)',
    '--normal-text': 'var(--primary-foreground)',

    '--success-bg': 'var(--primary)',
    '--success-border': 'var(--primary)',
    '--success-text': 'var(--primary-foreground)',

    '--info-bg': 'var(--primary)',
    '--info-border': 'var(--primary)',
    '--info-text': 'var(--primary-foreground)',

    '--warning-bg': 'var(--color-brand-400)',
    '--warning-border': 'var(--color-brand-400)',
    '--warning-text': '#ffffff',

    '--error-bg': 'var(--destructive)',
    '--error-border': 'var(--destructive)',
    '--error-text': '#ffffff',
} as CSSProperties;

export function AppToaster() {
    return (
        <Toaster
            richColors
            position="top-right"
            style={brandToastColors}
            toastOptions={{
                classNames: {
                    toast: 'rounded-lg shadow-lg',
                    description: '!text-current opacity-80',
                },
            }}
        />
    );
}

export default AppToaster;
