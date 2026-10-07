<?php

namespace App\Http\Controllers;

use App\Enums\TemplatePreset;
use App\Models\InvoiceTemplate;
use App\Models\ProprietaryPreset;
use App\Services\SubscriptionLimitService;
use App\Support\BankDetails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
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
            'presets' => $this->presetOptions($companyId),
        ]);
    }

    private function storeTemplate(Request $request, string $templateType)
    {
        $companyId = $this->companyId($request);
        $module = $this->templateModule($templateType);

        if (! $companyId) {
            return back()->with('error', 'Select a company first.');
        }

        $limiter = new SubscriptionLimitService($request->user());

        if (! $limiter->canCreateTemplate($templateType, $companyId)) {
            return back()->with('error', 'You have reached your template limit. Please upgrade your plan to create more templates.');
        }

        try {
            $data = $this->validateTemplateRequest($request, $companyId);
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
            $data = $this->validateTemplateRequest($request, $companyId);
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

    private function validateTemplateRequest(Request $request, int $companyId): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'is_default' => ['sometimes', 'boolean'],
            'settings' => ['nullable', 'array'],
            'settings.preset' => ['sometimes', 'bail', Rule::enum(TemplatePreset::class), Rule::notIn(ProprietaryPreset::lockedFor($companyId))],
            'settings.content.qr_url' => ['nullable', 'string', 'max:500'],
            'settings.content.tagline' => ['nullable', 'string', 'max:120'],
            'settings.bank' => ['nullable', 'array'],
            'settings.bank.name' => ['nullable', 'string', 'max:120'],
            'settings.bank.account_name' => ['nullable', 'string', 'max:120'],
            'settings.bank.account_number' => ['nullable', 'string', 'max:60'],
            'settings.bank.branch' => ['nullable', 'string', 'max:120'],
            'settings.bank.swift_code' => ['nullable', 'string', 'max:20'],
            'terms_html' => ['nullable', 'string'],
            'footer_html' => ['nullable', 'string'],
        ], [
            'settings.preset.enum' => 'Choose one of the available designs.',
            'settings.preset.not_in' => 'That design is exclusive to another company.',
            'settings.bank.*.string' => 'Bank details must be plain text.',
            'settings.bank.*.max' => 'Keep each bank detail under :max characters.',
        ]);

        /**
         * Rules on a few nested keys make validated() keep only those keys, which
         * would silently drop the rest of the settings (preset, brand, layout…).
         */
        $data['settings'] = $request->has('settings') ? (array) $request->input('settings') : null;

        return $data;
    }

    /**
     * The presets this company may build on, flagging those it holds
     * exclusively.
     *
     * @return list<array{id: string, exclusive: bool}>
     */
    private function presetOptions(int $companyId): array
    {
        $exclusive = ProprietaryPreset::query()
            ->where('company_id', $companyId)
            ->pluck('preset')
            ->all();

        return array_map(fn (TemplatePreset $preset): array => [
            'id' => $preset->value,
            'exclusive' => in_array($preset, $exclusive, true),
        ], TemplatePreset::usableBy($companyId));
    }

    private function normalizedSettings(array $incomingSettings, array $existingSettings = []): array
    {
        $defaults = $this->templateDefaults();

        $settings = array_replace_recursive($defaults, $existingSettings, $incomingSettings);
        $settings['visibility'] = array_merge(
            $defaults['visibility'],
            array_map(fn ($value) => (bool) $value, (array) ($settings['visibility'] ?? []))
        );
        $settings['content'] = [
            'qr_url' => $this->qrLink((string) ($settings['content']['qr_url'] ?? '')),
            'tagline' => trim((string) ($settings['content']['tagline'] ?? '')),
        ];
        $settings['bank'] = BankDetails::normalize($settings['bank'] ?? []);

        return $settings;
    }

    /**
     * A bare "example.com" would encode as plain text and phones would show it
     * rather than open it, so anything without a scheme is assumed to be a site.
     */
    private function qrLink(string $value): string
    {
        $value = trim($value);

        if ($value === '' || preg_match('/^[a-z][a-z0-9+.\-]*:/i', $value) === 1) {
            return $value;
        }

        return 'https://'.$value;
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
     *     brand: array{primary: string, accent: string, header: string, font: string},
     *     layout: array{header: string, table: string, density: string},
     *     visibility: array{
     *         show_logo: bool,
     *         show_client_email: bool,
     *         show_contact_person: bool,
     *         show_terms: bool,
     *         show_notes: bool,
     *         show_bank_details: bool,
     *         show_signature: bool,
     *         show_qr: bool
     *     },
     *     content: array{qr_url: string, tagline: string},
     *     bank: array{name: string, account_name: string, account_number: string, branch: string, swift_code: string}
     * }
     */
    private function templateDefaults(): array
    {
        return [
            'preset' => 'wave_premium',
            'brand' => [
                'primary' => '#111827',
                'accent' => '#F59E0B',
                'header' => '#111827',
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
                'show_qr' => true,
            ],
            'content' => [
                'qr_url' => '',
                'tagline' => '',
            ],
            'bank' => BankDetails::normalize([]),
        ];
    }
}
