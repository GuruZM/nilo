/// <reference types="react" />

// InertiaJS global types (optional, for better DX)
declare module '@inertiajs/inertia' {
    export interface PageProps {
        auth?: {
            user?: {
                id: number;
                name: string;
                email: string;
            };
        };
        errors?: Record<string, string>;
    }
}
