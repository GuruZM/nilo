<?php

test('xsrf token cookie is not excluded from encryption', function () {
    $bootstrapApp = file_get_contents(__DIR__.'/../../bootstrap/app.php');

    expect($bootstrapApp)
        ->toContain("\$middleware->encryptCookies(except: ['appearance', 'sidebar_state']);")
        ->not->toContain("'XSRF-TOKEN'");
});
