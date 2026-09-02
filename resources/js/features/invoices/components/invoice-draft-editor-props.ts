import type { ReactNode } from 'react';
import type { CustomerTranslations } from '@/types/customer';
import type {
    DocumentDraftCreation,
    DocumentEditorFinancials,
} from '@/types/document';
import type {
    InvoiceCatalogFormOptions,
    InvoiceCurrencyOption,
    InvoiceCustomerFormOptions,
    InvoiceCustomerSelection,
    InvoiceDraft,
    InvoiceLifecycleActions,
    InvoiceLimits,
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
    inlineCreatedCustomer: InvoiceCustomerSelection | null;
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
    onDirtyChange?: (dirty: boolean) => void;
    onProcessingChange?: (processing: boolean) => void;
    onLineCountChange?: (count: number) => void;
    formId?: string;
    showActions?: boolean;
    workspaceAside?: (financials: DocumentEditorFinancials) => ReactNode;
};
