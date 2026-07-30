<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Models\InvoiceTemplate;

/**
 * Resolves the template a document should print through, creating a default
 * when the company has none.
 *
 * Invoices and quotations gate on a template existing, because designing one is
 * part of setting those up. The derived documents do not: making somebody build
 * a delivery note template before they can dispatch goods is a wall, not a
 * feature. They start on the house default and can be styled later through the
 * existing builder.
 */
class TemplateProvisioner
{
    /**
     * Resolves the template, creating one as a side effect when the company has
     * none of this type yet. Callers always get a persisted template back.
     */
    public function forCompany(int $companyId, DocumentType $type, ?int $templateId = null): InvoiceTemplate
    {
        if ($templateId) {
            $chosen = InvoiceTemplate::query()
                ->where('id', $templateId)
                ->where('company_id', $companyId)
                ->where('type', $type->value)
                ->first();

            if ($chosen) {
                return $chosen;
            }
        }

        $existing = InvoiceTemplate::query()
            ->where('company_id', $companyId)
            ->where('type', $type->value)
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        return InvoiceTemplate::query()->create([
            'company_id' => $companyId,
            'name' => 'Default '.$type->label(),
            'type' => $type->value,
            'is_default' => true,
            'settings' => [],
        ]);
    }
}
