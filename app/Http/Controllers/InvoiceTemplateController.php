<?php

namespace App\Http\Controllers;

use App\Models\InvoiceTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class InvoiceTemplateController extends Controller
{
    protected function companyId(Request $request): int
    {
        $user = $request->user();

        return (int) ($user?->current_company_id ?? 0);
    }

    public function index(Request $request)
    {
        return $this->showIndex($request, 'invoice');
    }

    public function quotationIndex(Request $request)
    {
        return $this->showIndex($request, 'quotation');
    }

    public function create(Request $request)
    {
        return $this->showBuilder($request, 'invoice', 'create');
    }

    public function quotationCreate(Request $request)
    {
        return $this->showBuilder($request, 'quotation', 'create');
    }

    public function edit(Request $request, InvoiceTemplate $template)
    {
        return $this->showBuilder($request, 'invoice', 'edit', $template);
    }

    public function quotationEdit(Request $request, InvoiceTemplate $template)
    {
        return $this->showBuilder($request, 'quotation', 'edit', $template);
    }

    public function store(Request $request)
    {
        return $this->storeTemplate($request, 'invoice');
    }

    public function quotationStore(Request $request)
    {
        return $this->storeTemplate($request, 'quotation');
    }

    public function update(Request $request, InvoiceTemplate $template)
    {
        return $this->updateTemplate($request, 'invoice', $template);
    }

    public function quotationUpdate(Request $request, InvoiceTemplate $template)
    {
        return $this->updateTemplate($request, 'quotation', $template);
    }

    public function makeDefault(Request $request, InvoiceTemplate $template)
    {
        return $this->makeDefaultTemplate($request, 'invoice', $template);
    }

    public function quotationMakeDefault(Request $request, InvoiceTemplate $template)
    {
        return $this->makeDefaultTemplate($request, 'quotation', $template);
    }

    private function showIndex(Request $request, string $templateType)
    {
        $companyId = $this->companyId($request);
        $module = $this->templateModule($templateType);

        if (! $companyId) {
            return redirect('/companies')->with('error', 'Select a company first.');
        }

        $templates = InvoiceTemplate::query()
            ->where('company_id', $companyId)
            ->where('type', $templateType)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'is_default',
                'type',
                'created_at',
                'updated_at',
            ]);

        return Inertia::render('settings/invoicetemplates/index', [
            'templates' => $templates,
            'module' => $module,
        ]);
    }

    private function showBuilder(
        Request $request,
        string $templateType,
        string $mode,
        ?InvoiceTemplate $template = null,
    ) {
        $companyId = $this->companyId($request);

        if (! $companyId) {
            return redirect('/companies')->with('error', 'Select a company first.');
        }

        if ($mode === 'edit' && (! $template || ! $this->matchesTemplateType($template, $companyId, $templateType))) {
            throw ValidationException::withMessages([
                'template' => 'Unauthorized template access.',
            ]);
        }

        return Inertia::render('settings/invoicetemplates/builder', [
            'mode' => $mode,
            'template' => $template?->only([
                'id',
                'name',
                'type',
                'is_default',
                'settings',
                'terms_html',
                'footer_html',
            ]),
            'module' => $this->templateModule($templateType),
        ]);
    }

    private function storeTemplate(Request $request, string $templateType)
    {
        $companyId = $this->companyId($request);
        $module = $this->templateModule($templateType);

        if (! $companyId) {
            return back()->with('error', 'Select a company first.');
        }

        try {
            $data = $this->validateTemplateRequest($request);
            $settings = $this->normalizedSettings((array) ($data['settings'] ?? []));
            $isDefault = (bool) ($data['is_default'] ?? false);

            DB::transaction(function () use ($companyId, $data, $settings, $isDefault, $templateType) {
                if ($isDefault) {
                    InvoiceTemplate::query()
                        ->where('company_id', $companyId)
                        ->where('type', $templateType)
                        ->update(['is_default' => false]);
                }

                $template = InvoiceTemplate::create([
                    'company_id' => $companyId,
                    'type' => $templateType,
                    'name' => $data['name'],
                    'is_default' => $isDefault,
                    'settings' => $settings,
                    'terms_html' => $data['terms_html'] ?? null,
                    'footer_html' => $data['footer_html'] ?? null,
                ]);

                $count = InvoiceTemplate::query()
                    ->where('company_id', $companyId)
                    ->where('type', $templateType)
                    ->count();

                if ($count === 1 && ! $template->is_default) {
                    $template->forceFill(['is_default' => true])->save();
                }
            });

            return redirect($module['basePath'])
                ->with('success', $module['singularTitle'].' created.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Document template store failed', [
                'company_id' => $companyId,
                'template_type' => $templateType,
                'error' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'template' => 'Failed to create template. Please try again.',
            ]);
        }
    }

    private function updateTemplate(Request $request, string $templateType, InvoiceTemplate $template)
    {
        $companyId = $this->companyId($request);
        $module = $this->templateModule($templateType);

        if (! $companyId) {
            return back()->with('error', 'Select a company first.');
        }

        if (! $this->matchesTemplateType($template, $companyId, $templateType)) {
            abort(404);
        }

        try {
            $data = $this->validateTemplateRequest($request);
            $existing = is_array($template->settings) ? $template->settings : [];
            $incoming = (array) ($data['settings'] ?? []);
            $settings = $this->normalizedSettings($incoming, $existing);
            $isDefault = array_key_exists('is_default', $data)
                ? (bool) $data['is_default']
                : (bool) $template->is_default;

            DB::transaction(function () use ($companyId, $data, $settings, $isDefault, $templateType, $template) {
                if ($isDefault) {
                    InvoiceTemplate::query()
                        ->where('company_id', $companyId)
                        ->where('type', $templateType)
                        ->where('id', '!=', $template->id)
                        ->update(['is_default' => false]);
                }

                $template->update([
                    'name' => $data['name'],
                    'is_default' => $isDefault,
                    'settings' => $settings,
                    'terms_html' => $data['terms_html'] ?? null,
                    'footer_html' => $data['footer_html'] ?? null,
                ]);

                $hasDefault = InvoiceTemplate::query()
                    ->where('company_id', $companyId)
                    ->where('type', $templateType)
                    ->where('is_default', true)
                    ->exists();

                if (! $hasDefault) {
                    InvoiceTemplate::query()
                        ->where('company_id', $companyId)
                        ->where('type', $templateType)
                        ->orderBy('id')
                        ->limit(1)
                        ->update(['is_default' => true]);
                }
            });

            return redirect($module['basePath'])
                ->with('success', $module['singularTitle'].' updated.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Document template update failed', [
                'company_id' => $companyId,
                'template_id' => $template->id,
                'template_type' => $templateType,
                'error' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'template' => 'Failed to update template. Please try again.',
            ]);
        }
    }

    private function makeDefaultTemplate(Request $request, string $templateType, InvoiceTemplate $template)
    {
        $companyId = $this->companyId($request);
        $module = $this->templateModule($templateType);

        if (! $companyId) {
            return back()->with('error', 'Select a company first.');
        }

        if (! $this->matchesTemplateType($template, $companyId, $templateType)) {
            throw ValidationException::withMessages([
                'template' => 'Unauthorized template access.',
            ]);
        }

        DB::transaction(function () use ($companyId, $templateType, $template) {
            InvoiceTemplate::query()
                ->where('company_id', $companyId)
                ->where('type', $templateType)
                ->update(['is_default' => false]);

            $template->forceFill(['is_default' => true])->save();
        });

        return back()->with('success', 'Default '.$module['singularTitle'].' updated.');
    }

    private function validateTemplateRequest(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'is_default' => ['sometimes', 'boolean'],
            'settings' => ['nullable', 'array'],
            'terms_html' => ['nullable', 'string'],
            'footer_html' => ['nullable', 'string'],
        ]);
    }

    private function normalizedSettings(array $incomingSettings, array $existingSettings = []): array
    {
        $defaults = $this->templateDefaults();

        $settings = array_replace_recursive($defaults, $existingSettings, $incomingSettings);
        $settings['visibility'] = array_merge(
            $defaults['visibility'],
            array_map(fn ($value) => (bool) $value, (array) ($settings['visibility'] ?? []))
        );

        return $settings;
    }

    private function matchesTemplateType(InvoiceTemplate $template, int $companyId, string $templateType): bool
    {
        return (int) $template->company_id === (int) $companyId
            && $template->type === $templateType;
    }

    /**
     * @return array{
     *     type: string,
     *     singularTitle: string,
     *     pluralTitle: string,
     *     basePath: string,
     *     createPath: string
     * }
     */
    private function templateModule(string $templateType): array
    {
        if ($templateType === 'quotation') {
            $basePath = '/settings/quotation-templates';

            return [
                'type' => 'quotation',
                'singularTitle' => 'Quotation template',
                'pluralTitle' => 'Quotation templates',
                'basePath' => $basePath,
                'createPath' => $basePath.'/create',
            ];
        }

        $basePath = '/settings/invoice-templates';

        return [
            'type' => 'invoice',
            'singularTitle' => 'Invoice template',
            'pluralTitle' => 'Invoice templates',
            'basePath' => $basePath,
            'createPath' => $basePath.'/create',
        ];
    }

    /**
     * @return array{
     *     preset: string,
     *     brand: array{primary: string, accent: string, font: string},
     *     layout: array{header: string, table: string, density: string},
     *     visibility: array{
     *         show_logo: bool,
     *         show_client_email: bool,
     *         show_contact_person: bool,
     *         show_terms: bool,
     *         show_notes: bool,
     *         show_bank_details: bool,
     *         show_signature: bool
     *     }
     * }
     */
    private function templateDefaults(): array
    {
        return [
            'preset' => 'wave_premium',
            'brand' => [
                'primary' => '#111827',
                'accent' => '#F59E0B',
                'font' => 'Inter',
            ],
            'layout' => [
                'header' => 'split',
                'table' => 'striped',
                'density' => 'normal',
            ],
            'visibility' => [
                'show_logo' => true,
                'show_client_email' => true,
                'show_contact_person' => true,
                'show_terms' => true,
                'show_notes' => true,
                'show_bank_details' => false,
                'show_signature' => false,
            ],
        ];
    }
}
