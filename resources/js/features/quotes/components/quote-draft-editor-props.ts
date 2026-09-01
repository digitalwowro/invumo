import type { CatalogTranslations } from '@/types/catalog';
import type { CustomerTranslations } from '@/types/customer';
import type { DocumentDraftCreation } from '@/types/document';
import type {
    QuoteCatalogFormOptions,
    QuoteConversionControl,
    QuoteCurrencyOption,
    QuoteCustomerFormOptions,
    QuoteCustomerSelection,
    QuoteDraft,
    QuoteLimits,
    QuoteProductDefaults,
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
    inlineProductStoreUrl: string;
    inlineCreatedCustomer: QuoteCustomerSelection | null;
    inlineCreatedProduct: QuoteProductDefaults | null;
    sourceAbilities: { createCustomer: boolean; createProduct: boolean };
    currencyOptions: QuoteCurrencyOption[];
    languageOptions: QuoteSourceOption[];
    bankAccountOptions: QuoteSourceOption[];
    customerForm: QuoteCustomerFormOptions;
    catalogForm: QuoteCatalogFormOptions;
    labels: QuoteTranslations['edit'];
    customerLabels: CustomerTranslations;
    catalogLabels: CatalogTranslations;
    conversion?: QuoteConversionControl;
    conversionLabels: QuoteTranslations['conversion'];
    onDirtyChange?: (dirty: boolean) => void;
    onProcessingChange?: (processing: boolean) => void;
    onLineCountChange?: (count: number) => void;
    formId?: string;
    showActions?: boolean;
};
