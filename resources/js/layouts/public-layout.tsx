import type { PropsWithChildren } from 'react';
import AppLogo from '@/components/app/app-logo';

export default function PublicLayout({ children }: PropsWithChildren) {
    return (
        <div className="min-h-svh bg-page">
            <header className="border-b border-border bg-background">
                <div className="mx-auto flex w-full max-w-screen-2xl items-center px-4 py-4 sm:px-6 lg:px-8">
                    <AppLogo size="header" />
                </div>
            </header>
            <main>{children}</main>
        </div>
    );
}
