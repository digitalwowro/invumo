import { Head } from '@inertiajs/react';
import { Download, Eye } from 'lucide-react';
import { useState } from 'react';
import { ActionLink } from '@/components/app/action-link';
import { Cluster, Stack } from '@/components/app/layout';
import { PageFrame } from '@/components/app/page-frame';
import { PageHeader } from '@/components/app/page-header';
import { SystemMessage } from '@/components/app/system-message';
import { InvoiceStatusBadges } from '@/components/domain/invoice-status-badges';
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
    InvoiceProductDefaults,
    InvoiceSourceOption,
    InvoiceSourceUrls,
    InvoiceTranslations,
} from '@/types/invoice';
import type { InvoiceTransactions } from '@/types/invoice-transaction';

type Props = {
    invoice: InvoiceDraft;
    transactions: InvoiceTransactions;
    limits: InvoiceLimits;
    updateUrl: string;
    issueUrl: string;
    representationUrl: string;
    pdfUrl: string;
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
};

export default function EditInvoice({
    invoice,
    transactions,
    limits,
    updateUrl,
    issueUrl,
    representationUrl,
    pdfUrl,
    indexUrl,
    status,
    translations,
    customerTranslations,
    catalogTranslations,
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
                                <ActionLink href={pdfUrl} variant="secondary">
                                    <Download aria-hidden="true" />
                                    {translations.representation.download_pdf}
                                </ActionLink>
                                <InvoiceStatusBadges
                                    lifecycle={invoice.lifecycle}
                                    paymentState={invoice.paymentState}
                                    overdue={invoice.isOverdue}
                                    labels={translations.index.statuses}
                                />
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
                    <InvoiceDraftEditor
                        key={invoice.editVersion}
                        invoice={invoice}
                        limits={limits}
                        updateUrl={updateUrl}
                        issueUrl={issueUrl}
                        labels={translations.edit}
                        issueLabels={translations.issue}
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
