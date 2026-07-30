import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { initializeTheme } from './hooks/use-appearance';
import { syncCsrfToken } from './lib/csrf';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.tsx`,
            import.meta.glob('./pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(<App {...props} />);
    },
    progress: {
        color: '#4B5563',
    },
});

/**
 * `success` rather than `navigate`: history restores replay cached props, whose
 * token was current when that page was fetched, not now. Only a fresh server
 * response can be trusted to carry the live token.
 */
router.on('success', (event) => {
    syncCsrfToken(event.detail.page.props.csrfToken);
});

/**
 * Every sidebar link is `prefetch`, so Inertia caches those pages for 30s and
 * `router.visit` serves a cached hit outright without revalidating. Inertia
 * only drops entries whose `cacheTags` intersect a visit's
 * `invalidateCacheTags`, and both default to empty — so nothing was ever
 * dropped, and for up to 30s after saving a document the dashboard and list
 * pages rendered their pre-mutation snapshot.
 *
 * `finish` rather than `success`: it carries the visit's method, and it also
 * covers responses that failed validation after a partial write.
 */
router.on('finish', (event) => {
    if (event.detail.visit.method !== 'get') {
        router.flushAll();
    }
});

// This will set light / dark mode on load...
initializeTheme();
