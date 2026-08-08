export type {
    Auth,
    BreadcrumbItem,
    ChargeQuote,
    Coupon,
    CouponQuote,
    NavGroup,
    NavItem,
    Plan,
    PlanData,
    SharedData,
    SubscriptionData,
    UsageData,
    User,
} from './index.d';

export interface PageProps {
    auth?: {
        user?: {
            id: number;
            name: string;
            email: string;
            // Add more user fields as needed
        };
    };
    errors?: Record<string, string>;
    // Add more global props as needed
}
