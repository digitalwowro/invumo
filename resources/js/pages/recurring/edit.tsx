import { Head } from '@inertiajs/react';
import { Stack } from '@/components/app/layout';
import { PageFrame } from '@/components/app/page-frame';
import { PageHeader } from '@/components/app/page-header';
import { SystemMessage } from '@/components/app/system-message';
import { StatusBadge } from '@/components/domain/status-badge';
import { RecurringTemplateDeleteDialog } from '@/features/recurring/components/recurring-template-delete-dialog';
import { RecurringTemplateDraftEditor } from '@/features/recurring/components/recurring-template-draft-editor';
import type { CatalogTranslations } from '@/types/catalog';
import type { CustomerTranslations } from '@/types/customer';
import type {
    RecurringSourceProps,
    RecurringInheritanceProps,
    RecurringTemplateDraft,
    RecurringTemplateLimits,
    RecurringTranslations,
} from '@/types/recurring';

type Props = RecurringSourceProps &
    RecurringInheritanceProps & {
        template: RecurringTemplateDraft;
        limits: RecurringTemplateLimits;
        updateUrl: string;
        deleteUrl: string;
        indexUrl: string;
        canDelete: boolean;
        status?: string;
        translations: RecurringTranslations;
        customerTranslations: CustomerTranslations;
        catalogTranslations: CatalogTranslations;
    };

export default function EditRecurringTemplate(props: Props) {
    return (
        <>
            <Head
                title={`${props.translations.editor.head_title} ${props.template.internalName}`}
            />
            <PageFrame width="full">
                <Stack gap="2xl">
                    <PageHeader
                        title={props.template.internalName}
                        subtitle={props.translations.editor.description}
                        actions={
                            <>
                                <StatusBadge
                                    status="draft"
                                    label={
                                        props.translations.index.states.DRAFT
                                    }
                                />
                                {props.canDelete && (
                                    <RecurringTemplateDeleteDialog
                                        url={props.deleteUrl}
                                        labels={props.translations.deletion}
                                    />
                                )}
                            </>
                        }
                    />
                    {props.status && (
                        <SystemMessage title={props.status} tone="money" />
                    )}
                    <RecurringTemplateDraftEditor
                        {...props}
                        labels={props.translations.editor}
                        customerLabels={props.customerTranslations}
                        catalogLabels={props.catalogTranslations}
                    />
                </Stack>
            </PageFrame>
        </>
    );
}
