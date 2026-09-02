import type { ReactNode } from 'react';
import { Breadcrumbs } from '@/components/app/breadcrumbs';
import { PageSubtitle, PageTitle } from '@/components/app/typography';
import { Badge } from '@/components/ui/badge';

type Props = {
    indexUrl: string;
    indexLabel: string;
    number: string;
    title: string;
    description: string;
    dirty: boolean;
    dirtyLabel: string;
    status: ReactNode;
    secondaryActions: ReactNode;
    primaryActions: ReactNode;
    tabs: ReactNode;
};

export function DocumentWorkspaceHeader(props: Props) {
    return (
        <header
            data-slot="document-workspace-header"
            className="-mx-4 -mt-6 border-b border-divider bg-background px-4 pt-5 sm:-mx-6 sm:px-6 md:sticky md:top-0 md:z-20 lg:-mx-8 lg:px-8"
        >
            <div className="flex flex-col gap-4">
                <div className="flex min-w-0 flex-col gap-1">
                    <Breadcrumbs
                        breadcrumbs={[
                            {
                                title: props.indexLabel,
                                href: props.indexUrl,
                            },
                            { title: props.number, href: props.indexUrl },
                        ]}
                    />
                    <div className="flex min-w-0 flex-wrap items-center gap-3">
                        <PageTitle>
                            <span className="font-data">
                                {props.title} {props.number}
                            </span>
                        </PageTitle>
                        {props.status}
                        {props.dirty && (
                            <Badge variant="warning" className="rounded-full">
                                {props.dirtyLabel}
                            </Badge>
                        )}
                    </div>
                    <PageSubtitle>{props.description}</PageSubtitle>
                </div>
                <div className="flex flex-col justify-between gap-3 xl:flex-row xl:items-center">
                    <div className="flex flex-wrap items-center gap-2">
                        {props.secondaryActions}
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        {props.primaryActions}
                    </div>
                </div>
                {props.tabs}
            </div>
        </header>
    );
}
