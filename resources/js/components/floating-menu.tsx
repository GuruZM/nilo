import * as React from 'react';

import { cn } from '@/lib/utils';

const placementClass = {
    /** Anchored below the trigger and matched to its width. */
    'bottom-stretch': 'top-[calc(100%+0.375rem)] right-0 left-0',
    /** Anchored below the trigger, right edges aligned. */
    'bottom-end': 'top-[calc(100%+0.5rem)] right-0',
    /** Anchored beside the trigger — used by the icon-collapsed rail. */
    'right-start': 'top-0 left-[calc(100%+0.5rem)]',
} as const;

export type FloatingMenuPlacement = keyof typeof placementClass;

const panelClass = cn(
    'absolute z-50 overflow-hidden rounded-2xl bg-popover p-1.5',
    'animate-in fade-in-0 zoom-in-95',
    'shadow-[0_1px_2px_0_rgb(16_24_40/0.04),0_18px_44px_-16px_rgb(16_24_40/0.28)]',
    'dark:ring-1 dark:ring-white/10',
);

/** Row styling shared by every option inside a floating menu. */
export const menuRowClass = (isActive = false): string =>
    cn(
        'flex w-full items-center gap-2 rounded-xl px-3 py-2 text-left text-[13px] transition',
        'focus-visible:ring-2 focus-visible:ring-brand-400 focus-visible:outline-none',
        isActive
            ? 'bg-brand-50 font-semibold text-brand-700 dark:bg-brand-500/15 dark:text-brand-200'
            : 'text-muted-foreground hover:bg-muted hover:text-foreground dark:hover:bg-white/5',
    );

/**
 * Minimal popover primitive: a trigger, an anchored panel, and dismissal on
 * outside pointer or Escape. Deliberately unstyled beyond the panel shell so
 * callers keep the app's soft, borderless language instead of menu chrome.
 */
export function FloatingMenu({
    trigger,
    children,
    placement = 'bottom-stretch',
    role = 'menu',
    className,
    panelClassName,
}: {
    trigger: (state: { open: boolean; toggle: () => void }) => React.ReactNode;
    children: (close: () => void) => React.ReactNode;
    placement?: FloatingMenuPlacement;
    role?: 'menu' | 'listbox';
    className?: string;
    panelClassName?: string;
}) {
    const [open, setOpen] = React.useState(false);
    const containerRef = React.useRef<HTMLDivElement>(null);

    React.useEffect(() => {
        if (!open) {
            return;
        }

        const handlePointerDown = (event: PointerEvent) => {
            if (!containerRef.current?.contains(event.target as Node)) {
                setOpen(false);
            }
        };

        const handleKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setOpen(false);
            }
        };

        document.addEventListener('pointerdown', handlePointerDown);
        document.addEventListener('keydown', handleKeyDown);

        return () => {
            document.removeEventListener('pointerdown', handlePointerDown);
            document.removeEventListener('keydown', handleKeyDown);
        };
    }, [open]);

    return (
        <div ref={containerRef} className={cn('relative', className)}>
            {trigger({ open, toggle: () => setOpen((previous) => !previous) })}

            {open && (
                <div
                    role={role}
                    className={cn(
                        panelClass,
                        placementClass[placement],
                        panelClassName,
                    )}
                >
                    {children(() => setOpen(false))}
                </div>
            )}
        </div>
    );
}
