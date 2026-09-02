import type { ReactNode } from 'react';
import type { CustomerTranslations } from '@/types/customer';
import type {
    DocumentDraftCreation,
    DocumentEditorFinancials,
} from '@/types/document';
import type {
    QuoteCatalogFormOptions,
    QuoteConversionControl,
    QuoteCurrencyOption,
    QuoteCustomerFormOptions,
    QuoteCustomerSelection,
    QuoteDraft,
    QuoteLimits,
    QuoteSourceOption,
    QuoteSourceUrls,
    QuoteTranslations,
} from '@/types/quote';

export type QuoteDraftEditorProps = {
    quote: QuoteDraft;
    limits: QuoteLimits;
    updateUrl: string;
    creation?: DocumentDraftCreation;
    sourceUrls: QuoteSourceUrls;
    inlineCustomerStoreUrl: string;
    inlineCreatedCustomer: QuoteCustomerSelection | null;
    sourceAbilities: { createCustomer: boolean; createProduct: boolean };
    currencyOptions: QuoteCurrencyOption[];
    languageOptions: QuoteSourceOption[];
    bankAccountOptions: QuoteSourceOption[];
    customerForm: QuoteCustomerFormOptions;
    catalogForm: QuoteCatalogFormOptions;
    labels: QuoteTranslations['edit'];
    customerLabels: CustomerTranslations;
    conversion?: QuoteConversionControl;
    conversionLabels: QuoteTranslations['conversion'];
    onDirtyChange?: (dirty: boolean) => void;
    onProcessingChange?: (processing: boolean) => void;
    onLineCountChange?: (count: number) => void;
    formId?: string;
    showActions?: boolean;
    workspaceAside?: (financials: DocumentEditorFinancials) => ReactNode;
};
