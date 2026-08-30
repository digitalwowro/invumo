import { TextField } from '@/components/app/form-field';
import { FormSection } from '@/components/app/form-section';
import { Grid } from '@/components/app/layout';
import type { InvoiceLimits, InvoiceTranslations } from '@/types/invoice';

type Props = {
    issueDate: string;
    paymentTermDays: string;
    dueDate: string;
    customerReference: string;
    limits: InvoiceLimits;
    labels: InvoiceTranslations['edit'];
    errors: Record<string, string>;
    onChange: (field: string, value: string) => void;
};

export function InvoiceDetailsSection(props: Props) {
    return (
        <FormSection
            title={props.labels.details_section}
            description={props.labels.details_description}
        >
            <Grid columns={4} gap="lg" className="lg:grid-cols-4">
                <TextField
                    label={props.labels.fields.issue_date}
                    error={props.errors.issue_date}
                    input={{
                        type: 'date',
                        value: props.issueDate,
                        onChange: (event) =>
                            props.onChange('issueDate', event.target.value),
                    }}
                />
                <TextField
                    label={props.labels.fields.payment_term_days}
                    error={props.errors.payment_term_days}
                    input={{
                        'data-test': 'invoice-payment-term-days',
                        type: 'number',
                        min: 0,
                        max: props.limits.maxDayOffset,
                        step: 1,
                        value: props.paymentTermDays,
                        onChange: (event) =>
                            props.onChange(
                                'paymentTermDays',
                                event.target.value,
                            ),
                    }}
                />
                <TextField
                    label={props.labels.fields.due_date}
                    error={props.errors.due_date}
                    input={{
                        type: 'date',
                        value: props.dueDate,
                        min: props.issueDate || undefined,
                        onChange: (event) =>
                            props.onChange('dueDate', event.target.value),
                    }}
                />
                <TextField
                    label={props.labels.fields.customer_reference}
                    error={props.errors.customer_reference}
                    input={{
                        value: props.customerReference,
                        maxLength: props.limits.customerReference,
                        onChange: (event) =>
                            props.onChange(
                                'customerReference',
                                event.target.value,
                            ),
                    }}
                />
            </Grid>
        </FormSection>
    );
}
