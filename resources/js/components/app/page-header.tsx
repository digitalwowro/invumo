import type { ReactNode } from 'react';

type PageHeaderProps = {
    title: string;
    subtitle?: string;
    actions?: ReactNode;
};

export function PageHeader({ title, subtitle, actions }: PageHeaderProps) {
    return (
        <header className="flex flex-col gap-4 border-b border-divider pb-6 sm:flex-row sm:items-start sm:justify-between">
            <div className="min-w-0 space-y-1">
                <h1 className="text-2xl leading-8 font-bold tracking-tight text-foreground">
                    {title}
                </h1>
                {subtitle && (
                    <p className="text-sm leading-5 text-foreground-muted">
                        {subtitle}
                    </p>
                )}
            </div>
            {actions && (
                <div className="flex shrink-0 flex-wrap items-center gap-2">
                    {actions}
                </div>
            )}
        </header>
    );
}
