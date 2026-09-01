import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { ActionLink } from '@/components/app/action-link';
import { SubmitButton } from '@/components/app/form-actions';
import { Stack } from '@/components/app/layout';
import { PageFrame } from '@/components/app/page-frame';
import { PageHeader } from '@/components/app/page-header';
import { QuoteDraftEditor } from '@/features/quotes/components/quote-draft-editor';
import type { QuoteDraftEditorProps } from '@/features/quotes/components/quote-draft-editor-props';
import type { CatalogTranslations } from '@/types/catalog';
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
    | 'catalogLabels'
    | 'conversion'
    | 'conversionLabels'
> & {
    creation: DocumentDraftCreation;
    indexUrl: string;
    translations: QuoteTranslations;
    customerTranslations: CustomerTranslations;
    catalogTranslations: CatalogTranslations;
};

export default function CreateQuote(props: Props) {
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
                    <QuoteDraftEditor
                        {...props}
                        updateUrl=""
                        creation={props.creation}
                        labels={props.translations.edit}
                        customerLabels={props.customerTranslations}
                        catalogLabels={props.catalogTranslations}
                        conversionLabels={props.translations.conversion}
                        formId={FORM_ID}
                        showActions={false}
                        onProcessingChange={setProcessing}
                    />
                </Stack>
            </PageFrame>
        </>
    );
}
