<?php

test('app sidebar renders a dedicated configurations section', function () {
    $sidebar = file_get_contents(__DIR__.'/../../resources/js/components/app-sidebar.tsx');
    $navMain = file_get_contents(__DIR__.'/../../resources/js/components/nav-main.tsx');
    $routes = file_get_contents(__DIR__.'/../../routes/web.php');

    expect($sidebar)
        ->toContain('const navigationItems: NavItem[] = [')
        ->toContain("title: 'Configurations'")
        ->toContain("title: 'Templates'")
        ->toContain("title: 'Invoice Templates'")
        ->toContain("title: 'Quotation Templates'")
        ->toContain("href: '/settings/quotation-templates'")
        ->not->toContain('const configurationNavItems: NavItem[] = [');

    expect($navMain)
        ->toContain("label = 'Platform'")
        ->toContain('function NavItemNode({')
        ->toContain('<Collapsible')
        ->toContain('<SidebarMenuSub>')
        ->toContain('<SidebarGroupLabel>{label}</SidebarGroupLabel>');

    expect($routes)
        ->toContain("Route::get('/settings/quotation-templates', [InvoiceTemplateController::class, 'quotationIndex']);")
        ->toContain("Route::get('/settings/quotation-templates/create', [InvoiceTemplateController::class, 'quotationCreate']);")
        ->toContain("Route::post('/settings/quotation-templates', [InvoiceTemplateController::class, 'quotationStore']);");
});

test('settings layout keeps account-only links after moving configuration pages to the app sidebar', function () {
    $settingsLayout = file_get_contents(__DIR__.'/../../resources/js/layouts/settings/layout.tsx');

    expect($settingsLayout)
        ->toContain("title: 'Profile'")
        ->toContain("title: 'Password'")
        ->not->toContain("title: 'Two-Factor Auth'")
        ->not->toContain("title: 'Appearance'")
        ->not->toContain("title: 'Currencies'")
        ->not->toContain("title: 'Invoice Templates'");
});
