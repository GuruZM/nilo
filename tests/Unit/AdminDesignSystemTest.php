<?php

/**
 * The plans page set the look for the admin area: borderless `Panel` surfaces,
 * pill actions, and the shared status/table primitives. These assertions catch
 * a drift back to hard-coded slate/blue palettes or bordered shadcn controls.
 */
function adminPageSource(string $path): string
{
    return file_get_contents(__DIR__.'/../../resources/js/pages/admin/'.$path);
}

function subscriptionPageSource(string $path): string
{
    return file_get_contents(__DIR__.'/../../resources/js/pages/subscription/'.$path);
}

const ADMIN_PAGES = [
    'dashboard.tsx',
    'plans/index.tsx',
    'users/index.tsx',
    'users/show.tsx',
    'payments/index.tsx',
    'payments/show.tsx',
    'inquiries/index.tsx',
];

const SUBSCRIPTION_PAGES = [
    'current.tsx',
    'select.tsx',
    'payment.tsx',
    'payment-status.tsx',
    'enterprise.tsx',
];

it('builds every admin page on the shared dashboard primitives', function (string $path) {
    expect(adminPageSource($path))
        ->toContain("from '@/components/dashboard/primitives'")
        ->not->toContain("from '@/components/ui/card'")
        ->not->toContain("from '@/components/ui/button'")
        ->not->toContain("from '@/components/ui/input'");
})->with(ADMIN_PAGES);

it('builds every subscription page on the shared dashboard primitives', function (string $path) {
    expect(subscriptionPageSource($path))
        ->toContain("from '@/components/dashboard/primitives'")
        ->not->toContain("from '@/components/ui/card'")
        ->not->toContain("from '@/components/ui/button'")
        ->not->toContain("from '@/components/ui/input'")
        ->not->toContain("from '@/components/ui/label'");
})->with(SUBSCRIPTION_PAGES);

/**
 * The design system carries light and dark through semantic tokens. A literal
 * `slate-`/`blue-` utility is the tell that a page is painting its own palette.
 */
it('keeps hard-coded slate and blue palettes out of the admin and subscription pages', function (string $source) {
    expect($source)
        ->not->toMatch('/\b(?:bg|text|border|divide|ring)-slate-\d/')
        ->not->toMatch('/\b(?:bg|text|border|divide|ring)-blue-\d/')
        // `dark:bg-white/5` is the design system's tint; an opaque `bg-white`
        // is a panel painting itself instead of using `bg-card`.
        ->not->toMatch('/\bbg-white(?![\/-])/')
        ->not->toContain('dark:bg-slate');
})->with(array_merge(
    array_map(fn (string $path): string => adminPageSource($path), ADMIN_PAGES),
    array_map(fn (string $path): string => subscriptionPageSource($path), SUBSCRIPTION_PAGES),
));

it('gives every admin list page the same panel shell and page body as the plans page', function (string $path) {
    expect(adminPageSource($path))
        ->toContain('<Panel>')
        ->toContain('<PanelHeader')
        ->toContain('flex w-full flex-col gap-4 py-6');
})->with([
    'plans/index.tsx',
    'users/index.tsx',
    'payments/index.tsx',
    'inquiries/index.tsx',
]);

it('renders admin tables as tinted row tiles rather than ruled lines', function (string $path) {
    expect(adminPageSource($path))
        ->toContain('tableCellClass')
        ->toContain('border-separate border-spacing-y-1.5')
        ->toContain('rounded-l-2xl')
        ->toContain('rounded-r-2xl');
})->with(['users/index.tsx', 'users/show.tsx', 'payments/index.tsx']);

it('paginates the admin lists through the shared pagination primitive', function (string $path) {
    expect(adminPageSource($path))
        ->toContain('<Pagination links=')
        // The hand-rolled link loop used raw utility colours per state.
        ->not->toContain('links.map');
})->with(['users/index.tsx', 'payments/index.tsx', 'inquiries/index.tsx']);

it('routes every admin status through the shared status pill', function () {
    $primitives = file_get_contents(
        __DIR__.'/../../resources/js/components/dashboard/primitives.tsx'
    );

    expect($primitives)
        ->toContain('export function EmptyState')
        ->toContain('export function Pagination')
        ->toContain('export const tableCellClass')
        // Underscored statuses such as `pending_payment` must read as words.
        ->toContain("status.replace(/_/g, ' ')");

    foreach (['confirmed', 'rejected', 'active', 'cancelled', 'handled'] as $status) {
        expect($primitives)->toContain($status.': {');
    }

    expect(adminPageSource('payments/index.tsx'))
        ->toContain('<StatusPill');
    expect(adminPageSource('users/index.tsx'))
        ->toContain('<StatusPill');
});

it('seats the standalone subscription pages on the canvas through one shared shell', function (string $path) {
    expect(subscriptionPageSource($path))
        ->toContain("from '@/components/subscription/gate-shell'")
        ->toContain('<GateShell')
        // The old gradient backdrop is what set these pages apart.
        ->not->toContain('bg-gradient-to-br');
})->with(['select.tsx', 'payment.tsx', 'payment-status.tsx', 'enterprise.tsx']);

it('puts the grey canvas behind the subscription gate shell', function () {
    expect(file_get_contents(
        __DIR__.'/../../resources/js/components/subscription/gate-shell.tsx'
    ))->toContain('bg-canvas');
});

/**
 * `useForm().post()` sends the form's own state; a `data` override is silently
 * ignored, which previously posted `plan_id: 0` for every plan chosen.
 */
it('posts the chosen plan id from the plan selection page', function () {
    expect(subscriptionPageSource('select.tsx'))
        ->toContain('router.post(')
        ->toContain('plan_id: plan.id')
        ->not->toContain('data: { plan_id: plan.id }');
});

it('drives the popular plan badge off the admin-managed flag', function () {
    expect(subscriptionPageSource('select.tsx'))
        ->toContain('plan.is_popular')
        ->not->toContain("plan.slug === 'standard'");
});
