<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceTemplate;
use App\Models\Plan;
use App\Models\PurchaseOrder;
use App\Models\Quotation;
use App\Models\User;

class SubscriptionLimitService
{
    public function __construct(private User $user) {}

    public function activePlan(): ?Plan
    {
        return $this->user->activePlan();
    }

    /**
     * A structured refusal the UI renders as a dialog rather than a toast.
     *
     * The limit checks return false both when an allowance is used up and when
     * there is no plan at all, so the wording distinguishes the two — the old
     * single message claimed a limit was reached even for accounts that never
     * had one.
     *
     * @return array{title: string, message: string, action_label: string, action_href: string}
     */
    public function limitNotice(string $documents): array
    {
        $plan = $this->activePlan();

        if (! $plan) {
            return [
                'title' => 'No active plan',
                'message' => "Your account has no active plan, so {$documents} cannot be created yet. Choose a plan to get started.",
                'action_label' => 'Choose a plan',
                'action_href' => route('subscription.select'),
            ];
        }

        return [
            'title' => 'Plan limit reached',
            'message' => "The {$plan->name} plan does not cover any more {$documents}. Upgrade your plan to keep creating them.",
            'action_label' => 'View plans',
            'action_href' => route('subscription.select'),
        ];
    }

    public function canCreateCompany(): bool
    {
        $plan = $this->activePlan();

        if (! $plan) {
            return false;
        }

        if ($plan->max_companies === -1) {
            return true;
        }

        return $this->user->ownedCompanies()->count() < $plan->max_companies;
    }

    public function canCreateInvoice(int $companyId): bool
    {
        $plan = $this->activePlan();

        if (! $plan) {
            return false;
        }

        if ($plan->max_invoices === -1) {
            return true;
        }

        $count = Invoice::where('company_id', $companyId)->count();

        return $count < $plan->max_invoices;
    }

    public function canCreateQuotation(int $companyId): bool
    {
        $plan = $this->activePlan();

        if (! $plan) {
            return false;
        }

        if ($plan->max_quotations === -1) {
            return true;
        }

        $count = Quotation::where('company_id', $companyId)->count();

        return $count < $plan->max_quotations;
    }

    public function canCreatePurchaseOrder(int $companyId): bool
    {
        $plan = $this->activePlan();

        if (! $plan) {
            return false;
        }

        if ($plan->max_purchase_orders === -1) {
            return true;
        }

        return PurchaseOrder::where('company_id', $companyId)->count() < $plan->max_purchase_orders;
    }

    public function canCreateTemplate(string $type, int $companyId): bool
    {
        $plan = $this->activePlan();

        if (! $plan) {
            return false;
        }

        $limit = $type === 'invoice'
            ? $plan->max_invoice_templates
            : $plan->max_quotation_templates;

        if ($limit === -1) {
            return true;
        }

        $count = InvoiceTemplate::where('company_id', $companyId)
            ->where('type', $type)
            ->count();

        return $count < $limit;
    }

    public function canUploadCustomTemplate(): bool
    {
        $plan = $this->activePlan();

        return $plan && $plan->can_upload_custom_template;
    }

    /**
     * @return array{
     *     companies: array{used: int, limit: int},
     *     invoices: array{used: int, limit: int},
     *     quotations: array{used: int, limit: int},
     *     purchase_orders: array{used: int, limit: int},
     *     invoice_templates: array{used: int, limit: int},
     *     quotation_templates: array{used: int, limit: int},
     * }
     */
    public function usage(): array
    {
        $plan = $this->activePlan();
        $companyId = $this->user->current_company_id;

        return [
            'companies' => [
                'used' => $this->user->ownedCompanies()->count(),
                'limit' => $plan?->max_companies ?? 0,
            ],
            'invoices' => [
                'used' => $companyId ? Invoice::where('company_id', $companyId)->count() : 0,
                'limit' => $plan?->max_invoices ?? 0,
            ],
            'quotations' => [
                'used' => $companyId ? Quotation::where('company_id', $companyId)->count() : 0,
                'limit' => $plan?->max_quotations ?? 0,
            ],
            'purchase_orders' => [
                'used' => $companyId ? PurchaseOrder::where('company_id', $companyId)->count() : 0,
                'limit' => $plan?->max_purchase_orders ?? 0,
            ],
            'invoice_templates' => [
                'used' => $companyId ? InvoiceTemplate::where('company_id', $companyId)->where('type', 'invoice')->count() : 0,
                'limit' => $plan?->max_invoice_templates ?? 0,
            ],
            'quotation_templates' => [
                'used' => $companyId ? InvoiceTemplate::where('company_id', $companyId)->where('type', 'quotation')->count() : 0,
                'limit' => $plan?->max_quotation_templates ?? 0,
            ],
        ];
    }
}
