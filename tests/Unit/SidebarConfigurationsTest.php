<?php

test('app sidebar renders main, settings, and admin navigation sections', function () {
    $sidebar = file_get_contents(__DIR__.'/../../resources/js/components/app-sidebar.tsx');
    $navMain = file_get_contents(__DIR__.'/../../resources/js/components/nav-main.tsx');
    $routes = file_get_contents(__DIR__.'/../../routes/web.php');

    expect($sidebar)
        ->toContain('const mainNavItems: NavItem[] = [')
        ->toContain('const settingsNavItems: NavItem[] = [')
        ->toContain('const adminNavItems: NavItem[] = [')
        ->toContain("title: 'Templates'")
        ->toContain("title: 'Invoice Templates'")
        ->toContain("title: 'Quotation Templates'")
        ->toContain("href: '/settings/quotation-templates'")
        // The admin rail is only shown on /admin pages, so it reads "Dashboard".
        ->toContain("title: 'Dashboard'")
        ->not->toContain("title: 'Admin Dashboard'")
        ->toContain("title: 'Users'")
        ->toContain("title: 'Plans'")
        ->toContain("href: '/admin/plans'")
        ->toContain("title: 'Payments'")
        ->toContain("title: 'Inquiries'")
        ->toContain('<NavMain items={mainNavItems} label="Menu" />')
        ->toMatch('/<NavMain\s+items=\{settingsNavItems\}\s+label="Settings"/')
        ->toContain('<NavMain items={adminNavItems} label="Admin" />');

    expect($sidebar)
        // Menu and Settings are suppressed while viewing an /admin page.
        ->toContain('{!inAdminMode && (')
        ->toContain('const isAdminPath = (url: string): boolean => {')
        ->toContain("path === '/admin' || path.startsWith('/admin/')")
        ->toContain('const inAdminMode = isAdmin && isAdminPath(page.url);')
        // Admin mode is per-page, so the tenant rail returns outside /admin.
        ->toContain("const canReturnToApp = subscription?.status === 'active';")
        ->toContain('<NavMain items={backToAppNavItems} label="App" />');

    expect($navMain)
        ->toContain('function NavItemNode({')
        ->toContain('<Collapsible')
        ->toContain('<CollapsibleContent')
        ->toContain('{label}');

    expect($routes)
        ->toContain("Route::get('/settings/quotation-templates', [InvoiceTemplateController::class, 'quotationIndex']);")
        ->toContain("Route::get('/settings/quotation-templates/create', [InvoiceTemplateController::class, 'quotationCreate']);")
        ->toContain("Route::post('/settings/quotation-templates', [InvoiceTemplateController::class, 'quotationStore']);");
});

test('company and currency switchers live in the left rail, detached from the nav links', function () {
    $sidebar = file_get_contents(__DIR__.'/../../resources/js/components/app-sidebar.tsx');
    $switchers = file_get_contents(__DIR__.'/../../resources/js/components/workspace-switchers.tsx');

    expect($sidebar)
        ->toContain("import { WorkspaceSwitchers } from '@/components/workspace-switchers';")
        ->toContain('<WorkspaceSwitchers')
        ->toMatch('/\{!inAdminMode && \(\s*<WorkspaceSwitchers/')
        // The rail is a transparent column; each block carries its own panel.
        ->toContain('[&>[data-sidebar=sidebar]]:bg-transparent')
        ->toContain('const panelClass = cn(');

    expect($switchers)
        ->toContain("'/companies/switch'")
        ->toContain("'/currencies/switch'")
        // Hand-rolled dropdowns rather than the shadcn menu primitives.
        ->toContain("import { FloatingMenu, menuRowClass } from '@/components/floating-menu';")
        ->not->toContain('@/components/ui/dropdown-menu')
        ->not->toContain('@/components/ui/button');
});

test('the top bar is a detached card holding only navigation context and the user menu', function () {
    $header = file_get_contents(__DIR__.'/../../resources/js/components/app-sidebar-header.tsx');
    $userMenu = file_get_contents(__DIR__.'/../../resources/js/components/user-menu.tsx');

    expect($header)
        ->toContain('<Breadcrumbs breadcrumbs={breadcrumbs} />')
        ->toContain('<UserMenu user={authUser} />')
        // The switchers moved to the left rail.
        ->not->toContain('/companies/switch')
        ->not->toContain('/currencies/switch')
        ->not->toContain('@/components/ui/dropdown-menu');

    expect($userMenu)
        ->toContain('<FloatingMenu')
        ->toContain('data-test="logout-button"')
        ->toContain('href={edit()}')
        ->not->toContain('@/components/ui/dropdown-menu');
});

test('settings layout gathers the account and sign-in links while configuration stays in the app sidebar', function () {
    $settingsLayout = file_get_contents(__DIR__.'/../../resources/js/layouts/settings/layout.tsx');
    $sidebar = file_get_contents(__DIR__.'/../../resources/js/components/app-sidebar.tsx');

    expect($settingsLayout)
        ->toContain("title: 'Profile'")
        ->toContain("title: 'Password'")
        // Both sign-in methods now live under the profile section.
        ->toContain("title: 'Two-factor auth'")
        ->toContain("title: 'Passkeys'")
        ->toContain("href: '/settings/passkeys'")
        ->toContain("import { show as showTwoFactor } from '@/routes/two-factor';")
        ->not->toContain("title: 'Appearance'")
        ->not->toContain("title: 'Currencies'")
        ->not->toContain("title: 'Invoice Templates'");

    expect($settingsLayout)
        // The active row is resolved from the Inertia page so it survives SSR.
        ->toContain("const currentPath = usePage().url.split('?')[0];")
        ->not->toContain('window.location.pathname');

    expect($sidebar)
        // The rail links to the hub instead of repeating what it contains.
        ->toContain("title: 'Profile & Security'")
        ->toContain('href: editProfile()')
        ->not->toContain("title: 'Two-Factor Auth'")
        ->not->toContain("title: 'Passkeys'");
});

test('the account settings pages are built from the dashboard panel primitives', function () {
    $pages = [
        'two-factor' => file_get_contents(__DIR__.'/../../resources/js/pages/settings/two-factor.tsx'),
        'passkeys' => file_get_contents(__DIR__.'/../../resources/js/pages/settings/passkeys.tsx'),
        'profile' => file_get_contents(__DIR__.'/../../resources/js/pages/settings/profile.tsx'),
        'password' => file_get_contents(__DIR__.'/../../resources/js/pages/settings/password.tsx'),
    ];

    foreach ($pages as $name => $page) {
        expect($page)
            ->toContain("from '@/components/dashboard/primitives'")
            ->toContain('<Panel>')
            ->toContain('<PanelHeader')
            // Borderless panels replace the bordered shadcn card and button.
            ->not->toContain('@/components/ui/card')
            ->not->toContain('@/components/ui/button')
            ->not->toContain('@/components/heading-small');
    }

    expect($pages['two-factor'])
        ->toContain('<StatePill enabled={twoFactorEnabled} />')
        ->toContain('Enable 2FA')
        ->toContain('Turn off');

    expect($pages['passkeys'])
        ->toContain('data-test="add-passkey-button"')
        ->toContain('<EmptyState');

    // The recovery codes block sits inside the 2FA panel, so it is a tinted
    // tile rather than a second card.
    expect(file_get_contents(__DIR__.'/../../resources/js/components/two-factor-recovery-codes.tsx'))
        ->toContain('<SoftTile')
        ->not->toContain('@/components/ui/card');
});
