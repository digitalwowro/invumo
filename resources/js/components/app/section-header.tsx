import type { ReactNode } from 'react';

type SectionHeaderProps = {
    title: string;
    description?: string;
    action?: ReactNode;
};

export function SectionHeader({
    title,
    description,
    action,
}: SectionHeaderProps) {
    return (
        <header className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div className="min-w-0 space-y-1">
                <h2 className="text-base leading-6 font-semibold text-foreground">
                    {title}
                </h2>
                {description && (
                    <p className="text-sm leading-5 text-foreground-muted">
                        {description}
                    </p>
                )}
            </div>
            {action && <div className="shrink-0">{action}</div>}
        </header>
    );
}
