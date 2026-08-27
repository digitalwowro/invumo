import { Head } from '@inertiajs/react';
import { Download, Eye } from 'lucide-react';
import { useState } from 'react';
import { ActionLink } from '@/components/app/action-link';
import { DownloadLink } from '@/components/app/download-link';
import { Cluster, Stack } from '@/components/app/layout';
import { PageFrame } from '@/components/app/page-frame';
import { PageHeader } from '@/components/app/page-header';
import { SystemMessage } from '@/components/app/system-message';
import { PublicDocumentLinkPanel } from '@/components/domain/documents/public-document-link-panel';
import { InvoiceStatusBadges } from '@/components/domain/invoice-status-badges';
import { InvoiceDeleteDialog } from '@/features/invoices/components/invoice-delete-dialog';
import { InvoiceDraftEditor } from '@/features/invoices/components/invoice-draft-editor';
import { InvoiceTransactionsPanel } from '@/features/invoices/components/invoice-transactions-panel';
import type { CatalogTranslations } from '@/types/catalog';
import type { CustomerTranslations } from '@/types/customer';
import type {
    InvoiceCatalogFormOptions,
    InvoiceCurrencyOption,
    InvoiceCustomerFormOptions,
    InvoiceCustomerSelection,
    InvoiceDraft,
    InvoiceLimits,
    InvoiceLifecycleActions,
    InvoiceProductDefaults,
    InvoiceSourceOption,
    InvoiceSourceUrls,
    InvoiceTranslations,
} from '@/types/invoice';
import type { InvoiceTransactions } from '@/types/invoice-transaction';
import type {
    PublicDocumentLink,
    PublicDocumentTranslations,
} from '@/types/public-document';

type Props = {
    invoice: InvoiceDraft;
    transactions: InvoiceTransactions;
    lifecycleActions: InvoiceLifecycleActions;
    limits: InvoiceLimits;
    updateUrl: string;
    issueUrl: string;
    representationUrl: string;
    pdfUrl: string;
    publicLink: PublicDocumentLink;
    deletion: { url: string | null; highRisk: boolean };
    indexUrl: string;
    sourceUrls: InvoiceSourceUrls;
    inlineCustomerStoreUrl: string;
    inlineProductStoreUrl: string;
    inlineCreatedCustomer: InvoiceCustomerSelection | null;
    inlineCreatedProduct: InvoiceProductDefaults | null;
    sourceAbilities: { createCustomer: boolean; createProduct: boolean };
    currencyOptions: InvoiceCurrencyOption[];
    languageOptions: InvoiceSourceOption[];
    bankAccountOptions: InvoiceSourceOption[];
    customerForm: InvoiceCustomerFormOptions;
    catalogForm: InvoiceCatalogFormOptions;
    status?: string;
    translations: InvoiceTranslations;
    customerTranslations: CustomerTranslations;
    catalogTranslations: CatalogTranslations;
    publicDocumentTranslations: PublicDocumentTranslations;
};

export default function EditInvoice({
    invoice,
    transactions,
    lifecycleActions,
    limits,
    updateUrl,
    issueUrl,
    representationUrl,
    pdfUrl,
    publicLink,
    deletion,
    indexUrl,
    status,
    translations,
    customerTranslations,
    catalogTranslations,
    publicDocumentTranslations,
    ...sourceProps
}: Props) {
    const [invoiceDirty, setInvoiceDirty] = useState(false);

    return (
        <>
            <Head title={`${translations.edit.head_title} ${invoice.number}`} />
            <PageFrame width="full">
                <Stack gap="2xl">
                    <PageHeader
                        title={`${translations.edit.title} ${invoice.number}`}
                        subtitle={translations.edit.description}
                        actions={
                            <>
                                <ActionLink
                                    href={representationUrl}
                                    variant="secondary"
                                >
                                    <Eye aria-hidden="true" />
                                    {translations.representation.view}
                                </ActionLink>
                                <DownloadLink
                                    href={pdfUrl}
                                    testId="pdf-download"
                                >
                                    <Download aria-hidden="true" />
                                    {translations.representation.download_pdf}
                                </DownloadLink>
                                <InvoiceStatusBadges
                                    lifecycle={invoice.lifecycle}
                                    paymentState={invoice.paymentState}
                                    overdue={invoice.isOverdue}
                                    labels={translations.index.statuses}
                                />
                                {deletion.url && (
                                    <InvoiceDeleteDialog
                                        url={deletion.url}
                                        number={invoice.number}
                                        highRisk={deletion.highRisk}
                                        labels={translations.deletion}
                                    />
                                )}
                            </>
                        }
                    />
                    {status && <SystemMessage title={status} tone="money" />}
                    <SystemMessage
                        title={
                            invoice.currencyCode === null
                                ? translations.edit.currency_required
                                : `${invoice.currencyCode} · ${invoice.issueDate ?? ''}`
                        }
                        tone={
                            invoice.currencyCode === null
                                ? 'warning'
                                : 'neutral'
                        }
                    />
                    <PublicDocumentLinkPanel
                        link={publicLink}
                        labels={publicDocumentTranslations.management}
                    />
                    <InvoiceDraftEditor
                        key={invoice.editVersion}
                        invoice={invoice}
                        limits={limits}
                        updateUrl={updateUrl}
                        issueUrl={issueUrl}
                        lifecycleActions={lifecycleActions}
                        labels={translations.edit}
                        issueLabels={translations.issue}
                        lifecycleLabels={translations.lifecycle}
                        customerLabels={customerTranslations}
                        catalogLabels={catalogTranslations}
                        onDirtyChange={setInvoiceDirty}
                        {...sourceProps}
                    />
                    <InvoiceTransactionsPanel
                        lifecycle={invoice.lifecycle}
                        currencyCode={invoice.currencyCode}
                        transactions={transactions}
                        labels={translations.transactions}
                        invoiceDirty={invoiceDirty}
                    />
                    <Cluster>
                        <ActionLink href={indexUrl} variant="ghost">
                            {translations.index.title}
                        </ActionLink>
                    </Cluster>
                </Stack>
            </PageFrame>
        </>
    );
}
