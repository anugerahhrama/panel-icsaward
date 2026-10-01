import { Toaster } from '@/components/ui/sonner';
import AuthLayoutTemplate from '@/layouts/auth/auth-simple-layout';
import { FORM_ERROR_TOASTER_ID } from '@/lib/form-errors';

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
        <>
            <AuthLayoutTemplate
                title={title}
                description={description}
                wide={wide}
                hideRegistrationPeriod={hideRegistrationPeriod}
            >
                {children}
            </AuthLayoutTemplate>
            <Toaster id={FORM_ERROR_TOASTER_ID} position="bottom-center" />
        </>
    );
}
