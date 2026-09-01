import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { ActionLink } from '@/components/app/action-link';
import { SubmitButton } from '@/components/app/form-actions';
import { Stack } from '@/components/app/layout';
import { PageFrame } from '@/components/app/page-frame';
import { PageHeader } from '@/components/app/page-header';
import { InvoiceDraftEditor } from '@/features/invoices/components/invoice-draft-editor';
import type { InvoiceDraftEditorProps } from '@/features/invoices/components/invoice-draft-editor-props';
import type { CatalogTranslations } from '@/types/catalog';
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
    | 'catalogLabels'
> & {
    creation: DocumentDraftCreation;
    indexUrl: string;
    translations: InvoiceTranslations;
    customerTranslations: CustomerTranslations;
    catalogTranslations: CatalogTranslations;
};

export default function CreateInvoice(props: Props) {
    const [processing, setProcessing] = useState(false);

    return (
        <>
            <Head title={props.translations.create.head_title} />
            <PageFrame width="full">
                <Stack gap="2xl">
                    <PageHeader
                        title={props.translations.create.title}
                        subtitle={props.translations.create.description}
                        actions={
                            <>
                                <ActionLink
                                    href={props.indexUrl}
                                    variant="secondary"
                                >
                                    {props.translations.edit.cancel}
                                </ActionLink>
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
                        catalogLabels={props.catalogTranslations}
                        formId={FORM_ID}
                        showActions={false}
                        onProcessingChange={setProcessing}
                    />
                </Stack>
            </PageFrame>
        </>
    );
}
