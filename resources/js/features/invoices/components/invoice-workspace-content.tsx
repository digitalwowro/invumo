import type { Dispatch, SetStateAction } from 'react';
import { ActionLink } from '@/components/app/action-link';
import { SystemMessage } from '@/components/app/system-message';
import { PublicDocumentLinkPanel } from '@/components/domain/documents/public-document-link-panel';
import { TabsContent } from '@/components/ui/tabs';
import { InvoiceDraftEditor } from '@/features/invoices/components/invoice-draft-editor';
import { InvoiceTransactionsPanel } from '@/features/invoices/components/invoice-transactions-panel';
import { InvoiceBalanceCard } from '@/features/invoices/components/invoice-workspace-sidebar';
import { INVOICE_FORM_ID } from '@/features/invoices/components/invoice-workspace-types';
import type {
    InvoiceEditPageProps,
    InvoiceWorkspaceComposedProps,
    InvoiceWorkspaceTab,
} from '@/features/invoices/components/invoice-workspace-types';

type Props = InvoiceWorkspaceComposedProps & {
    tab: InvoiceWorkspaceTab;
    dirty: boolean;
    setDirty: Dispatch<SetStateAction<boolean>>;
    setProcessing: Dispatch<SetStateAction<boolean>>;
    setLineCount: Dispatch<SetStateAction<number>>;
    setTab: Dispatch<SetStateAction<InvoiceWorkspaceTab>>;
};

export function InvoiceWorkspaceContent(props: Props) {
    const labels = props.translations;

    return (
        <main className="flex min-w-0 flex-col gap-5 pt-6">
            <WorkspaceMessages {...props} />
            <TabsContent
                value="build"
                forceMount
                className="mt-0 data-[state=inactive]:hidden"
            >
                <InvoiceDraftEditor
                    key={props.invoice.editVersion}
                    invoice={props.invoice}
                    limits={props.limits}
                    updateUrl={props.updateUrl}
                    issueUrl={props.issueUrl}
                    lifecycleActions={props.lifecycleActions}
                    labels={labels.edit}
                    issueLabels={labels.issue}
                    lifecycleLabels={labels.lifecycle}
                    customerLabels={props.customerTranslations}
                    onDirtyChange={props.setDirty}
                    onProcessingChange={props.setProcessing}
                    onLineCountChange={props.setLineCount}
                    formId={INVOICE_FORM_ID}
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
                                  <InvoiceBalanceCard
                                      invoice={props.invoice}
                                      transactions={props.transactions}
                                      invoiceDirty={props.dirty}
                                      labels={labels}
                                      financials={financials}
                                  />
                              )
                            : undefined
                    }
                />
            </TabsContent>
            <TabsContent
                value="money"
                forceMount
                className="mt-0 data-[state=inactive]:hidden"
            >
                <InvoiceTransactionsPanel
                    lifecycle={props.invoice.lifecycle}
                    currencyCode={props.invoice.currencyCode}
                    transactions={props.transactions}
                    labels={labels.transactions}
                    invoiceDirty={props.dirty}
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
                {props.reminderPanel}
            </TabsContent>
        </main>
    );
}

function WorkspaceMessages(props: InvoiceEditPageProps) {
    const labels = props.translations;

    return (
        <>
            {props.status && (
                <SystemMessage title={props.status} tone="money" />
            )}
            {props.recurringAutomation.currencyReviewRequired && (
                <SystemMessage
                    title={labels.recurring.review_title}
                    description={labels.recurring.review_description}
                    tone="warning"
                    action={
                        props.recurringAutomation.templateUrl ? (
                            <ActionLink
                                href={props.recurringAutomation.templateUrl}
                                variant="secondary"
                            >
                                {labels.recurring.open_template}
                            </ActionLink>
                        ) : undefined
                    }
                />
            )}
            {props.invoice.currencyCode === null && (
                <SystemMessage
                    title={labels.edit.currency_required}
                    tone="warning"
                />
            )}
            {props.invoice.lifecycle === 'ISSUED' &&
                props.lifecycleActions.state === 'OWNER_ADMIN_REQUIRED' && (
                    <SystemMessage
                        title={props.lifecycleActions.stateTitle}
                        description={props.lifecycleActions.stateDescription}
                        tone="warning"
                    />
                )}
        </>
    );
}
