import { FormSection } from '@/components/app/form-section';
import { Button } from '@/components/ui/button';
import type { QuoteCustomerSelection, QuoteTranslations } from '@/types/quote';

type Props = {
    customer: QuoteCustomerSelection;
    labels: QuoteTranslations['edit'];
    onSelect: () => void;
};

export function QuoteCustomerControls({ customer, labels, onSelect }: Props) {
    return (
        <FormSection
            title={labels.customer_section}
            description={labels.customer_description}
            actions={
                <Button
                    type="button"
                    variant="secondary"
                    data-testid="quote-customer-select"
                    onClick={onSelect}
                >
                    {customer.customerId === null
                        ? labels.select_customer
                        : labels.change_customer}
                </Button>
            }
        >
            <p className="font-medium">
                {customer.displayName ?? labels.no_customer}
            </p>
        </FormSection>
    );
}
