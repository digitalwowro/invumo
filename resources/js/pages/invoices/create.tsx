import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { DiscardChangesDialog } from '@/components/app/discard-changes-dialog';
import { SubmitButton } from '@/components/app/form-actions';
import { Stack } from '@/components/app/layout';
import {
    ResourceWorkspace,
    ResourceWorkspaceHeader,
} from '@/components/app/resource-workspace';
import { InvoiceDraftEditor } from '@/features/invoices/components/invoice-draft-editor';
import type { InvoiceDraftEditorProps } from '@/features/invoices/components/invoice-draft-editor-props';
import { InvoiceBalanceCard } from '@/features/invoices/components/invoice-workspace-sidebar';
import type { CustomerTranslations } from '@/types/customer';
import type { DocumentDraftCreation } from '@/types/document';
import type { InvoiceTranslations } from '@/types/invoice';

const FORM_ID = 'new-invoice-editor';

type Props = Omit<
    InvoiceDraftEditorProps,
    | 'updateUrl'
    | 'creation'
    | 'issueUrl'
    | 'lifecycleActions'
    | 'labels'
    | 'issueLabels'
    | 'lifecycleLabels'
    | 'customerLabels'
> & {
    creation: DocumentDraftCreation;
    indexUrl: string;
    translations: InvoiceTranslations;
    customerTranslations: CustomerTranslations;
};

export default function CreateInvoice(props: Props) {
    const [dirty, setDirty] = useState(false);
    const [processing, setProcessing] = useState(false);

    return (
        <>
            <Head title={props.translations.create.head_title} />
            <ResourceWorkspace>
                <Stack gap="2xl">
                    <ResourceWorkspaceHeader
                        breadcrumbs={[
                            {
                                title: props.translations.index.title,
                                href: props.indexUrl,
                            },
                            {
                                title: props.translations.create.title,
                                href: props.indexUrl,
                            },
                        ]}
                        title={props.translations.create.title}
                        description={props.translations.create.description}
                        actions={
                            <>
                                <DiscardChangesDialog
                                    dirty={dirty}
                                    processing={processing}
                                    form={FORM_ID}
                                    mode="clear"
                                    labels={props.translations.edit}
                                />
                                <SubmitButton
                                    form={FORM_ID}
                                    processing={processing}
                                >
                                    {props.translations.create.submit}
                                </SubmitButton>
                            </>
                        }
                    />
                    <InvoiceDraftEditor
                        {...props}
                        updateUrl=""
                        creation={props.creation}
                        labels={props.translations.edit}
                        issueLabels={props.translations.issue}
                        lifecycleLabels={props.translations.lifecycle}
                        customerLabels={props.customerTranslations}
                        formId={FORM_ID}
                        showActions={false}
                        onDirtyChange={setDirty}
                        onProcessingChange={setProcessing}
                        workspaceAside={(financials) => (
                            <InvoiceBalanceCard
                                invoice={props.invoice}
                                invoiceDirty={financials.dirty}
                                labels={props.translations}
                                financials={financials}
                            />
                        )}
                    />
                </Stack>
            </ResourceWorkspace>
        </>
    );
}
