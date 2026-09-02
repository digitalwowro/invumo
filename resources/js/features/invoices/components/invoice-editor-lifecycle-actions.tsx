import { DiscardChangesDialog } from '@/components/app/discard-changes-dialog';
import { FormActions, SaveButton } from '@/components/app/form-actions';
import { SystemMessage } from '@/components/app/system-message';
import { InvoiceIssueDialog } from '@/features/invoices/components/invoice-issue-dialog';
import { InvoiceLifecycleDialog } from '@/features/invoices/components/invoice-lifecycle-dialog';
import type { DocumentResetLabels } from '@/types/document';
import type {
    InvoiceLifecycleActions,
    InvoiceTranslations,
} from '@/types/invoice';

type Props = {
    lifecycle: 'DRAFT' | 'ISSUED' | 'CANCELLED';
    lifecycleActions: InvoiceLifecycleActions;
    issueUrl: string;
    editVersion: number;
    dirty: boolean;
    processing: boolean;
    saveLabel: string;
    issueLabels: InvoiceTranslations['issue'];
    lifecycleLabels: InvoiceTranslations['lifecycle'];
    formId?: string;
    separated?: boolean;
    showStateMessage?: boolean;
    resetLabels?: DocumentResetLabels;
};

export function InvoiceEditorLifecycleActions(props: Props) {
    const actionDisabled = props.dirty || props.processing;

    return (
        <>
            {props.showStateMessage !== false &&
                props.lifecycle === 'ISSUED' &&
                props.lifecycleActions.state === 'OWNER_ADMIN_REQUIRED' && (
                    <SystemMessage
                        title={props.lifecycleActions.stateTitle}
                        description={props.lifecycleActions.stateDescription}
                        tone="warning"
                    />
                )}
            <FormActions separated={props.separated ?? true}>
                {props.formId && props.resetLabels ? (
                    <DiscardChangesDialog
                        dirty={props.dirty}
                        processing={props.processing}
                        form={props.formId}
                        mode="discard"
                        labels={props.resetLabels}
                    />
                ) : null}
                <SaveButton
                    processing={props.processing}
                    dirty={props.dirty}
                    testId="save-invoice"
                    form={props.formId}
                >
                    {props.saveLabel}
                </SaveButton>
                {props.lifecycle === 'DRAFT' && (
                    <InvoiceIssueDialog
                        key={props.editVersion}
                        url={props.issueUrl}
                        editVersion={props.editVersion}
                        labels={props.issueLabels}
                        disabled={actionDisabled}
                    />
                )}
                {props.lifecycle === 'ISSUED' &&
                    props.lifecycleActions.cancelUrl && (
                        <InvoiceLifecycleDialog
                            key={`cancel-${props.editVersion}`}
                            action="cancel"
                            url={props.lifecycleActions.cancelUrl}
                            editVersion={props.editVersion}
                            workflow={props.lifecycleActions}
                            labels={props.lifecycleLabels}
                            disabled={actionDisabled}
                        />
                    )}
                {props.lifecycle === 'CANCELLED' &&
                    props.lifecycleActions.reopenUrl && (
                        <InvoiceLifecycleDialog
                            key={`reopen-${props.editVersion}`}
                            action="reopen"
                            url={props.lifecycleActions.reopenUrl}
                            editVersion={props.editVersion}
                            workflow={props.lifecycleActions}
                            labels={props.lifecycleLabels}
                            disabled={actionDisabled}
                        />
                    )}
            </FormActions>
        </>
    );
}
