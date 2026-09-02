import type { Page } from '@inertiajs/core';
import { DocumentCustomerSelector } from '@/components/domain/documents/document-customer-selector';
import { InlineDocumentCustomerDialog } from '@/components/domain/documents/inline-document-customer-dialog';
import type { CustomerTranslations } from '@/types/customer';
import type {
    DocumentCustomerFormOptions,
    DocumentCustomerSelection,
    DocumentEditorTranslations,
    DocumentSourceUrls,
} from '@/types/document';

type Props = {
    customerOpen: boolean;
    customerCreatorOpen: boolean;
    sourceUrls: DocumentSourceUrls;
    inlineCustomerStoreUrl: string;
    customerForm: DocumentCustomerFormOptions;
    abilities: { createCustomer: boolean };
    allowCompanyDefaults?: boolean;
    labels: DocumentEditorTranslations;
    customerLabels: CustomerTranslations;
    onCustomerOpenChange: (open: boolean) => void;
    onCustomerCreatorOpenChange: (open: boolean) => void;
    onCustomerSelected: (selection: DocumentCustomerSelection) => void;
    onCustomerCreated: (page: Page) => void;
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
        </>
    );
}
