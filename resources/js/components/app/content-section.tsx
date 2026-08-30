import type { ReactNode } from 'react';
import { SectionHeader } from '@/components/app/section-header';
import { Surface } from '@/components/app/surface';

type ContentSectionProps = {
    title: string;
    description?: string;
    children: ReactNode;
    headerActions?: ReactNode;
    footer?: ReactNode;
};

export function ContentSection({
    title,
    description,
    children,
    headerActions,
    footer,
}: ContentSectionProps) {
    return (
        <Surface className="overflow-hidden p-0">
            <div className="border-b border-divider px-5 py-4 sm:px-6">
                <SectionHeader
                    title={title}
                    description={description}
                    action={headerActions}
                />
            </div>
            {children}
            {footer && (
                <div className="flex flex-wrap justify-end gap-2 border-t border-divider px-5 py-4 sm:px-6">
                    {footer}
                </div>
            )}
        </Surface>
    );
}

export type { ContentSectionProps };
