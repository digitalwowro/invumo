import type { Page } from '@inertiajs/core';
import { DocumentCustomerSelector } from '@/components/domain/documents/document-customer-selector';
import { DocumentProductSelector } from '@/components/domain/documents/document-product-selector';
import { InlineDocumentCustomerDialog } from '@/components/domain/documents/inline-document-customer-dialog';
import { InlineDocumentProductDialog } from '@/components/domain/documents/inline-document-product-dialog';
import type { CatalogTranslations } from '@/types/catalog';
import type { CustomerTranslations } from '@/types/customer';
import type {
    DocumentCatalogFormOptions,
    DocumentCustomerFormOptions,
    DocumentCustomerSelection,
    DocumentEditorTranslations,
    DocumentProductDefaults,
    DocumentSourceUrls,
} from '@/types/document';

type Props = {
    customerOpen: boolean;
    customerCreatorOpen: boolean;
    productOpen: boolean;
    productCreatorOpen: boolean;
    currencyCode: string | null;
    sourceUrls: DocumentSourceUrls;
    inlineCustomerStoreUrl: string;
    inlineProductStoreUrl: string;
    customerForm: DocumentCustomerFormOptions;
    catalogForm: DocumentCatalogFormOptions;
    abilities: { createCustomer: boolean; createProduct: boolean };
    allowCompanyDefaults?: boolean;
    labels: DocumentEditorTranslations;
    customerLabels: CustomerTranslations;
    catalogLabels: CatalogTranslations;
    onCustomerOpenChange: (open: boolean) => void;
    onCustomerCreatorOpenChange: (open: boolean) => void;
    onProductOpenChange: (open: boolean) => void;
    onProductCreatorOpenChange: (open: boolean) => void;
    onCustomerSelected: (selection: DocumentCustomerSelection) => void;
    onCustomerCreated: (page: Page) => void;
    onProductSelected: (defaults: DocumentProductDefaults) => void;
    onProductCreated: (page: Page) => void;
};

export function DocumentSourceDialogs(props: Props) {
    return (
        <>
            <DocumentCustomerSelector
                open={props.customerOpen}
                searchUrl={props.sourceUrls.customerSearch}
                companyDefaultsUrl={props.sourceUrls.companyCustomerDefaults}
                labels={props.labels}
                canCreate={props.abilities.createCustomer}
                allowCompanyDefaults={props.allowCompanyDefaults}
                onOpenChange={props.onCustomerOpenChange}
                onCreate={() => {
                    props.onCustomerOpenChange(false);
                    props.onCustomerCreatorOpenChange(true);
                }}
                onSelect={props.onCustomerSelected}
            />
            <InlineDocumentCustomerDialog
                open={props.customerCreatorOpen}
                storeUrl={props.inlineCustomerStoreUrl}
                options={props.customerForm}
                documentLabels={props.labels}
                customerLabels={props.customerLabels}
                onOpenChange={props.onCustomerCreatorOpenChange}
                onCreated={props.onCustomerCreated}
            />
            <DocumentProductSelector
                open={props.productOpen}
                searchUrl={props.sourceUrls.productSearch}
                currencyCode={props.currencyCode}
                labels={props.labels}
                canCreate={props.abilities.createProduct}
                onOpenChange={props.onProductOpenChange}
                onCreate={() => {
                    props.onProductOpenChange(false);
                    props.onProductCreatorOpenChange(true);
                }}
                onSelect={props.onProductSelected}
            />
            <InlineDocumentProductDialog
                open={props.productCreatorOpen}
                storeUrl={props.inlineProductStoreUrl}
                options={props.catalogForm}
                documentLabels={props.labels}
                catalogLabels={props.catalogLabels}
                onOpenChange={props.onProductCreatorOpenChange}
                onCreated={props.onProductCreated}
            />
        </>
    );
}
