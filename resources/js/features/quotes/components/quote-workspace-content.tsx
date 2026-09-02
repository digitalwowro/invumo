import type { Dispatch, SetStateAction } from 'react';
import { SystemMessage } from '@/components/app/system-message';
import { PublicDocumentLinkPanel } from '@/components/domain/documents/public-document-link-panel';
import { TabsContent } from '@/components/ui/tabs';
import { QuoteDraftEditor } from '@/features/quotes/components/quote-draft-editor';
import { QuoteInvoiceAllocationSection } from '@/features/quotes/components/quote-invoice-allocation';
import { QuoteSummaryCard } from '@/features/quotes/components/quote-workspace-sidebar';
import { QUOTE_FORM_ID } from '@/features/quotes/components/quote-workspace-types';
import type {
    QuoteWorkspaceComposedProps,
    QuoteWorkspaceTab,
} from '@/features/quotes/components/quote-workspace-types';

type Props = QuoteWorkspaceComposedProps & {
    tab: QuoteWorkspaceTab;
    dirty: boolean;
    setDirty: Dispatch<SetStateAction<boolean>>;
    setProcessing: Dispatch<SetStateAction<boolean>>;
    setLineCount: Dispatch<SetStateAction<number>>;
    setTab: Dispatch<SetStateAction<QuoteWorkspaceTab>>;
};

export function QuoteWorkspaceContent(props: Props) {
    const labels = props.translations;

    return (
        <main className="flex min-w-0 flex-col gap-5 pt-6">
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
                    inlineCreatedCustomer={props.inlineCreatedCustomer}
                    sourceAbilities={props.sourceAbilities}
                    currencyOptions={props.currencyOptions}
                    languageOptions={props.languageOptions}
                    bankAccountOptions={props.bankAccountOptions}
                    customerForm={props.customerForm}
                    catalogForm={props.catalogForm}
                    workspaceAside={
                        props.tab === 'build'
                            ? (financials) => (
                                  <QuoteSummaryCard
                                      quote={props.quote}
                                      allocation={props.invoiceAllocation}
                                      labels={labels}
                                      financials={financials}
                                  />
                              )
                            : undefined
                    }
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
    );
}
