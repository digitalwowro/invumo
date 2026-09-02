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
import type { InvoiceLimits, InvoiceTranslations } from '@/types/invoice';

const UNSET = '__UNSET__';

type Props = {
    issueDate: string;
    paymentTermDays: string;
    dueDate: string;
    customerReference: string;
    currencyCode: string | null;
    currencyOptions: DocumentCurrencyOption[];
    taxDefault: DocumentTaxDefault | null;
    taxPresetOptions: CatalogTaxOption[];
    limits: InvoiceLimits;
    labels: InvoiceTranslations['edit'];
    errors: Record<string, string>;
    onChange: (field: string, value: string) => void;
    onDefaultChange: (field: string, value: string | null) => void;
    onTaxDefaultChange: (value: string) => void;
};

export function InvoiceDetailsSection(props: Props) {
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
