/**
 * Keeps the `csrf-token` meta tag in step with the session.
 *
 * Blade renders the tag once per document. Inertia then swaps pages without
 * re-rendering `<head>`, so any token regeneration — logging out, a session
 * expiring — leaves the tag holding a value the server will reject. Inertia's
 * own requests are unaffected because they authenticate off the XSRF cookie,
 * but code that reads this tag to build a `fetch` gets a 419.
 */
export const syncCsrfToken = (token: unknown): void => {
    if (typeof token !== 'string' || token === '') {
        return;
    }

    const tag = document.querySelector<HTMLMetaElement>(
        'meta[name="csrf-token"]',
    );

    if (!tag) {
        return;
    }

    if (tag.content !== token) {
        tag.content = token;
    }
};
