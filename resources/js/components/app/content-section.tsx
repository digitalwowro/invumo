import type { ReactNode } from 'react';
import { SectionHeader } from '@/components/app/section-header';
import { Surface } from '@/components/app/surface';

type ContentSectionProps = {
    title: string;
    description?: string;
    children: ReactNode;
    headerActions?: ReactNode;
    headerActionsPlacement?: 'side' | 'below';
    footer?: ReactNode;
    footerVariant?: 'actions' | 'link';
};

export function ContentSection({
    title,
    description,
    children,
    headerActions,
    headerActionsPlacement = 'side',
    footer,
    footerVariant = 'actions',
}: ContentSectionProps) {
    return (
        <Surface className="overflow-hidden p-0">
            <div className="border-b border-divider px-5 py-4 sm:px-6">
                <SectionHeader
                    title={title}
                    description={description}
                    action={headerActions}
                    actionPlacement={headerActionsPlacement}
                />
            </div>
            {children}
            {footer && (
                <div
                    className={
                        footerVariant === 'link'
                            ? 'border-t border-rule bg-surface-subtle px-4 py-2.5 text-center text-xs font-semibold [&_a]:outline-none [&_a]:hover:underline [&_a]:focus-visible:ring-2 [&_a]:focus-visible:ring-ring'
                            : 'flex flex-wrap justify-end gap-2 border-t border-divider px-5 py-4 sm:px-6'
                    }
                >
                    {footer}
                </div>
            )}
        </Surface>
    );
}

export type { ContentSectionProps };
