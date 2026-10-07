<?php

use App\Enums\TemplatePreset;
use App\Models\Company;
use App\Models\InvoiceTemplate;
use App\Models\ProprietaryPreset;
use App\Models\User;
use App\Services\InvoiceDocumentRenderer;
use Database\Seeders\RolePermissionSeeder;

function presetAdmin(): User
{
    test()->seed(RolePermissionSeeder::class);

    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('super-admin');

    return $user;
}

/**
 * @return array{0: User, 1: Company}
 */
function presetTenant(): array
{
    $user = User::factory()->withSubscription()->create();
    $company = Company::factory()->create(['owner_id' => $user->id]);
    $user->companies()->attach($company->id, ['is_owner' => true, 'status' => 'active']);
    $user->forceFill(['current_company_id' => $company->id])->save();

    return [$user, $company];
}

/**
 * @return array<string, mixed>
 */
function presetTemplatePayload(string $preset): array
{
    return [
        'name' => 'House style',
        'is_default' => true,
        'settings' => ['preset' => $preset],
    ];
}

it('lists every preset with its owner and the companies already using it', function () {
    $owner = Company::factory()->create(['name' => 'Resonant Technologies']);
    ProprietaryPreset::query()->create(['preset' => 'meridian', 'company_id' => $owner->id]);

    $other = Company::factory()->create();
    InvoiceTemplate::query()->create([
        'company_id' => $other->id,
        'type' => 'invoice',
        'name' => 'Borrowed',
        'settings' => ['preset' => 'meridian'],
    ]);

    $this->actingAs(presetAdmin())
        ->get(route('admin.templates.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('admin/templates/index')
            ->has('presets', count(TemplatePreset::cases()))
            ->where('presets.1.id', 'meridian')
            ->where('presets.1.owner.id', $owner->id)
            ->where('presets.1.owner.name', 'Resonant Technologies')
            ->where('presets.1.other_companies_using', 1)
            ->where('presets.0.owner', null)
            ->has('companies', 2)
        );
});

it('makes a preset proprietary to the company the admin picks', function () {
    $company = Company::factory()->create();

    $this->actingAs(presetAdmin())
        ->put(route('admin.templates.update', 'meridian'), ['company_id' => $company->id])
        ->assertRedirect(route('admin.templates.index'));

    expect(ProprietaryPreset::query()->sole())
        ->preset->toBe(TemplatePreset::Meridian)
        ->company_id->toBe($company->id);
});

it('hands a proprietary preset over to a different company', function () {
    [$first, $second] = Company::factory()->count(2)->create();
    ProprietaryPreset::query()->create(['preset' => 'meridian', 'company_id' => $first->id]);

    $this->actingAs(presetAdmin())
        ->put(route('admin.templates.update', 'meridian'), ['company_id' => $second->id])
        ->assertRedirect();

    expect(ProprietaryPreset::query()->sole()->company_id)->toBe($second->id);
});

it('releases a preset back to everyone', function () {
    $company = Company::factory()->create();
    ProprietaryPreset::query()->create(['preset' => 'meridian', 'company_id' => $company->id]);

    $this->actingAs(presetAdmin())
        ->put(route('admin.templates.update', 'meridian'), ['company_id' => null])
        ->assertRedirect();

    expect(ProprietaryPreset::query()->count())->toBe(0);
});

it('refuses a company that does not exist', function () {
    $this->actingAs(presetAdmin())
        ->put(route('admin.templates.update', 'meridian'), ['company_id' => 999])
        ->assertSessionHasErrors('company_id');

    expect(ProprietaryPreset::query()->count())->toBe(0);
});

it('keeps the fallback preset open to everyone', function () {
    $company = Company::factory()->create();

    $this->actingAs(presetAdmin())
        ->put(route('admin.templates.update', 'wave_premium'), ['company_id' => $company->id])
        ->assertSessionHasErrors('company_id');

    expect(ProprietaryPreset::query()->count())->toBe(0);
});

it('404s on a preset that is not in the catalogue', function () {
    $this->actingAs(presetAdmin())
        ->put('/admin/templates/made_up', ['company_id' => null])
        ->assertNotFound();
});

it('keeps preset ownership away from non-admins', function () {
    [$user, $company] = presetTenant();

    $this->actingAs($user)->get(route('admin.templates.index'))->assertForbidden();
    $this->actingAs($user)
        ->put(route('admin.templates.update', 'meridian'), ['company_id' => $company->id])
        ->assertForbidden();

    expect(ProprietaryPreset::query()->count())->toBe(0);
});

it('offers the builder only the presets the company may use', function () {
    [$user] = presetTenant();
    ProprietaryPreset::query()->create(['preset' => 'meridian', 'company_id' => Company::factory()->create()->id]);

    $this->actingAs($user)
        ->get('/settings/invoice-templates/create')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('settings/invoicetemplates/builder')
            ->where('presets', [
                ['id' => 'wave_premium', 'exclusive' => false],
                ['id' => 'modern_minimal', 'exclusive' => false],
                ['id' => 'classic_business', 'exclusive' => false],
                ['id' => 'bold_header', 'exclusive' => false],
            ])
        );
});

it('marks a proprietary preset as exclusive in its owner\'s builder', function () {
    [$user, $company] = presetTenant();
    ProprietaryPreset::query()->create(['preset' => 'meridian', 'company_id' => $company->id]);

    $this->actingAs($user)
        ->get('/settings/quotation-templates/create')
        ->assertInertia(fn ($page) => $page
            ->has('presets', count(TemplatePreset::cases()))
            ->where('presets.1', ['id' => 'meridian', 'exclusive' => true])
        );
});

it('refuses to save a template on another company\'s proprietary preset', function (string $path) {
    [$user, $company] = presetTenant();
    ProprietaryPreset::query()->create(['preset' => 'meridian', 'company_id' => Company::factory()->create()->id]);

    $this->actingAs($user)
        ->post($path, presetTemplatePayload('meridian'))
        ->assertSessionHasErrors(['settings.preset' => 'That design is exclusive to another company.']);

    expect(InvoiceTemplate::query()->where('company_id', $company->id)->count())->toBe(0);
})->with(['/settings/invoice-templates', '/settings/quotation-templates']);

it('refuses to switch an existing template onto another company\'s proprietary preset', function () {
    [$user, $company] = presetTenant();
    ProprietaryPreset::query()->create(['preset' => 'meridian', 'company_id' => Company::factory()->create()->id]);
    $template = InvoiceTemplate::query()->create([
        'company_id' => $company->id,
        'type' => 'invoice',
        'name' => 'House style',
        'is_default' => true,
        'settings' => ['preset' => 'wave_premium'],
    ]);

    $this->actingAs($user)
        ->put("/settings/invoice-templates/{$template->id}", presetTemplatePayload('meridian'))
        ->assertSessionHasErrors('settings.preset');

    expect($template->fresh()->settings['preset'])->toBe('wave_premium');
});

it('lets the owner save a template on its proprietary preset', function () {
    [$user, $company] = presetTenant();
    ProprietaryPreset::query()->create(['preset' => 'meridian', 'company_id' => $company->id]);

    $this->actingAs($user)
        ->post('/settings/invoice-templates', presetTemplatePayload('meridian'))
        ->assertSessionHasNoErrors()
        ->assertRedirect('/settings/invoice-templates');

    expect(InvoiceTemplate::query()->where('company_id', $company->id)->sole()->settings['preset'])->toBe('meridian');
});

it('leaves unowned presets open to every company', function () {
    [$user, $company] = presetTenant();
    ProprietaryPreset::query()->create(['preset' => 'meridian', 'company_id' => Company::factory()->create()->id]);

    $this->actingAs($user)
        ->post('/settings/invoice-templates', presetTemplatePayload('bold_header'))
        ->assertSessionHasNoErrors();

    expect(InvoiceTemplate::query()->where('company_id', $company->id)->sole()->settings['preset'])->toBe('bold_header');
});

it('refuses a preset that is not in the catalogue', function () {
    [$user] = presetTenant();

    $this->actingAs($user)
        ->post('/settings/invoice-templates', presetTemplatePayload('made_up'))
        ->assertSessionHasErrors('settings.preset');
});

it('prints a template on someone else\'s proprietary preset as wave premium', function () {
    $owner = Company::factory()->create();
    $other = Company::factory()->create();
    ProprietaryPreset::query()->create(['preset' => 'meridian', 'company_id' => $owner->id]);

    $renderer = app(InvoiceDocumentRenderer::class);
    $settingsFor = fn (Company $company) => $renderer->normalizedSettings(InvoiceTemplate::query()->create([
        'company_id' => $company->id,
        'type' => 'invoice',
        'name' => 'Meridian',
        'settings' => ['preset' => 'meridian'],
    ]));

    expect($settingsFor($other)['preset'])->toBe('wave_premium')
        ->and($settingsFor($owner)['preset'])->toBe('meridian');
});

it('frees a proprietary preset when its owner is deleted', function () {
    $company = Company::factory()->create();
    ProprietaryPreset::query()->create(['preset' => 'meridian', 'company_id' => $company->id]);

    $company->delete();

    expect(ProprietaryPreset::query()->count())->toBe(0);
});
