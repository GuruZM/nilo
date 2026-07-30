<?php

/**
 * The passkey login button shares a hook with the autofill ceremony, and that
 * ceremony waits for as long as the page is open. These assertions guard the
 * separation that keeps the button pressable; see the component's own comment.
 */
test('the passkey login button tracks its own press rather than the shared hook state', function () {
    $button = file_get_contents(__DIR__.'/../../resources/js/components/passkey-login-button.tsx');

    expect($button)
        // The hook's `isLoading` is deliberately not destructured: it also goes
        // true for the autofill ceremony armed on mount.
        ->toContain('const { verify, error, isSupported } = usePasskeyVerify({')
        ->toContain('const [verifying, setVerifying] = useState<boolean>(false);')
        // Nothing rendered may read the hook's flag.
        ->not->toMatch('/\{\s*isLoading/')
        ->not->toMatch('/isLoading\s*(\?|&&)/');

    expect($button)
        ->toContain('disabled={verifying}')
        ->toContain('aria-busy={verifying}')
        ->toContain('{verifying ? (')
        ->toContain('Waiting for your device…');
});

test('the login page anchors the passkey picker to the email field', function () {
    $login = file_get_contents(__DIR__.'/../../resources/js/pages/auth/login.tsx');

    expect($login)
        ->toContain('<PasskeyLoginButton autofill')
        // Conditional mediation has nothing to hang its dropdown off without
        // the `webauthn` token, so autofill silently never fires.
        ->toContain('autoComplete="email webauthn"');
});
