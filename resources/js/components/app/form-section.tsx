import type { ReactNode } from 'react';
import { ContentSection } from '@/components/app/content-section';
import { FieldGroup } from '@/components/ui/field';
import { cn } from '@/lib/utils';

type FormSectionProps = {
    title: string;
    description?: string;
    children: ReactNode;
    headerActions?: ReactNode;
    actions?: ReactNode;
    flush?: boolean;
    contentClassName?: string;
};

export function FormSection({
    title,
    description,
    children,
    headerActions,
    actions,
    flush = false,
    contentClassName,
}: FormSectionProps) {
    return (
        <ContentSection
            title={title}
            description={description}
            headerActions={headerActions}
            footer={actions}
        >
            <FieldGroup
                className={cn(flush ? 'gap-0' : 'p-5 sm:p-6', contentClassName)}
            >
                {children}
            </FieldGroup>
        </ContentSection>
    );
}
