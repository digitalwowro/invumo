import type { PropsWithChildren } from 'react';

export default function PublicLayout({ children }: PropsWithChildren) {
    return (
        <div className="min-h-svh bg-page">
            <header className="border-b border-border bg-background">
                <div className="mx-auto flex w-full max-w-screen-2xl items-center px-4 py-4 sm:px-6 lg:px-8">
                    <span className="text-xl font-bold tracking-tight">
                        Invumo
                    </span>
                </div>
            </header>
            <main>{children}</main>
        </div>
    );
}
