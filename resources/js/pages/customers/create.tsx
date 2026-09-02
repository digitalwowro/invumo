import { Head } from '@inertiajs/react';
import {
    ResourceWorkspace,
    ResourceWorkspaceHeader,
} from '@/components/app/resource-workspace';
import { CustomerForm } from '@/components/domain/customers/customer-form';
import type {
    CustomerFieldLimits,
    CustomerOption,
    CustomerRecord,
    CustomerTranslations,
} from '@/types/customer';

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
    storeUrl: string;
    indexUrl: string;
    countryOptions: CustomerOption[];
    customerTypeOptions: CustomerOption[];
    limits: CustomerFieldLimits;
    translations: CustomerTranslations;
};

export default function CreateCustomer({
    storeUrl,
    indexUrl,
    countryOptions,
    customerTypeOptions,
    limits,
    translations,
}: Props) {
    const labels = translations.create;

    return (
        <>
            <Head title={labels.head_title} />
            <ResourceWorkspace>
                <CustomerForm
                    customer={emptyCustomer}
                    actionUrl={storeUrl}
                    method="post"
                    submitLabel={labels.submit}
                    countryOptions={countryOptions}
                    customerTypeOptions={customerTypeOptions}
                    limits={limits}
                    labels={translations.form}
                    unsavedWarning={translations.form.unsaved_warning}
                    formId="new-customer-form"
                    showActions={false}
                    renderHeader={(submitAction) => (
                        <ResourceWorkspaceHeader
                            breadcrumbs={[
                                {
                                    title: translations.index.title,
                                    href: indexUrl,
                                },
                                { title: labels.title, href: indexUrl },
                            ]}
                            title={labels.title}
                            description={labels.description}
                            actions={submitAction}
                        />
                    )}
                />
            </ResourceWorkspace>
        </>
    );
}
