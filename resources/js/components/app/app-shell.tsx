import { usePage } from '@inertiajs/react';
import type { CSSProperties, ReactNode } from 'react';
import { ImpersonationBanner } from '@/components/app/impersonation-banner';
import { SkipLink } from '@/components/app/skip-link';
import { SidebarProvider } from '@/components/ui/sidebar-context';
import type { AppVariant } from '@/types';

type Props = {
    children: ReactNode;
    variant?: AppVariant;
};

export function AppShell({ children, variant = 'sidebar' }: Props) {
    const isOpen = usePage().props.sidebarOpen;
    const isImpersonating = Boolean(usePage().props.impersonation);

    if (variant === 'header') {
        return (
            <>
                <ImpersonationBanner />
                <div className="flex min-h-screen w-full flex-col">
                    {children}
                </div>
            </>
        );
    }

    return (
        <>
            <ImpersonationBanner />
            <SidebarProvider
                defaultOpen={isOpen}
                style={
                    {
                        '--shell-top-offset': isImpersonating
                            ? '3.5rem'
                            : '0rem',
                    } as CSSProperties
                }
            >
                <SkipLink />
                {children}
            </SidebarProvider>
        </>
    );
}
