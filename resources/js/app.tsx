import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import CompanySettingsLayout from '@/layouts/company-settings-layout';
import ImpersonationLayout from '@/layouts/impersonation-layout';
import PlatformLayout from '@/layouts/platform-layout';
import PublicLayout from '@/layouts/public-layout';
import SettingsLayout from '@/layouts/settings/layout';

const appName = import.meta.env.VITE_APP_NAME || 'Invumo';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name.startsWith('auth/'):
            case name.startsWith('errors/'):
                return AuthLayout;
            case name.startsWith('impersonation/'):
                return ImpersonationLayout;
            case name.startsWith('companies/settings/'):
                return [AppLayout, CompanySettingsLayout];
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            case name.startsWith('platform/'):
                return PlatformLayout;
            case name.startsWith('public/'):
                return PublicLayout;
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: 'var(--foreground)',
    },
});
