import AuthLayoutTemplate from '@/layouts/auth/auth-simple-layout';

export default function AuthLayout({
    children,
    title,
    description,
    maxWidthClass,
    ...props
}: {
    children: React.ReactNode;
    title: string;
    description: string;
    maxWidthClass?: string;
}) {
    return (
        <AuthLayoutTemplate
            title={title}
            description={description}
            maxWidthClass={maxWidthClass}
            {...props}
        >
            {children}
        </AuthLayoutTemplate>
    );
}
