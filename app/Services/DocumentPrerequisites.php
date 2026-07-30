<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Currency;
use App\Models\InvoiceTemplate;

class DocumentPrerequisites
{
    public function __construct(private string $type, private ?int $companyId) {}

    public static function forInvoices(?int $companyId): self
    {
        return new self('invoice', $companyId);
    }

    public static function forQuotations(?int $companyId): self
    {
        return new self('quotation', $companyId);
    }

    public function hasActiveCompany(): bool
    {
        return (bool) $this->companyId;
    }

    public function hasTemplates(): bool
    {
        return $this->hasActiveCompany() && InvoiceTemplate::query()
            ->where('company_id', $this->companyId)
            ->where('type', $this->type)
            ->exists();
    }

    public function hasClients(): bool
    {
        return $this->hasActiveCompany() && Client::query()
            ->where('company_id', $this->companyId)
            ->exists();
    }

    public function hasCurrencies(): bool
    {
        return Currency::query()->where('is_active', true)->exists();
    }

    public function canCreate(): bool
    {
        return $this->blockers() === [];
    }

    /**
     * Confirms the given template belongs to the active company and matches this
     * document type. Guards against a template id from another company.
     */
    public function ownsTemplate(?int $templateId): bool
    {
        if (! $this->hasActiveCompany() || ! $templateId) {
            return false;
        }

        return InvoiceTemplate::query()
            ->where('id', $templateId)
            ->where('company_id', $this->companyId)
            ->where('type', $this->type)
            ->exists();
    }

    /**
     * Unmet prerequisites, most blocking first. Each later prerequisite is scoped
     * to the company, so the order is meaningful rather than cosmetic.
     *
     * @return list<array{key: string, title: string, description: string, action_label: string, action_href: string}>
     */
    public function blockers(): array
    {
        $label = $this->type === 'quotation' ? 'quotation' : 'invoice';
        $plural = $label.'s';
        $blockers = [];

        if (! $this->hasActiveCompany()) {
            $blockers[] = [
                'key' => 'company',
                'title' => 'Add or select a company',
                'description' => "Every {$label} is issued by a company. Create one or make an existing company active before you continue.",
                'action_label' => 'Manage companies',
                'action_href' => '/companies',
            ];

            return $blockers;
        }

        if (! $this->hasTemplates()) {
            $blockers[] = [
                'key' => 'template',
                'title' => "Create a {$label} template",
                'description' => "A template controls how your {$plural} look. Create at least one before issuing a {$label}.",
                'action_label' => 'Create template',
                'action_href' => "/settings/{$label}-templates/create",
            ];
        }

        if (! $this->hasClients()) {
            $blockers[] = [
                'key' => 'client',
                'title' => 'Add a client',
                'description' => "A {$label} has to be addressed to someone. Add your first client to continue.",
                'action_label' => 'Add client',
                'action_href' => '/clients',
            ];
        }

        if (! $this->hasCurrencies()) {
            $blockers[] = [
                'key' => 'currency',
                'title' => 'Add a currency',
                'description' => "Amounts on a {$label} need a currency. Activate at least one to continue.",
                'action_label' => 'Manage currencies',
                'action_href' => '/settings/currencies',
            ];
        }

        return $blockers;
    }

    /**
     * @return array{
     *     can_create: bool,
     *     has_active_company: bool,
     *     has_templates: bool,
     *     has_clients: bool,
     *     has_currencies: bool,
     *     blockers: list<array{key: string, title: string, description: string, action_label: string, action_href: string}>,
     * }
     */
    public function toArray(): array
    {
        $blockers = $this->blockers();

        return [
            'can_create' => $blockers === [],
            'has_active_company' => $this->hasActiveCompany(),
            'has_templates' => $this->hasTemplates(),
            'has_clients' => $this->hasClients(),
            'has_currencies' => $this->hasCurrencies(),
            'blockers' => $blockers,
        ];
    }
}
