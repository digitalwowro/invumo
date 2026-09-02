import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { DiscardChangesDialog } from '@/components/app/discard-changes-dialog';
import { SubmitButton } from '@/components/app/form-actions';
import { Stack } from '@/components/app/layout';
import {
    ResourceWorkspace,
    ResourceWorkspaceHeader,
} from '@/components/app/resource-workspace';
import { QuoteDraftEditor } from '@/features/quotes/components/quote-draft-editor';
import type { QuoteDraftEditorProps } from '@/features/quotes/components/quote-draft-editor-props';
import { QuoteSummaryCard } from '@/features/quotes/components/quote-workspace-sidebar';
import type { CustomerTranslations } from '@/types/customer';
import type { DocumentDraftCreation } from '@/types/document';
import type { QuoteTranslations } from '@/types/quote';

const FORM_ID = 'new-quote-editor';

type Props = Omit<
    QuoteDraftEditorProps,
    | 'updateUrl'
    | 'creation'
    | 'labels'
    | 'customerLabels'
    | 'conversion'
    | 'conversionLabels'
> & {
    creation: DocumentDraftCreation;
    indexUrl: string;
    translations: QuoteTranslations;
    customerTranslations: CustomerTranslations;
};

export default function CreateQuote(props: Props) {
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
                    <QuoteDraftEditor
                        {...props}
                        updateUrl=""
                        creation={props.creation}
                        labels={props.translations.edit}
                        customerLabels={props.customerTranslations}
                        conversionLabels={props.translations.conversion}
                        formId={FORM_ID}
                        showActions={false}
                        onDirtyChange={setDirty}
                        onProcessingChange={setProcessing}
                        workspaceAside={(financials) => (
                            <QuoteSummaryCard
                                quote={props.quote}
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
