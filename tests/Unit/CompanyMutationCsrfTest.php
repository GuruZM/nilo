<?php

test('company create and update submissions send an explicit csrf header', function () {
    $page = file_get_contents(__DIR__.'/../../resources/js/pages/Companies/Index.tsx');

    expect($page)
        ->toContain('const getCsrfHeaders = (): Record<string, string> => {')
        ->toContain("'X-CSRF-TOKEN': token,")
        ->toContain("form.post('/companies', {")
        ->toContain('router.post(');

    expect(substr_count($page, 'headers: getCsrfHeaders(),'))->toBe(2);
});
