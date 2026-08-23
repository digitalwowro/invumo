import { Inbox, SearchX } from 'lucide-react';
import type { ReactNode } from 'react';
import { SystemMessage } from '@/components/app/system-message';
import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Skeleton } from '@/components/ui/skeleton';

type EmptyStateProps = {
    title: string;
    description: string;
    action?: ReactNode;
    kind?: 'empty' | 'no-results';
};

export function EmptyState({
    title,
    description,
    action,
    kind = 'empty',
}: EmptyStateProps) {
    const Icon = kind === 'no-results' ? SearchX : Inbox;

    return (
        <Empty>
            <EmptyHeader>
                <EmptyMedia variant="icon">
                    <Icon aria-hidden="true" />
                </EmptyMedia>
                <EmptyTitle>{title}</EmptyTitle>
                <EmptyDescription>{description}</EmptyDescription>
            </EmptyHeader>
            {action && <EmptyContent>{action}</EmptyContent>}
        </Empty>
    );
}

export function LoadingState({ label }: { label: string }) {
    return (
        <div
            data-slot="loading-state"
            aria-busy="true"
            aria-label={label}
            className="space-y-3 rounded-lg border border-border p-6"
        >
            <span className="sr-only">{label}</span>
            <Skeleton className="h-4 w-1/3" />
            <Skeleton className="h-4 w-full" />
            <Skeleton className="h-4 w-4/5" />
        </div>
    );
}

type ErrorStateProps = {
    title: string;
    description: string;
    retry?: ReactNode;
};

export function ErrorState({ title, description, retry }: ErrorStateProps) {
    return (
        <SystemMessage
            tone="error"
            title={title}
            description={description}
            action={retry}
        />
    );
}
