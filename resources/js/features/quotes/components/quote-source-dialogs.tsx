import type { Page } from '@inertiajs/core';
import { InlineCustomerDialog } from '@/features/quotes/components/inline-customer-dialog';
import { InlineProductDialog } from '@/features/quotes/components/inline-product-dialog';
import { QuoteCustomerSelector } from '@/features/quotes/components/quote-customer-selector';
import { QuoteProductSelector } from '@/features/quotes/components/quote-product-selector';
import type { CatalogTranslations } from '@/types/catalog';
import type { CustomerTranslations } from '@/types/customer';
import type {
    QuoteCatalogFormOptions,
    QuoteCustomerFormOptions,
    QuoteCustomerSelection,
    QuoteProductDefaults,
    QuoteSourceUrls,
    QuoteTranslations,
} from '@/types/quote';

type Props = {
    customerOpen: boolean;
    customerCreatorOpen: boolean;
    productOpen: boolean;
    productCreatorOpen: boolean;
    currencyCode: string | null;
    sourceUrls: QuoteSourceUrls;
    inlineCustomerStoreUrl: string;
    inlineProductStoreUrl: string;
    customerForm: QuoteCustomerFormOptions;
    catalogForm: QuoteCatalogFormOptions;
    abilities: { createCustomer: boolean; createProduct: boolean };
    labels: QuoteTranslations['edit'];
    customerLabels: CustomerTranslations;
    catalogLabels: CatalogTranslations;
    onCustomerOpenChange: (open: boolean) => void;
    onCustomerCreatorOpenChange: (open: boolean) => void;
    onProductOpenChange: (open: boolean) => void;
    onProductCreatorOpenChange: (open: boolean) => void;
    onCustomerSelected: (selection: QuoteCustomerSelection) => void;
    onCustomerCreated: (page: Page) => void;
    onProductSelected: (defaults: QuoteProductDefaults) => void;
    onProductCreated: (page: Page) => void;
};

export function QuoteSourceDialogs(props: Props) {
    return (
        <>
            <QuoteCustomerSelector
                open={props.customerOpen}
                searchUrl={props.sourceUrls.customerSearch}
                companyDefaultsUrl={props.sourceUrls.companyCustomerDefaults}
                labels={props.labels}
                canCreate={props.abilities.createCustomer}
                onOpenChange={props.onCustomerOpenChange}
                onCreate={() => {
                    props.onCustomerOpenChange(false);
                    props.onCustomerCreatorOpenChange(true);
                }}
                onSelect={props.onCustomerSelected}
            />
            <InlineCustomerDialog
                open={props.customerCreatorOpen}
                storeUrl={props.inlineCustomerStoreUrl}
                options={props.customerForm}
                quoteLabels={props.labels}
                customerLabels={props.customerLabels}
                onOpenChange={props.onCustomerCreatorOpenChange}
                onCreated={props.onCustomerCreated}
            />
            <QuoteProductSelector
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
            <InlineProductDialog
                open={props.productCreatorOpen}
                storeUrl={props.inlineProductStoreUrl}
                options={props.catalogForm}
                quoteLabels={props.labels}
                catalogLabels={props.catalogLabels}
                onOpenChange={props.onProductCreatorOpenChange}
                onCreated={props.onProductCreated}
            />
        </>
    );
}
