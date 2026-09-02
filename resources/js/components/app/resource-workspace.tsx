import type { ReactNode } from 'react';
import { Breadcrumbs } from '@/components/app/breadcrumbs';
import { PageFrame } from '@/components/app/page-frame';
import { PageSubtitle, PageTitle } from '@/components/app/typography';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

type WorkspaceProps = {
    children: ReactNode;
};

export function ResourceWorkspace({ children }: WorkspaceProps) {
    return (
        <div data-slot="resource-workspace" className="min-h-full bg-page">
            <PageFrame width="full">{children}</PageFrame>
        </div>
    );
}

type HeaderProps = {
    breadcrumbs: BreadcrumbItem[];
    title: string;
    description: string;
    status?: ReactNode;
    actions?: ReactNode;
    navigation?: ReactNode;
};

export function ResourceWorkspaceHeader({
    breadcrumbs,
    title,
    description,
    status,
    actions,
    navigation,
}: HeaderProps) {
    return (
        <header
            data-slot="resource-workspace-header"
            className={cn(
                '-mx-4 -mt-6 border-b border-divider bg-background px-4 pt-5 sm:-mx-6 sm:px-6 md:sticky md:top-0 md:z-20 lg:-mx-8 lg:px-8',
                !navigation && 'pb-5',
            )}
        >
            <div className="flex flex-col gap-4">
                <div className="min-w-0 space-y-1">
                    <Breadcrumbs breadcrumbs={breadcrumbs} />
                    <div className="flex min-w-0 flex-wrap items-center gap-3">
                        <PageTitle>{title}</PageTitle>
                        {status}
                    </div>
                    <PageSubtitle>{description}</PageSubtitle>
                </div>
                {actions && (
                    <div className="flex flex-wrap items-center gap-2">
                        {actions}
                    </div>
                )}
                {navigation}
            </div>
        </header>
    );
}
