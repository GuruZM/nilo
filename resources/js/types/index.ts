export type {
    Auth,
    BreadcrumbItem,
    NavGroup,
    NavItem,
    SharedData,
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
