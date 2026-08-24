import { ImpersonationBanner } from '@/components/app/impersonation-banner';
import AuthLayoutTemplate from '@/layouts/auth/auth-simple-layout';
import type { AuthLayoutProps } from '@/types';

export default function AuthLayout({ children }: AuthLayoutProps) {
    return (
        <>
            <ImpersonationBanner />
            <AuthLayoutTemplate>{children}</AuthLayoutTemplate>
        </>
    );
}
