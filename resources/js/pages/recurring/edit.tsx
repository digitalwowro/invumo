import { Head, usePage } from '@inertiajs/react';
import { Stack } from '@/components/app/layout';
import { PageFrame } from '@/components/app/page-frame';
import { PageHeader } from '@/components/app/page-header';
import { SystemMessage } from '@/components/app/system-message';
import { StatusBadge } from '@/components/domain/status-badge';
import { RecurringAutomaticEmailForm } from '@/features/recurring/components/recurring-automatic-email-form';
import { RecurringTemplateDeleteDialog } from '@/features/recurring/components/recurring-template-delete-dialog';
import { RecurringTemplateDraftEditor } from '@/features/recurring/components/recurring-template-draft-editor';
import { RecurringTemplateExecution } from '@/features/recurring/components/recurring-template-execution';
import { RecurringTemplateLifecycleActions } from '@/features/recurring/components/recurring-template-lifecycle-actions';
import { RecurringTemplateScheduleForm } from '@/features/recurring/components/recurring-template-schedule-form';
import type { CatalogTranslations } from '@/types/catalog';
import type { CustomerTranslations } from '@/types/customer';
import type { DependencyGuard } from '@/types/dependency-guard';
import type {
    RecurringSourceProps,
    RecurringInheritanceProps,
    RecurringTemplateDraft,
    RecurringTemplateLimits,
} from '@/types/recurring';
import type { RecurringTranslations } from '@/types/recurring-translations';

type Props = RecurringSourceProps &
    RecurringInheritanceProps & {
        template: RecurringTemplateDraft;
        limits: RecurringTemplateLimits;
        updateUrl: string;
        scheduleUpdateUrl: string;
        automaticEmailUpdateUrl: string;
        transitionUrls: Record<
            'activate' | 'pause' | 'resume' | 'complete',
            string
        >;
        duplicateUrl: string;
        retryUrl: string;
        duplicateCreationKey: string;
        deleteUrl: string;
        deletion: { highRisk: boolean; guard: DependencyGuard };
        indexUrl: string;
        canDelete: boolean;
        canEditDraft: boolean;
        canManageSchedule: boolean;
        canManageAutomation: boolean;
        canDuplicate: boolean;
        canRetry: boolean;
        status?: string;
        translations: RecurringTranslations;
        customerTranslations: CustomerTranslations;
        catalogTranslations: CatalogTranslations;
    };

export default function EditRecurringTemplate(props: Props) {
    const { i18n } = usePage().props;

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
                                    status={
                                        props.template.state.toLowerCase() as
                                            | 'draft'
                                            | 'active'
                                            | 'paused'
                                            | 'completed'
                                    }
                                    label={
                                        props.translations.index.states[
                                            props.template.state
                                        ]
                                    }
                                />
                                <RecurringTemplateLifecycleActions
                                    state={props.template.state}
                                    editVersion={props.template.editVersion}
                                    urls={props.transitionUrls}
                                    duplicateUrl={props.duplicateUrl}
                                    retryUrl={props.retryUrl}
                                    duplicateCreationKey={
                                        props.duplicateCreationKey
                                    }
                                    canManageAutomation={
                                        props.canManageAutomation
                                    }
                                    canDuplicate={props.canDuplicate}
                                    canRetry={props.canRetry}
                                    labels={props.translations.lifecycle}
                                    closeLabel={
                                        i18n.common.accessibility
                                            .close_navigation
                                    }
                                />
                                {props.canDelete && (
                                    <RecurringTemplateDeleteDialog
                                        url={props.deleteUrl}
                                        highRisk={props.deletion.highRisk}
                                        guard={props.deletion.guard}
                                        labels={props.translations.deletion}
                                    />
                                )}
                            </>
                        }
                    />
                    {props.status && (
                        <SystemMessage title={props.status} tone="money" />
                    )}
                    <RecurringTemplateScheduleForm
                        key={`${props.template.id}:${props.template.editVersion}`}
                        template={props.template}
                        updateUrl={props.scheduleUpdateUrl}
                        canManage={props.canManageSchedule}
                        labels={props.translations.schedule}
                    />
                    <RecurringAutomaticEmailForm
                        key={`automation:${props.template.editVersion}`}
                        template={props.template}
                        updateUrl={props.automaticEmailUpdateUrl}
                        canManage={props.canManageAutomation}
                        labels={props.translations.automation}
                    />
                    <RecurringTemplateExecution
                        execution={props.template.execution}
                        labels={props.translations}
                    />
                    {props.canEditDraft ? (
                        <RecurringTemplateDraftEditor
                            {...props}
                            labels={props.translations.editor}
                            customerLabels={props.customerTranslations}
                            catalogLabels={props.catalogTranslations}
                        />
                    ) : (
                        <SystemMessage
                            title={props.translations.editor.content_locked}
                            tone="info"
                        />
                    )}
                </Stack>
            </PageFrame>
        </>
    );
}
