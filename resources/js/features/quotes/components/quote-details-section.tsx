import { TextField } from '@/components/app/form-field';
import { FormSection } from '@/components/app/form-section';
import { Grid } from '@/components/app/layout';
import { SelectField } from '@/components/app/select-field';
import {
    taxDefaultOptions,
    taxDefaultSelection,
} from '@/components/domain/documents/document-tax-options';
import type { CatalogTaxOption } from '@/types/catalog';
import type {
    DocumentCurrencyOption,
    DocumentTaxDefault,
} from '@/types/document';
import type { QuoteLimits, QuoteTranslations } from '@/types/quote';

const UNSET = '__UNSET__';

type Props = {
    issueDate: string;
    validityDays: string;
    validUntil: string;
    customerReference: string;
    currencyCode: string | null;
    currencyOptions: DocumentCurrencyOption[];
    taxDefault: DocumentTaxDefault | null;
    taxPresetOptions: CatalogTaxOption[];
    limits: QuoteLimits;
    labels: QuoteTranslations['edit'];
    errors: Record<string, string>;
    onChange: (field: string, value: string) => void;
    onDefaultChange: (field: string, value: string | null) => void;
    onTaxDefaultChange: (value: string) => void;
};

export function QuoteDetailsSection(props: Props) {
    return (
        <FormSection
            title={props.labels.details_section}
            description={props.labels.details_description}
        >
            <Grid columns={3} gap="lg" className="lg:grid-cols-3">
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
                <SelectField
                    name="currency_code"
                    label={props.labels.currency}
                    error={props.errors.currency_code}
                    value={props.currencyCode ?? UNSET}
                    onValueChange={(value) =>
                        props.onDefaultChange(
                            'currencyCode',
                            value === UNSET ? null : value,
                        )
                    }
                    options={[
                        { value: UNSET, label: props.labels.not_available },
                        ...props.currencyOptions,
                    ]}
                />
                <SelectField
                    name="tax_default_preset_id"
                    testId="document-tax-default"
                    label={props.labels.tax_default}
                    error={props.errors.tax_default_preset_id}
                    value={taxDefaultSelection(props.taxDefault)}
                    options={taxDefaultOptions(
                        props.taxPresetOptions,
                        props.taxDefault,
                        props.labels.no_tax,
                    )}
                    onValueChange={props.onTaxDefaultChange}
                />
            </Grid>
        </FormSection>
    );
}
