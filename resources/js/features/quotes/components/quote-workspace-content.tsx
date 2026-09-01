import type { Dispatch, SetStateAction } from 'react';
import { SystemMessage } from '@/components/app/system-message';
import { PublicDocumentLinkPanel } from '@/components/domain/documents/public-document-link-panel';
import { TabsContent } from '@/components/ui/tabs';
import { QuoteDraftEditor } from '@/features/quotes/components/quote-draft-editor';
import { QuoteInvoiceAllocationSection } from '@/features/quotes/components/quote-invoice-allocation';
import { QuoteWorkspaceSidebar } from '@/features/quotes/components/quote-workspace-sidebar';
import { QUOTE_FORM_ID } from '@/features/quotes/components/quote-workspace-types';
import type {
    QuoteEditPageProps,
    QuoteWorkspaceComposedProps,
    QuoteWorkspaceTab,
} from '@/features/quotes/components/quote-workspace-types';

type Props = QuoteWorkspaceComposedProps & {
    dirty: boolean;
    setDirty: Dispatch<SetStateAction<boolean>>;
    setProcessing: Dispatch<SetStateAction<boolean>>;
    setLineCount: Dispatch<SetStateAction<number>>;
    setTab: Dispatch<SetStateAction<QuoteWorkspaceTab>>;
};

export function QuoteWorkspaceContent(props: Props) {
    const labels = props.translations;
    const language =
        props.languageOptions.find(
            (option) => option.value === props.quote.documentLanguage,
        )?.label ?? props.quote.documentLanguage;
    const latestDelivery = props.directDelivery.history[0] ?? null;

    return (
        <div className="grid min-w-0 gap-6 pt-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
            <main className="flex min-w-0 flex-col gap-5">
                {props.status && (
                    <SystemMessage title={props.status} tone="money" />
                )}
                {props.quote.currencyCode === null && (
                    <SystemMessage
                        title={labels.edit.currency_required}
                        tone="warning"
                    />
                )}
                <TabsContent
                    value="build"
                    forceMount
                    className="mt-0 data-[state=inactive]:hidden"
                >
                    <QuoteDraftEditor
                        key={props.quote.editVersion}
                        quote={props.quote}
                        limits={props.limits}
                        updateUrl={props.updateUrl}
                        labels={labels.edit}
                        customerLabels={props.customerTranslations}
                        catalogLabels={props.catalogTranslations}
                        conversion={{
                            url: props.conversionUrl,
                            creationKey: props.conversionKey,
                            allocation: props.invoiceAllocation,
                        }}
                        conversionLabels={labels.conversion}
                        onDirtyChange={props.setDirty}
                        onProcessingChange={props.setProcessing}
                        onLineCountChange={props.setLineCount}
                        formId={QUOTE_FORM_ID}
                        showActions={false}
                        sourceUrls={props.sourceUrls}
                        inlineCustomerStoreUrl={props.inlineCustomerStoreUrl}
                        inlineProductStoreUrl={props.inlineProductStoreUrl}
                        inlineCreatedCustomer={props.inlineCreatedCustomer}
                        inlineCreatedProduct={props.inlineCreatedProduct}
                        sourceAbilities={props.sourceAbilities}
                        currencyOptions={props.currencyOptions}
                        languageOptions={props.languageOptions}
                        bankAccountOptions={props.bankAccountOptions}
                        customerForm={props.customerForm}
                        catalogForm={props.catalogForm}
                    />
                </TabsContent>
                <TabsContent
                    value="invoices"
                    forceMount
                    className="mt-0 data-[state=inactive]:hidden"
                >
                    <QuoteInvoiceAllocationSection
                        allocation={props.invoiceAllocation}
                        currencyCode={props.quote.currencyCode}
                        labels={labels}
                    />
                </TabsContent>
                <TabsContent
                    value="sharing"
                    forceMount
                    className="mt-0 flex flex-col gap-5 data-[state=inactive]:hidden"
                >
                    <PublicDocumentLinkPanel
                        link={props.publicLink}
                        labels={props.publicDocumentTranslations.management}
                    />
                    {props.renderDeliveryPanel(props.dirty)}
                </TabsContent>
            </main>
            <QuoteWorkspaceSidebar
                quote={props.quote}
                allocation={props.invoiceAllocation}
                facts={documentFacts(props, language)}
                sharing={[
                    {
                        label: labels.workspace.public_link,
                        value: props.publicDocumentTranslations.management
                            .statuses[props.publicLink.status],
                    },
                    {
                        label: labels.workspace.email_delivery,
                        value: latestDelivery
                            ? props.deliveryTranslations.history.statuses[
                                  latestDelivery.state
                              ]
                            : labels.workspace.not_queued,
                    },
                ]}
                labels={labels}
                onOpenSharing={() => props.setTab('sharing')}
            />
        </div>
    );
}

function documentFacts(props: QuoteEditPageProps, language: string | null) {
    const labels = props.translations.workspace;

    return [
        {
            label: labels.customer,
            value: props.quote.customer?.displayName ?? labels.not_available,
        },
        {
            label: labels.issue_date,
            value: props.quote.issueDate ?? labels.not_available,
        },
        {
            label: labels.valid_until,
            value: props.quote.validUntil ?? labels.not_available,
        },
        {
            label: labels.validity,
            value:
                props.quote.validityDays === null
                    ? labels.not_available
                    : `${props.quote.validityDays} ${labels.days}`,
        },
        {
            label: labels.reference,
            value: props.quote.customerReference ?? labels.not_available,
        },
        { label: labels.language, value: language ?? labels.not_available },
        {
            label: labels.bank_account,
            value: props.quote.bankAccount?.label ?? labels.not_available,
        },
    ];
}
