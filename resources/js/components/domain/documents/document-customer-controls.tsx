import { FactStrip } from '@/components/app/fact-strip';
import { FormSection } from '@/components/app/form-section';
import { Button } from '@/components/ui/button';
import type {
    DocumentCustomerSelection,
    DocumentEditorTranslations,
} from '@/types/document';

type Props = {
    customer: DocumentCustomerSelection;
    labels: DocumentEditorTranslations;
    onSelect: () => void;
};

export function DocumentCustomerControls({
    customer,
    labels,
    onSelect,
}: Props) {
    return (
        <FormSection
            title={labels.customer_section}
            description={labels.customer_description}
            flush
            headerActions={
                <Button
                    type="button"
                    variant="secondary"
                    data-testid="document-customer-select"
                    onClick={onSelect}
                >
                    {customer.customerId === null
                        ? labels.select_customer
                        : labels.change_customer}
                </Button>
            }
        >
            <FactStrip
                className="lg:grid-cols-3"
                tone="subtle"
                facts={[
                    {
                        label: labels.customer_section,
                        value: customer.displayName ?? labels.no_customer,
                    },
                    {
                        label: labels.billing_contact,
                        value: billingContact(customer) ?? labels.not_available,
                    },
                    {
                        label: labels.tax_identifier,
                        value: taxIdentifier(customer) ?? labels.not_available,
                    },
                ]}
            />
        </FormSection>
    );
}

function billingContact(customer: DocumentCustomerSelection) {
    return (
        snapshotValue(customer, 'contact_name') ??
        snapshotValue(customer, 'email')
    );
}

function taxIdentifier(customer: DocumentCustomerSelection) {
    return (
        snapshotValue(customer, 'tax_registration_identifier') ??
        snapshotValue(customer, 'business_registration_number')
    );
}

function snapshotValue(
    customer: DocumentCustomerSelection,
    field: string,
): string | null {
    const value = customer.snapshot?.[field];

    return typeof value === 'string' && value.trim() !== '' ? value : null;
}
