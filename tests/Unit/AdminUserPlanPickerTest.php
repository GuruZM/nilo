<?php

test('the plan picker groups complimentary plans under their own heading', function () {
    $page = file_get_contents(__DIR__.'/../../resources/js/pages/admin/users/show.tsx');

    expect($page)
        ->toContain('const listedPlans = plans.filter((plan) => plan.is_public);')
        ->toContain('const complimentaryPlans = plans.filter((plan) => !plan.is_public);')
        ->toContain('<optgroup label="Public plans">')
        ->toContain('<optgroup label="Complimentary — admin assignment only">')
        // Every active plan still reaches the picker; grouping is presentation
        // only, so neither list may be filtered out of the markup.
        ->toContain('{listedPlans.map((plan) => (')
        ->toContain('{complimentaryPlans.map(');
});
