import AuthLayoutTemplate from '@/layouts/auth/auth-split-layout';

export default function AuthLayout({
    children,
    title,
    description,
    tagline,
    ...props
}: {
    children: React.ReactNode;
    title: string;
    description?: string;
    tagline?: string;
}) {
    return (
        <AuthLayoutTemplate
            title={title}
            description={description}
            tagline={tagline}
            {...props}
        >
            {children}
        </AuthLayoutTemplate>
    );
}
