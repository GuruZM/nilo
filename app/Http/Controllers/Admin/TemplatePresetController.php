<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TemplatePreset;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateTemplatePresetOwnerRequest;
use App\Models\Company;
use App\Models\InvoiceTemplate;
use App\Models\ProprietaryPreset;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TemplatePresetController extends Controller
{
    public function index(): Response
    {
        $owners = ProprietaryPreset::query()
            ->with('company:id,name')
            ->get()
            ->keyBy(fn (ProprietaryPreset $ownership): string => $ownership->preset->value);

        $presets = collect(TemplatePreset::cases())->map(function (TemplatePreset $preset) use ($owners): array {
            $owner = $owners->get($preset->value)?->company;

            return [
                'id' => $preset->value,
                'name' => $preset->label(),
                'is_fallback' => $preset === TemplatePreset::fallback(),
                'owner' => $owner?->only(['id', 'name']),
                'other_companies_using' => $this->companiesUsing($preset, $owner?->id),
            ];
        });

        return Inertia::render('admin/templates/index', [
            'presets' => $presets,
            'fallback' => TemplatePreset::fallback()->label(),
            'companies' => Company::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateTemplatePresetOwnerRequest $request, TemplatePreset $preset): RedirectResponse
    {
        $companyId = $request->validated('company_id');

        if ($companyId === null) {
            ProprietaryPreset::query()->where('preset', $preset->value)->delete();

            return redirect()
                ->route('admin.templates.index')
                ->with('success', "{$preset->label()} is now available to every company.");
        }

        $ownership = ProprietaryPreset::query()->updateOrCreate(
            ['preset' => $preset->value],
            ['company_id' => $companyId],
        );

        return redirect()
            ->route('admin.templates.index')
            ->with('success', "{$preset->label()} now belongs to {$ownership->company->name}.");
    }

    /**
     * Companies besides the owner with a template on this preset. Once the
     * preset is proprietary, theirs print on the fallback instead.
     */
    private function companiesUsing(TemplatePreset $preset, ?int $ownerId): int
    {
        return InvoiceTemplate::query()
            ->where('settings->preset', $preset->value)
            ->when($ownerId, fn ($query) => $query->where('company_id', '!=', $ownerId))
            ->distinct()
            ->count('company_id');
    }
}
