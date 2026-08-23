import type { ReactNode } from 'react';
import { PageSubtitle, PageTitle } from '@/components/app/typography';

type PageHeaderProps = {
    title: string;
    subtitle?: string;
    actions?: ReactNode;
};

export function PageHeader({ title, subtitle, actions }: PageHeaderProps) {
    return (
        <header className="flex flex-col gap-4 border-b border-divider pb-6 sm:flex-row sm:items-start sm:justify-between">
            <div className="min-w-0 space-y-1">
                <PageTitle>{title}</PageTitle>
                {subtitle && <PageSubtitle>{subtitle}</PageSubtitle>}
            </div>
            {actions && (
                <div className="flex shrink-0 flex-wrap items-center gap-2">
                    {actions}
                </div>
            )}
        </header>
    );
}
