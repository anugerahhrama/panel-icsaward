import AuthLayoutTemplate from '@/layouts/auth/auth-simple-layout';

export default function AuthLayout({
    title = '',
    description = '',
    wide = false,
    hideRegistrationPeriod = false,
    children,
}: {
    title?: string;
    description?: string;
    wide?: boolean;
    hideRegistrationPeriod?: boolean;
    children: React.ReactNode;
}) {
    return (
        <AuthLayoutTemplate
            title={title}
            description={description}
            wide={wide}
            hideRegistrationPeriod={hideRegistrationPeriod}
        >
            {children}
        </AuthLayoutTemplate>
    );
}
