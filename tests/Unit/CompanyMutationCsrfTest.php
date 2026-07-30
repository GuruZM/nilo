<?php

test('company and document mutations send an explicit csrf header', function () {
    $page = file_get_contents(__DIR__.'/../../resources/js/pages/Companies/Index.tsx');

    expect($page)
        ->toContain('const getCsrfHeaders = (): Record<string, string> => {')
        ->toContain("'X-CSRF-TOKEN': token,")
        ->toContain("form.post('/companies', {")
        ->toContain('router.post(')
        ->toContain('form.post(`/companies/${company.id}/documents`, {')
        ->toContain('router.delete(`/companies/${company.id}/documents/${document.id}`, {');

    /** Create, update, document upload and document delete — every mutation on the page. */
    expect(substr_count($page, 'headers: getCsrfHeaders(),'))->toBe(4);
});
