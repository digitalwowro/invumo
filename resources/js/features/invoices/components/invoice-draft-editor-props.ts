import type { CatalogTranslations } from '@/types/catalog';
import type { CustomerTranslations } from '@/types/customer';
import type { DocumentDraftCreation } from '@/types/document';
import type {
    InvoiceCatalogFormOptions,
    InvoiceCurrencyOption,
    InvoiceCustomerFormOptions,
    InvoiceCustomerSelection,
    InvoiceDraft,
    InvoiceLifecycleActions,
    InvoiceLimits,
    InvoiceProductDefaults,
    InvoiceSourceOption,
    InvoiceSourceUrls,
    InvoiceTranslations,
} from '@/types/invoice';

export type InvoiceDraftEditorProps = {
    invoice: InvoiceDraft;
    limits: InvoiceLimits;
    updateUrl: string;
    creation?: DocumentDraftCreation;
    issueUrl?: string;
    lifecycleActions?: InvoiceLifecycleActions;
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
    labels: InvoiceTranslations['edit'];
    issueLabels: InvoiceTranslations['issue'];
    lifecycleLabels: InvoiceTranslations['lifecycle'];
    customerLabels: CustomerTranslations;
    catalogLabels: CatalogTranslations;
    onDirtyChange?: (dirty: boolean) => void;
    onProcessingChange?: (processing: boolean) => void;
    onLineCountChange?: (count: number) => void;
    formId?: string;
    showActions?: boolean;
};
