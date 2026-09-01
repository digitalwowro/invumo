import type { ReactNode } from 'react';
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
    QuoteInvoiceAllocation,
    QuoteLimits,
    QuoteProductDefaults,
    QuoteSourceOption,
    QuoteSourceUrls,
    QuoteTranslations,
} from '@/types/quote';

export type QuoteWorkspaceTab = 'build' | 'invoices' | 'sharing';

export const QUOTE_FORM_ID = 'quote-draft-editor';

export type QuoteEditPageProps = {
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

export type QuoteWorkspaceComposedProps = QuoteEditPageProps & {
    renderDeliveryPanel: (documentDirty: boolean) => ReactNode;
};
