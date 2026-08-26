import type { Page } from '@inertiajs/core';
import { CustomerForm } from '@/components/domain/customers/customer-form';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { CustomerRecord, CustomerTranslations } from '@/types/customer';
import type {
    DocumentCustomerFormOptions,
    DocumentEditorTranslations,
} from '@/types/document';

const emptyCustomer: CustomerRecord = {
    type: 'INDIVIDUAL',
    firstName: null,
    lastName: null,
    legalName: null,
    email: null,
    phone: null,
    externalReference: null,
    addressLine1: null,
    addressLine2: null,
    city: null,
    region: null,
    postalCode: null,
    countryCode: null,
    taxRegistrationLabel: null,
    taxRegistrationIdentifier: null,
    businessRegistrationLabel: null,
    businessRegistrationNumber: null,
    internalNotes: null,
};

type Props = {
    open: boolean;
    storeUrl: string;
    options: DocumentCustomerFormOptions;
    documentLabels: DocumentEditorTranslations;
    customerLabels: CustomerTranslations;
    onOpenChange: (open: boolean) => void;
    onCreated: (page: Page) => void;
};

export function InlineDocumentCustomerDialog({
    open,
    storeUrl,
    options,
    documentLabels,
    customerLabels,
    onOpenChange,
    onCreated,
}: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className="sm:max-w-5xl"
                closeLabel={documentLabels.close}
            >
                <DialogHeader>
                    <DialogTitle>
                        {documentLabels.create_customer_title}
                    </DialogTitle>
                    <DialogDescription>
                        {documentLabels.create_customer_description}
                    </DialogDescription>
                </DialogHeader>
                <CustomerForm
                    customer={emptyCustomer}
                    actionUrl={storeUrl}
                    method="post"
                    submitLabel={customerLabels.create.submit}
                    countryOptions={options.countryOptions}
                    customerTypeOptions={options.customerTypeOptions}
                    limits={options.limits}
                    labels={customerLabels.form}
                    unsavedWarning={customerLabels.form.unsaved_warning}
                    onSuccess={onCreated}
                />
            </DialogContent>
        </Dialog>
    );
}
