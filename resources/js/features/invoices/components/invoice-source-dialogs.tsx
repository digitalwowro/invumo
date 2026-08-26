import type { Page } from '@inertiajs/core';
import { DocumentSourceDialogs } from '@/components/domain/documents/document-source-dialogs';
import type { CatalogTranslations } from '@/types/catalog';
import type { CustomerTranslations } from '@/types/customer';
import type {
    InvoiceCatalogFormOptions,
    InvoiceCustomerFormOptions,
    InvoiceCustomerSelection,
    InvoiceProductDefaults,
    InvoiceSourceUrls,
    InvoiceTranslations,
} from '@/types/invoice';

type Props = {
    customerOpen: boolean;
    customerCreatorOpen: boolean;
    productOpen: boolean;
    productCreatorOpen: boolean;
    currencyCode: string | null;
    sourceUrls: InvoiceSourceUrls;
    inlineCustomerStoreUrl: string;
    inlineProductStoreUrl: string;
    customerForm: InvoiceCustomerFormOptions;
    catalogForm: InvoiceCatalogFormOptions;
    abilities: { createCustomer: boolean; createProduct: boolean };
    labels: InvoiceTranslations['edit'];
    customerLabels: CustomerTranslations;
    catalogLabels: CatalogTranslations;
    onCustomerOpenChange: (open: boolean) => void;
    onCustomerCreatorOpenChange: (open: boolean) => void;
    onProductOpenChange: (open: boolean) => void;
    onProductCreatorOpenChange: (open: boolean) => void;
    onCustomerSelected: (selection: InvoiceCustomerSelection) => void;
    onProductSelected: (defaults: InvoiceProductDefaults) => void;
};

export function InvoiceSourceDialogs(props: Props) {
    const customerCreated = (page: Page) => {
        const created = page.props
            .inlineCreatedCustomer as InvoiceCustomerSelection | null;

        if (created === null) {
            return;
        }

        props.onCustomerCreatorOpenChange(false);
        props.onCustomerSelected(created);
    };
    const productCreated = (page: Page) => {
        const created = page.props
            .inlineCreatedProduct as InvoiceProductDefaults | null;

        if (created === null) {
            return;
        }

        props.onProductCreatorOpenChange(false);
        props.onProductSelected(created);
    };

    return (
        <DocumentSourceDialogs
            {...props}
            onCustomerCreated={customerCreated}
            onProductCreated={productCreated}
        />
    );
}
