import type { ReactNode } from 'react';
import { SectionHeader } from '@/components/section-header';
import { Surface } from '@/components/surface';
import { FieldGroup } from '@/components/ui/field';

type FormSectionProps = {
    title: string;
    description?: string;
    children: ReactNode;
    actions?: ReactNode;
};

export function FormSection({
    title,
    description,
    children,
    actions,
}: FormSectionProps) {
    return (
        <Surface>
            <div className="space-y-6">
                <SectionHeader title={title} description={description} />
                <FieldGroup>{children}</FieldGroup>
                {actions && (
                    <div className="flex flex-wrap justify-end gap-2 border-t border-divider pt-4">
                        {actions}
                    </div>
                )}
            </div>
        </Surface>
    );
}
