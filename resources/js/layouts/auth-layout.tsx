import AuthLayoutTemplate from '@/layouts/auth/auth-simple-layout';
import type { AuthLayoutProps } from '@/types';

export default function AuthLayout({ children }: AuthLayoutProps) {
    return <AuthLayoutTemplate>{children}</AuthLayoutTemplate>;
}
