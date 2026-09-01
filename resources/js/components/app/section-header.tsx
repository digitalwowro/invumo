import type { ReactNode } from 'react';
import { PageSubtitle, SectionTitle } from '@/components/app/typography';

type SectionHeaderProps = {
    title: string;
    description?: string;
    action?: ReactNode;
    actionPlacement?: 'side' | 'below';
};

export function SectionHeader({
    title,
    description,
    action,
    actionPlacement = 'side',
}: SectionHeaderProps) {
    if (actionPlacement === 'below') {
        return (
            <header className="flex flex-col gap-3">
                <div className="min-w-0 space-y-1">
                    <SectionTitle>{title}</SectionTitle>
                    {description && <PageSubtitle>{description}</PageSubtitle>}
                </div>
                {action && <div>{action}</div>}
            </header>
        );
    }

    return (
        <header className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div className="min-w-0 space-y-1">
                <SectionTitle>{title}</SectionTitle>
                {description && <PageSubtitle>{description}</PageSubtitle>}
            </div>
            {action && <div className="shrink-0">{action}</div>}
        </header>
    );
}
