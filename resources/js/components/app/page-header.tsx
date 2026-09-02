import type { ReactNode } from 'react';
import { Breadcrumbs } from '@/components/app/breadcrumbs';
import { PageSubtitle, PageTitle } from '@/components/app/typography';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

type PageHeaderProps = {
    title: string;
    subtitle?: string;
    breadcrumbs?: BreadcrumbItem[];
    status?: ReactNode;
    actions?: ReactNode;
    actionsPlacement?: 'below' | 'top-right';
};

export function PageHeader({
    title,
    subtitle,
    breadcrumbs = [],
    status,
    actions,
    actionsPlacement = 'below',
}: PageHeaderProps) {
    return (
        <header
            data-slot="page-header"
            className={cn(
                'flex flex-col gap-4',
                actionsPlacement === 'top-right' &&
                    'sm:flex-row sm:items-start sm:justify-between',
            )}
        >
            <div className="min-w-0 space-y-1">
                <Breadcrumbs breadcrumbs={breadcrumbs} />
                <div className="flex min-w-0 flex-wrap items-center gap-3">
                    <PageTitle>{title}</PageTitle>
                    {status}
                </div>
                {subtitle && <PageSubtitle>{subtitle}</PageSubtitle>}
            </div>
            {actions && (
                <div
                    data-slot="page-header-actions"
                    className={cn(
                        'flex flex-wrap items-center gap-2',
                        actionsPlacement === 'top-right' &&
                            'shrink-0 sm:justify-end',
                    )}
                >
                    {actions}
                </div>
            )}
        </header>
    );
}
