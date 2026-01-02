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
    // If you already have a helper on a base controller, replace this with your existing approach.
    protected function companyId(Request $request): int
    {
        $user = $request->user();

        return (int) ($user?->current_company_id ?? 0);
    }

    public function index(Request $request)
    {
        $companyId = $this->companyId($request);

        if (! $companyId) {
            return redirect('/companies')->with('error', 'Select a company first.');
        }

        $templates = InvoiceTemplate::query()
            ->where('company_id', $companyId)
            ->where('type', 'invoice')
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
        ]);
    }

    public function create(Request $request)
    {
        $companyId = $this->companyId($request);

        if (! $companyId) {
            return redirect('/companies')->with('error', 'Select a company first.');
        }

        return Inertia::render('settings/invoicetemplates/builder', [
            'mode' => 'create',
            'template' => null,
        ]);
    }

    public function edit(Request $request, InvoiceTemplate $template)
    {
        $companyId = $this->companyId($request);

        if (! $companyId) {
            return redirect('/companies')->with('error', 'Select a company first.');
        }

        if ((int) $template->company_id !== (int) $companyId || $template->type !== 'invoice') {
            throw ValidationException::withMessages([
                'template' => 'Unauthorized template access.',
            ]);
        }

        return Inertia::render('Settings/InvoiceTemplates/Builder', [
            'mode' => 'edit',
            'template' => $template->only([
                'id',
                'name',
                'type',
                'is_default',
                'settings',
                'terms_html',
                'footer_html',
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $companyId = $this->companyId($request);

        if (! $companyId) {
            return back()->with('error', 'Select a company first.');
        }

        try {
            $data = $request->validate([
                'name' => ['required', 'string', 'max:120'],
                'is_default' => ['required', 'boolean'],
                'settings' => ['nullable', 'array'],
                'terms_html' => ['nullable', 'string'],
                'footer_html' => ['nullable', 'string'],
            ]);

            DB::transaction(function () use ($companyId, $data) {
                if ($data['is_default']) {
                    InvoiceTemplate::query()
                        ->where('company_id', $companyId)
                        ->where('type', 'invoice')
                        ->update(['is_default' => false]);
                }

                $template = InvoiceTemplate::create([
                    'company_id' => $companyId,
                    'type' => 'invoice', // ✅ hard locked
                    'name' => $data['name'],
                    'is_default' => (bool) $data['is_default'],
                    'settings' => $data['settings'] ?? [],
                    'terms_html' => $data['terms_html'] ?? null,
                    'footer_html' => $data['footer_html'] ?? null,
                ]);

                // If this is the first invoice template, force it default
                $count = InvoiceTemplate::query()
                    ->where('company_id', $companyId)
                    ->where('type', 'invoice')
                    ->count();

                if ($count === 1) {
                    $template->forceFill(['is_default' => true])->save();
                }
            });

            return redirect('/settings/invoice-templates')->with('success', 'Invoice template created.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Invoice template store failed', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'template' => 'Failed to create template. Please try again.',
            ]);
        }
    }

    public function update(Request $request, InvoiceTemplate $template)
    {
        $companyId = $this->companyId($request);

        if (! $companyId) {
            return back()->with('error', 'Select a company first.');
        }

        if ((int) $template->company_id !== (int) $companyId || $template->type !== 'invoice') {
            throw ValidationException::withMessages([
                'template' => 'Unauthorized template access.',
            ]);
        }

        try {
            $data = $request->validate([
                'name' => ['required', 'string', 'max:120'],
                'is_default' => ['required', 'boolean'],
                'settings' => ['nullable', 'array'],
                'terms_html' => ['nullable', 'string'],
                'footer_html' => ['nullable', 'string'],
            ]);

            DB::transaction(function () use ($companyId, $template, $data) {
                if ($data['is_default']) {
                    InvoiceTemplate::query()
                        ->where('company_id', $companyId)
                        ->where('type', 'invoice')
                        ->update(['is_default' => false]);
                }

                $template->update([
                    'name' => $data['name'],
                    'is_default' => (bool) $data['is_default'],
                    'settings' => $data['settings'] ?? $template->settings ?? [],
                    'terms_html' => $data['terms_html'] ?? null,
                    'footer_html' => $data['footer_html'] ?? null,
                ]);
            });

            return redirect('/settings/invoice-templates')->with('success', 'Invoice template updated.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Invoice template update failed', [
                'company_id' => $companyId,
                'template_id' => $template->id,
                'error' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'template' => 'Failed to update template. Please try again.',
            ]);
        }
    }

    public function makeDefault(Request $request, InvoiceTemplate $template)
    {
        $companyId = $this->companyId($request);

        if (! $companyId) {
            return back()->with('error', 'Select a company first.');
        }

        if ((int) $template->company_id !== (int) $companyId || $template->type !== 'invoice') {
            throw ValidationException::withMessages([
                'template' => 'Unauthorized template access.',
            ]);
        }

        DB::transaction(function () use ($companyId, $template) {
            InvoiceTemplate::query()
                ->where('company_id', $companyId)
                ->where('type', 'invoice')
                ->update(['is_default' => false]);

            $template->forceFill(['is_default' => true])->save();
        });

        return back()->with('success', 'Default invoice template updated.');
    }
}