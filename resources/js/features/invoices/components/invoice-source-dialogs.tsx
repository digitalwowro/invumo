import type { Page } from '@inertiajs/core';
import { DocumentSourceDialogs } from '@/components/domain/documents/document-source-dialogs';
import type { CustomerTranslations } from '@/types/customer';
import type {
    InvoiceCustomerFormOptions,
    InvoiceCustomerSelection,
    InvoiceSourceUrls,
    InvoiceTranslations,
} from '@/types/invoice';

type Props = {
    customerOpen: boolean;
    customerCreatorOpen: boolean;
    sourceUrls: InvoiceSourceUrls;
    inlineCustomerStoreUrl: string;
    customerForm: InvoiceCustomerFormOptions;
    abilities: { createCustomer: boolean };
    labels: InvoiceTranslations['edit'];
    customerLabels: CustomerTranslations;
    onCustomerOpenChange: (open: boolean) => void;
    onCustomerCreatorOpenChange: (open: boolean) => void;
    onCustomerSelected: (selection: InvoiceCustomerSelection) => void;
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

    return (
        <DocumentSourceDialogs {...props} onCustomerCreated={customerCreated} />
    );
}
