import { InertiaLinkProps } from '@inertiajs/react';
import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
    roles: string[];
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
    items?: NavItem[];
}

export interface PlanData {
    id: number;
    name: string;
    slug: string;
}

export interface UsageData {
    companies: { used: number; limit: number };
    invoices: { used: number; limit: number };
    quotations: { used: number; limit: number };
    invoice_templates: { used: number; limit: number };
    quotation_templates: { used: number; limit: number };
}

export interface SubscriptionData {
    plan: PlanData | null;
    status: string | null;
    usage: UsageData | null;
}

export interface SharedData {
    name: string;
    /** Mirrored into the `csrf-token` meta tag on every visit; see lib/csrf.ts. */
    csrfToken: string;
    quote: { message: string; author: string };
    auth: Auth;
    subscription: SubscriptionData | null;
    sidebarOpen: boolean;
    oauth: {
        facebook: boolean;
        linkedin: boolean;
    };
    flash: {
        success?: string;
        error?: string;
        info?: string;
    };
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
}

/**
 * `price` arrives as a decimal string from Eloquent's `decimal:2` cast, and a
 * limit of -1 means unlimited. `subscriptions_count` is only loaded on the
 * admin plans index.
 */
export interface Plan {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    price: string;
    currency_code: string;
    billing_period: string;
    max_companies: number;
    max_invoices: number;
    max_quotations: number;
    max_purchase_orders: number;
    max_invoice_templates: number;
    max_quotation_templates: number;
    can_upload_custom_template: boolean;
    is_active: boolean;
    is_public: boolean;
    is_popular: boolean;
    features: string[] | null;
    sort_order: number;
    subscriptions_count?: number;
}

/**
 * `discount_value` is a percentage when `discount_type` is 'percentage' and a
 * money amount when it is 'fixed' — `currency_code` is only set for the latter.
 * An empty `plans` list means the coupon applies to every purchasable plan.
 */
export interface Coupon {
    id: number;
    code: string;
    description: string | null;
    discount_type: 'percentage' | 'fixed';
    discount_value: string;
    currency_code: string | null;
    starts_at: string | null;
    expires_at: string | null;
    max_redemptions: number | null;
    redemptions_count: number;
    once_per_user: boolean;
    is_active: boolean;
    plans?: { id: number; name: string }[];
    plan_ids?: number[];
}

/**
 * What the customer owes for a plan. Always server-derived — `error` carries a
 * rejected code's reason while leaving the undiscounted total intact.
 */
export interface CouponQuote {
    subtotal: number;
    discount: number;
    total: number;
    currency_code: string;
    coupon: {
        code: string;
        label: string;
        description: string | null;
    } | null;
    error: string | null;
}
