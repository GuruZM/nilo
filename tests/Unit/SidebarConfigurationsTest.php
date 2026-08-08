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
        // Settings is a collapsible row now, so it labels itself rather than
        // heading a group of its own.
        ->toContain('<NavMain items={settingsNavItems} />')
        ->not->toContain('label="Settings"')
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

test('every v1 document type is reachable from the Documents group and suppliers sit beside clients', function () {
    $sidebar = file_get_contents(__DIR__.'/../../resources/js/components/app-sidebar.tsx');

    expect($sidebar)
        ->toContain("title: 'Credit Notes'")
        ->toContain("href: '/credit-notes'")
        ->toContain("title: 'Delivery Notes'")
        ->toContain("href: '/delivery-notes'")
        ->toContain("title: 'Purchase Orders'")
        ->toContain("href: '/purchase-orders'")
        ->toContain("title: 'Suppliers'")
        ->toContain("href: '/suppliers'")
        // The icons the entries render with have to be imported to compile.
        ->toContain('FileMinus,')
        ->toContain('Truck,')
        ->toContain('ShoppingCart,')
        ->toContain('Factory,');

    // Substring assertions cannot express nesting, so slice the Documents group
    // out of the file and assert against that region alone. The group runs from
    // its own title to the next top-level entry, Clients.
    $documentsGroup = documentsNavGroup($sidebar);

    expect($documentsGroup)
        ->toContain("href: '/invoices'")
        ->toContain("href: '/quotations'")
        ->toContain("href: '/credit-notes'")
        ->toContain("href: '/delivery-notes'")
        // Purchase orders stay in the document register rather than splitting
        // off into a purchasing section - see the group's own comment.
        ->toContain("href: '/purchase-orders'")
        // Clients and suppliers are entity lists, not documents. Both stay at
        // the top level so neither costs an extra click the other does not.
        ->not->toContain("href: '/suppliers'")
        ->not->toContain("href: '/clients'");

    // Suppliers immediately follows clients, so the two lists read as a pair.
    expect($sidebar)->toMatch(
        '/href: \'\/clients\',.*?href: \'\/suppliers\',/s'
    );
});

test('settings collapse behind a single dropdown row instead of a permanent block', function () {
    $sidebar = file_get_contents(__DIR__.'/../../resources/js/components/app-sidebar.tsx');

    expect($sidebar)
        // The icon the row renders with has to be imported to compile.
        ->toContain('Settings,')
        // One entry, and everything else hangs off its `items`. `href: '#'` so
        // the row only ever toggles - it has no page of its own to visit.
        ->toMatch(
            '/const settingsNavItems: NavItem\[\] = \[\s*\{\s*'
            ."title: 'Settings',\s*href: '#',\s*icon: Settings,\s*items: \[/"
        );

    $settingsGroup = settingsNavGroup($sidebar);

    expect($settingsGroup)
        ->toContain("href: '/settings/invoice-templates'")
        ->toContain("href: '/settings/quotation-templates'")
        ->toContain("href: '/settings/currencies'")
        ->toContain("href: '/subscription'")
        ->toContain('href: editAppearance()')
        ->toContain('href: editProfile()');

    // Landing on a settings page has to open the fold, or the active row would
    // be hidden inside a closed group.
    expect(file_get_contents(__DIR__.'/../../resources/js/components/nav-main.tsx'))
        ->toContain('defaultOpen={isActive}');
});

test('the dropdown animates open and closed and respects reduced motion', function () {
    $navMain = file_get_contents(__DIR__.'/../../resources/js/components/nav-main.tsx');
    $css = file_get_contents(__DIR__.'/../../resources/css/app.css');

    expect($navMain)
        ->toContain('data-[state=open]:animate-collapsible-down')
        ->toContain('data-[state=closed]:animate-collapsible-up')
        // Without this the rows spill out of the box the height keyframe draws.
        ->toContain("'overflow-hidden',")
        ->toContain('motion-reduce:animate-none')
        // The panel is the only thing that moves. Rows do not stagger, slide,
        // or fade in on top of it - see the keyframe comment in app.css.
        ->not->toContain('animate-in')
        ->not->toContain('slide-in-from')
        ->not->toContain('animationDelay');

    expect($css)
        ->toContain('--animate-collapsible-down:')
        ->toContain('--animate-collapsible-up:')
        ->toContain('@keyframes collapsible-down {')
        ->toContain('@keyframes collapsible-up {')
        // Radix publishes the measured height; the keyframe has to read it
        // rather than animate to `auto`, which does not interpolate.
        ->toContain('height: var(--radix-collapsible-content-height);');

    // Short enough to read as the panel simply being there, and the fold never
    // fades from nothing. Both are what keeps the movement quiet.
    preg_match('/--animate-collapsible-down: collapsible-down (\d+)ms/', $css, $down);
    preg_match('/--animate-collapsible-up: collapsible-up (\d+)ms/', $css, $up);

    expect((int) $down[1])->toBeLessThanOrEqual(150);
    expect((int) $up[1])->toBeLessThanOrEqual((int) $down[1]);
    expect($css)->toContain('opacity: 0.85;')->not->toContain('opacity: 0;');
});

/** The `settingsNavItems` array, from its declaration to the next one. */
function settingsNavGroup(string $sidebar): string
{
    $start = strpos($sidebar, 'const settingsNavItems');
    $end = strpos($sidebar, 'const adminNavItems', $start);

    expect($start)->not->toBeFalse();
    expect($end)->not->toBeFalse();

    return substr($sidebar, $start, $end - $start);
}

/** The `Documents` entry of `mainNavItems`, from its title to the next entry. */
function documentsNavGroup(string $sidebar): string
{
    $start = strpos($sidebar, "title: 'Documents'");
    $end = strpos($sidebar, "title: 'Clients'", $start);

    expect($start)->not->toBeFalse();
    expect($end)->not->toBeFalse();

    return substr($sidebar, $start, $end - $start);
}

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
