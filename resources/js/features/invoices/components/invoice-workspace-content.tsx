import type { Dispatch, SetStateAction } from 'react';
import { ActionLink } from '@/components/app/action-link';
import { SystemMessage } from '@/components/app/system-message';
import { PublicDocumentLinkPanel } from '@/components/domain/documents/public-document-link-panel';
import { TabsContent } from '@/components/ui/tabs';
import { InvoiceDraftEditor } from '@/features/invoices/components/invoice-draft-editor';
import { InvoiceTransactionsPanel } from '@/features/invoices/components/invoice-transactions-panel';
import { InvoiceWorkspaceSidebar } from '@/features/invoices/components/invoice-workspace-sidebar';
import { INVOICE_FORM_ID } from '@/features/invoices/components/invoice-workspace-types';
import type {
    InvoiceEditPageProps,
    InvoiceWorkspaceComposedProps,
    InvoiceWorkspaceTab,
} from '@/features/invoices/components/invoice-workspace-types';

type Props = InvoiceWorkspaceComposedProps & {
    dirty: boolean;
    setDirty: Dispatch<SetStateAction<boolean>>;
    setProcessing: Dispatch<SetStateAction<boolean>>;
    setLineCount: Dispatch<SetStateAction<number>>;
    setTab: Dispatch<SetStateAction<InvoiceWorkspaceTab>>;
};

export function InvoiceWorkspaceContent(props: Props) {
    const labels = props.translations;
    const language =
        props.languageOptions.find(
            (option) => option.value === props.invoice.documentLanguage,
        )?.label ?? props.invoice.documentLanguage;
    const latestDelivery = props.directDelivery.history[0] ?? null;
    const facts = documentFacts(props, language);
    const sharing = [
        {
            label: labels.workspace.public_link,
            value: props.publicDocumentTranslations.management.statuses[
                props.publicLink.status
            ],
        },
        {
            label: labels.workspace.email_delivery,
            value: latestDelivery
                ? props.deliveryTranslations.history.statuses[
                      latestDelivery.state
                  ]
                : labels.workspace.not_queued,
        },
        {
            label: labels.workspace.reminders,
            value:
                props.reminders.rules.length === 1
                    ? labels.workspace.reminder_count_one
                    : labels.workspace.reminder_count.replace(
                          ':count',
                          String(props.reminders.rules.length),
                      ),
        },
    ];

    return (
        <div className="grid min-w-0 gap-6 pt-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
            <main className="flex min-w-0 flex-col gap-5">
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
                        catalogLabels={props.catalogTranslations}
                        onDirtyChange={props.setDirty}
                        onProcessingChange={props.setProcessing}
                        onLineCountChange={props.setLineCount}
                        formId={INVOICE_FORM_ID}
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
                    className="mt-0 space-y-5 data-[state=inactive]:hidden"
                >
                    <PublicDocumentLinkPanel
                        link={props.publicLink}
                        labels={props.publicDocumentTranslations.management}
                    />
                    {props.renderDeliveryPanel(props.dirty)}
                    {props.reminderPanel}
                </TabsContent>
            </main>
            <InvoiceWorkspaceSidebar
                invoice={props.invoice}
                transactions={props.transactions}
                invoiceDirty={props.dirty}
                facts={facts}
                sharing={sharing}
                labels={labels}
                onOpenSharing={() => props.setTab('sharing')}
            />
        </div>
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

function documentFacts(props: InvoiceEditPageProps, language: string | null) {
    const labels = props.translations.workspace;

    return [
        {
            label: labels.customer,
            value: props.invoice.customer?.displayName ?? labels.not_available,
        },
        {
            label: labels.issue_date,
            value: props.invoice.issueDate ?? labels.not_available,
        },
        {
            label: labels.due_date,
            value: props.invoice.dueDate ?? labels.not_available,
        },
        {
            label: labels.payment_term,
            value:
                props.invoice.paymentTermDays === null
                    ? labels.not_available
                    : `${props.invoice.paymentTermDays} ${labels.days}`,
        },
        {
            label: labels.reference,
            value: props.invoice.customerReference ?? labels.not_available,
        },
        { label: labels.language, value: language ?? labels.not_available },
        {
            label: labels.bank_account,
            value: props.invoice.bankAccount?.label ?? labels.not_available,
        },
    ];
}
