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
    QuoteCustomerFormOptions,
    QuoteTranslations,
} from '@/types/quote';

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
    options: QuoteCustomerFormOptions;
    quoteLabels: QuoteTranslations['edit'];
    customerLabels: CustomerTranslations;
    onOpenChange: (open: boolean) => void;
    onCreated: (page: Page) => void;
};

export function InlineCustomerDialog({
    open,
    storeUrl,
    options,
    quoteLabels,
    customerLabels,
    onOpenChange,
    onCreated,
}: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className="sm:max-w-5xl"
                closeLabel={quoteLabels.close}
            >
                <DialogHeader>
                    <DialogTitle>
                        {quoteLabels.create_customer_title}
                    </DialogTitle>
                    <DialogDescription>
                        {quoteLabels.create_customer_description}
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
