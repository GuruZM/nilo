# Sign in with Google — Design

**Date:** 2026-07-18
**Status:** Approved

## Goal

Let users authenticate with their Google account on both the login and
registration pages, in addition to the existing email/password flow (Laravel
Fortify + Inertia/React).

## Decisions

- **Package:** Use `laravel/socialite` (first-party OAuth package).
- **Account linking:** If a Google sign-in email matches an existing
  password-based account, auto-link by attaching `google_id` to that account and
  logging the user in. Google emails are verified, so this is safe.
- **New Google users:** Create the account (email auto-verified, no password),
  then send them to `subscription.select`, mirroring the existing registration
  flow in `RegisteredUserController::store`.

## Dependency & Configuration

- Add `laravel/socialite` via Composer.
- Add a `google` provider entry to `config/services.php`:
  ```php
  'google' => [
      'client_id' => env('GOOGLE_CLIENT_ID'),
      'client_secret' => env('GOOGLE_CLIENT_SECRET'),
      'redirect' => env('GOOGLE_REDIRECT_URI'),
  ],
  ```
- Add `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` to
  `.env` and `.env.example` (values blank in `.env.example`).

## Database Migration

A single migration modifying the `users` table:

- Add `google_id` — `string`, `nullable`, `unique`.
- Make `password` **nullable**. Per Laravel 12 rules, the `change()` must
  re-declare all existing attributes of the column so nothing is lost — it is
  currently a non-nullable `string`, so the new declaration is a nullable
  `string`.

No `avatar` column (YAGNI).

## Backend

### Route (`routes/auth.php`, inside existing `guest` middleware group)

- `GET /auth/google` → `GoogleController@redirect`, name `google.redirect`
- `GET /auth/google/callback` → `GoogleController@callback`, name
  `google.callback`

### Controller `App\Http\Controllers\Auth\GoogleController`

- `redirect(): RedirectResponse`
  - Returns `Socialite::driver('google')->redirect()`.

- `callback(): RedirectResponse`
  1. Fetch the Google user. Wrap in try/catch — on any Socialite exception
     (denied consent, invalid state), redirect to `login` with a flash error
     message.
  2. Look up a user by `google_id`. If none, look up by `email`.
     - **Existing account (matched by email, no `google_id` yet):** set its
       `google_id`, save, log in. Redirect to the normal post-login
       destination (dashboard / `home`).
     - **Already-linked account (matched by `google_id`):** log in, redirect to
       dashboard.
     - **No account:** create a user with `name`, `email`,
       `email_verified_at = now()`, `google_id`, and `password = null`. Fire the
       `Registered` event, log in, redirect to `subscription.select`.

The controller distinguishes "new user" (→ `subscription.select`) from
"returning/linked user" (→ dashboard) by whether the user was just created.

## Frontend

Add a **"Continue with Google"** button to both:

- `resources/js/pages/auth/login.tsx`
- `resources/js/pages/auth/register.tsx`

Details:

- Rendered as a plain `<a href="/auth/google">` — a full-page navigation, **not**
  an Inertia `<Link>`/`router.visit`, because the OAuth redirect leaves the SPA.
- Google "G" logo (inline SVG) + label, styled to match existing buttons and
  supporting dark mode like the rest of the auth pages.
- An "or" divider separating the Google button from the existing
  email/password form.

## Testing (Pest feature tests)

Mock the Socialite driver (`Socialite::shouldReceive('driver->user')` returning
a fake Google user) and cover:

1. **New user:** unknown email creates a user (verified, `google_id` set,
   `password` null) and redirects to `subscription.select`.
2. **Auto-link:** existing password account with matching email gets `google_id`
   attached and is logged in (no duplicate user created).
3. **Returning linked user:** existing `google_id` logs in, no new user created.
4. **OAuth failure:** a Socialite exception redirects back to `login` with an
   error.
5. **Redirect endpoint:** `GET /auth/google` redirects to Google.

## Out of Scope (YAGNI)

- Avatar sync from Google.
- Unlinking a Google account.
- Additional OAuth providers.
- Storing Google tokens for later API calls.
