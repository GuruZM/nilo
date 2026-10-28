import { router } from '@inertiajs/react';
import { useEffect } from 'react';

/**
 * Re-reads the props a document builder fills its pickers from.
 *
 * The page a builder mounts with is not always fresh: sidebar links are
 * prefetched (and a prefetch already in flight survives the `flushAll()` in
 * app.tsx), the back button replays the props from history, and a tab left
 * open never hears about a client added in another one. Any of those leaves a
 * record that exists out of its picker. A partial reload on mount, and again
 * whenever the tab is shown, puts it back without touching the form in
 * progress.
 */
export function useFreshProps(only: string[]): void {
    const key = only.join(',');

    useEffect(() => {
        const props = key.split(',');
        const refresh = () => router.reload({ only: props });

        const refreshWhenVisible = () => {
            if (document.visibilityState === 'visible') {
                refresh();
            }
        };

        refresh();
        document.addEventListener('visibilitychange', refreshWhenVisible);

        return () =>
            document.removeEventListener(
                'visibilitychange',
                refreshWhenVisible,
            );
    }, [key]);
}
