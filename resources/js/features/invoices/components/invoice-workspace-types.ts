import type { ReactNode } from 'react';
import type { CatalogTranslations } from '@/types/catalog';
import type { CustomerTranslations } from '@/types/customer';
import type { DependencyGuard } from '@/types/dependency-guard';
import type {
    DocumentDelivery,
    DocumentDeliveryTranslations,
} from '@/types/document-delivery';
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
import type { InvoiceReminder } from '@/types/reminder';

export type InvoiceWorkspaceTab = 'build' | 'money' | 'sharing';

export const INVOICE_FORM_ID = 'invoice-draft-editor';

export type InvoiceEditPageProps = {
    initialTab: InvoiceWorkspaceTab;
    invoice: InvoiceDraft;
    transactions: InvoiceTransactions;
    lifecycleActions: InvoiceLifecycleActions;
    limits: InvoiceLimits;
    updateUrl: string;
    issueUrl: string;
    representationUrl: string;
    pdfUrl: string;
    publicLink: PublicDocumentLink;
    directDelivery: DocumentDelivery;
    reminders: InvoiceReminder;
    recurringAutomation: {
        currencyReviewRequired: boolean;
        templateUrl: string | null;
    };
    deletion: {
        url: string | null;
        highRisk: boolean;
        stateVersion: string;
        guard: DependencyGuard;
    };
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
    deliveryTranslations: DocumentDeliveryTranslations;
};

export type InvoiceWorkspaceComposedProps = InvoiceEditPageProps & {
    renderDeliveryPanel: (documentDirty: boolean) => ReactNode;
    reminderPanel: ReactNode;
};
