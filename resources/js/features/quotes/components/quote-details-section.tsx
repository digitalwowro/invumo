import { TextField } from '@/components/app/form-field';
import { FormSection } from '@/components/app/form-section';
import { Grid } from '@/components/app/layout';
import type { QuoteLimits, QuoteTranslations } from '@/types/quote';

type Props = {
    issueDate: string;
    validityDays: string;
    validUntil: string;
    customerReference: string;
    limits: QuoteLimits;
    labels: QuoteTranslations['edit'];
    errors: Record<string, string>;
    onChange: (field: string, value: string) => void;
};

export function QuoteDetailsSection(props: Props) {
    return (
        <FormSection
            title={props.labels.details_section}
            description={props.labels.details_description}
        >
            <Grid columns={3} gap="lg">
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
                    label={props.labels.fields.validity_days}
                    error={props.errors.validity_days}
                    input={{
                        'data-test': 'quote-validity-days',
                        type: 'number',
                        min: 0,
                        max: props.limits.maxDayOffset,
                        step: 1,
                        value: props.validityDays,
                        onChange: (event) =>
                            props.onChange('validityDays', event.target.value),
                    }}
                />
                <TextField
                    label={props.labels.fields.valid_until}
                    error={props.errors.valid_until}
                    input={{
                        type: 'date',
                        value: props.validUntil,
                        min: props.issueDate || undefined,
                        onChange: (event) =>
                            props.onChange('validUntil', event.target.value),
                    }}
                />
            </Grid>
            <TextField
                label={props.labels.fields.customer_reference}
                description={props.labels.customer_reference_description}
                error={props.errors.customer_reference}
                input={{
                    value: props.customerReference,
                    maxLength: props.limits.customerReference,
                    onChange: (event) =>
                        props.onChange('customerReference', event.target.value),
                }}
            />
        </FormSection>
    );
}
