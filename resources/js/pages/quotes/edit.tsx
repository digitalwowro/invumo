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
import { StatusBadge } from '@/components/domain/status-badge';
import { DocumentDeliveryPanel } from '@/features/delivery/components/document-delivery-panel';
import { QuoteDeleteDialog } from '@/features/quotes/components/quote-delete-dialog';
import { QuoteDraftEditor } from '@/features/quotes/components/quote-draft-editor';
import { QuoteInvoiceAllocationSection } from '@/features/quotes/components/quote-invoice-allocation';
import { QuoteLifecycleDialog } from '@/features/quotes/components/quote-lifecycle-dialog';
import type { CatalogTranslations } from '@/types/catalog';
import type { CustomerTranslations } from '@/types/customer';
import type { DependencyGuard } from '@/types/dependency-guard';
import type {
    DocumentDelivery,
    DocumentDeliveryTranslations,
} from '@/types/document-delivery';
import type {
    PublicDocumentLink,
    PublicDocumentTranslations,
} from '@/types/public-document';
import type {
    QuoteCatalogFormOptions,
    QuoteCurrencyOption,
    QuoteCustomerFormOptions,
    QuoteCustomerSelection,
    QuoteDraft,
    QuoteLimits,
    QuoteInvoiceAllocation,
    QuoteProductDefaults,
    QuoteSourceOption,
    QuoteSourceUrls,
    QuoteTranslations,
} from '@/types/quote';
import type { Status } from '@/types/status';

type Props = {
    quote: QuoteDraft;
    limits: QuoteLimits;
    updateUrl: string;
    lifecycleUrl: string;
    conversionUrl: string;
    conversionKey: string;
    invoiceAllocation: QuoteInvoiceAllocation;
    deletion: {
        url: string | null;
        highRisk: boolean;
        stateVersion: string;
        guard: DependencyGuard;
    };
    representationUrl: string;
    pdfUrl: string;
    publicLink: PublicDocumentLink;
    directDelivery: DocumentDelivery;
    indexUrl: string;
    quoteAbilities: { correctLifecycle: boolean; delete: boolean };
    sourceUrls: QuoteSourceUrls;
    inlineCustomerStoreUrl: string;
    inlineProductStoreUrl: string;
    inlineCreatedCustomer: QuoteCustomerSelection | null;
    inlineCreatedProduct: QuoteProductDefaults | null;
    sourceAbilities: { createCustomer: boolean; createProduct: boolean };
    currencyOptions: QuoteCurrencyOption[];
    languageOptions: QuoteSourceOption[];
    bankAccountOptions: QuoteSourceOption[];
    customerForm: QuoteCustomerFormOptions;
    catalogForm: QuoteCatalogFormOptions;
    status?: string;
    translations: QuoteTranslations;
    customerTranslations: CustomerTranslations;
    catalogTranslations: CatalogTranslations;
    publicDocumentTranslations: PublicDocumentTranslations;
    deliveryTranslations: DocumentDeliveryTranslations;
};

export default function EditQuote({
    quote,
    limits,
    updateUrl,
    lifecycleUrl,
    conversionUrl,
    conversionKey,
    invoiceAllocation,
    deletion,
    representationUrl,
    pdfUrl,
    publicLink,
    directDelivery,
    indexUrl,
    quoteAbilities,
    status,
    translations,
    customerTranslations,
    catalogTranslations,
    publicDocumentTranslations,
    deliveryTranslations,
    ...sourceProps
}: Props) {
    const [quoteDirty, setQuoteDirty] = useState(false);

    return (
        <>
            <Head title={`${translations.edit.head_title} ${quote.number}`} />
            <PageFrame width="full">
                <Stack gap="2xl">
                    <PageHeader
                        title={`${translations.edit.title} ${quote.number}`}
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
                                <StatusBadge
                                    status={
                                        quote.status.toLowerCase() as Status
                                    }
                                    label={
                                        translations.index.statuses[
                                            quote.status
                                        ]
                                    }
                                />
                                {quoteAbilities.correctLifecycle && (
                                    <QuoteLifecycleDialog
                                        lifecycle={quote.lifecycle}
                                        url={lifecycleUrl}
                                        labels={translations.lifecycle}
                                    />
                                )}
                                {quoteAbilities.delete && deletion.url && (
                                    <QuoteDeleteDialog
                                        url={deletion.url}
                                        highRisk={deletion.highRisk}
                                        stateVersion={deletion.stateVersion}
                                        guard={deletion.guard}
                                        labels={translations.deletion}
                                    />
                                )}
                            </>
                        }
                    />
                    {status && <SystemMessage title={status} tone="money" />}
                    <SystemMessage
                        title={
                            quote.currencyCode === null
                                ? translations.edit.currency_required
                                : `${quote.currencyCode} · ${quote.issueDate ?? ''}`
                        }
                        tone={
                            quote.currencyCode === null ? 'warning' : 'neutral'
                        }
                    />
                    <PublicDocumentLinkPanel
                        link={publicLink}
                        labels={publicDocumentTranslations.management}
                    />
                    <DocumentDeliveryPanel
                        delivery={directDelivery}
                        labels={deliveryTranslations}
                        documentDirty={quoteDirty}
                    />
                    <QuoteDraftEditor
                        quote={quote}
                        limits={limits}
                        updateUrl={updateUrl}
                        labels={translations.edit}
                        customerLabels={customerTranslations}
                        catalogLabels={catalogTranslations}
                        conversion={{
                            url: conversionUrl,
                            creationKey: conversionKey,
                            allocation: invoiceAllocation,
                        }}
                        conversionLabels={translations.conversion}
                        onDirtyChange={setQuoteDirty}
                        {...sourceProps}
                    />
                    <QuoteInvoiceAllocationSection
                        allocation={invoiceAllocation}
                        currencyCode={quote.currencyCode}
                        labels={translations}
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
