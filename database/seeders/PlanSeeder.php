<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free',
                'slug' => 'free',
                'description' => 'Get started with basic invoicing features.',
                'price' => 0,
                'currency_code' => 'ZMW',
                'billing_period' => 'monthly',
                'max_companies' => 1,
                'max_invoices' => 1,
                'max_quotations' => 1,
                'max_invoice_templates' => 1,
                'max_quotation_templates' => 1,
                'can_upload_custom_template' => false,
                'is_active' => true,
                'sort_order' => 0,
                'features' => [
                    '1 Company',
                    '1 Invoice',
                    '1 Quotation',
                    '1 Template each',
                    'Email support',
                ],
            ],
            [
                'name' => 'Standard',
                'slug' => 'standard',
                'description' => 'For growing businesses that need more capacity.',
                'price' => 100000,
                'currency_code' => 'ZMW',
                'billing_period' => 'monthly',
                'max_companies' => 2,
                'max_invoices' => 20,
                'max_quotations' => 20,
                'max_invoice_templates' => 5,
                'max_quotation_templates' => 5,
                'can_upload_custom_template' => false,
                'is_active' => true,
                'is_popular' => true,
                'sort_order' => 1,
                'features' => [
                    '2 Companies',
                    '20 Invoices',
                    '20 Quotations',
                    'Multiple templates',
                    'Priority support',
                ],
            ],
            [
                'name' => 'Premium',
                'slug' => 'premium',
                'description' => 'Unlimited invoicing power for established businesses.',
                'price' => 200000,
                'currency_code' => 'ZMW',
                'billing_period' => 'monthly',
                'max_companies' => 5,
                'max_invoices' => -1,
                'max_quotations' => -1,
                'max_invoice_templates' => -1,
                'max_quotation_templates' => -1,
                'can_upload_custom_template' => true,
                'is_active' => true,
                'sort_order' => 2,
                'features' => [
                    '3-5 Companies',
                    'Unlimited invoices',
                    'Unlimited quotations',
                    'Multiple templates',
                    'Upload custom templates',
                    'Priority support',
                ],
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'Custom solutions tailored to your business needs.',
                'price' => 0,
                'currency_code' => 'ZMW',
                'billing_period' => 'monthly',
                'max_companies' => -1,
                'max_invoices' => -1,
                'max_quotations' => -1,
                'max_invoice_templates' => -1,
                'max_quotation_templates' => -1,
                'can_upload_custom_template' => true,
                'is_active' => true,
                'sort_order' => 3,
                'features' => [
                    'Unlimited companies',
                    'Unlimited invoices',
                    'Unlimited quotations',
                    'Unlimited templates',
                    'Upload custom templates',
                    'Dedicated support',
                    'Custom integrations',
                ],
            ],
            [
                'name' => 'Resonantt',
                'slug' => 'resonantt',
                'description' => 'Complimentary lifetime access for Resonantt users.',
                'price' => 0,
                'currency_code' => 'ZMW',
                'billing_period' => 'monthly',
                'max_companies' => -1,
                'max_invoices' => -1,
                'max_quotations' => -1,
                'max_invoice_templates' => -1,
                'max_quotation_templates' => -1,
                'can_upload_custom_template' => true,
                'is_active' => true,
                'is_public' => false,
                'is_popular' => false,
                'sort_order' => 99,
                'features' => [
                    'Unlimited companies',
                    'Unlimited invoices',
                    'Unlimited quotations',
                    'Unlimited templates',
                    'Upload custom templates',
                    'Free forever',
                ],
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(
                ['slug' => $plan['slug']],
                $plan,
            );
        }
    }
}
