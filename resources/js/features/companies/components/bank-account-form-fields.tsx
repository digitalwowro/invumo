import { CheckboxField, TextField } from '@/components/app/form-field';
import { Grid } from '@/components/app/layout';
import { SelectField } from '@/components/app/select-field';
import { FieldDescription, FieldLegend, FieldSet } from '@/components/ui/field';
import type { CompanyOption } from '@/types/company';
import type {
    BankAccountFormData,
    BankRoutingField,
    CompanyBankAccountTranslations,
} from '@/types/company-bank-account';

type Props = {
    data: BankAccountFormData;
    errors: Record<string, string>;
    currencyOptions: CompanyOption[];
    routingFields: BankRoutingField[];
    labels: CompanyBankAccountTranslations;
    onChange: (data: BankAccountFormData) => void;
};

const noCurrencyValue = '__none__';

export function BankAccountFormFields({
    data,
    errors,
    currencyOptions,
    routingFields,
    labels,
    onChange,
}: Props) {
    const change = <Key extends keyof BankAccountFormData>(
        key: Key,
        value: BankAccountFormData[Key],
    ) => onChange({ ...data, [key]: value });

    return (
        <>
            <Grid columns={2} gap="lg">
                <TextField
                    label={labels.fields.label}
                    error={errors.label}
                    input={{
                        value: data.label,
                        required: true,
                        maxLength: 120,
                        onChange: (event) =>
                            change('label', event.target.value),
                    }}
                />
                <TextField
                    label={labels.fields.bank_name}
                    error={errors.bank_name}
                    input={{
                        value: data.bank_name,
                        required: true,
                        maxLength: 160,
                        onChange: (event) =>
                            change('bank_name', event.target.value),
                    }}
                />
                <TextField
                    label={labels.fields.account_holder}
                    error={errors.account_holder}
                    input={{
                        value: data.account_holder,
                        required: true,
                        maxLength: 160,
                        onChange: (event) =>
                            change('account_holder', event.target.value),
                    }}
                />
                <TextField
                    label={labels.fields.account_number}
                    error={errors.account_number}
                    input={{
                        value: data.account_number,
                        required: true,
                        maxLength: 64,
                        autoCapitalize: 'characters',
                        onChange: (event) =>
                            change('account_number', event.target.value),
                    }}
                />
                <TextField
                    label={labels.fields.swift_bic}
                    description={labels.field_descriptions.swift_bic}
                    error={errors.swift_bic}
                    input={{
                        value: data.swift_bic,
                        maxLength: 11,
                        autoCapitalize: 'characters',
                        onChange: (event) =>
                            change(
                                'swift_bic',
                                event.target.value.toUpperCase(),
                            ),
                    }}
                />
                <SelectField
                    name="currency_id"
                    label={labels.fields.currency_id}
                    description={labels.field_descriptions.currency_id}
                    error={errors.currency_id}
                    value={data.currency_id || noCurrencyValue}
                    onValueChange={(value) =>
                        change(
                            'currency_id',
                            value === noCurrencyValue ? '' : value,
                        )
                    }
                    options={[
                        { value: noCurrencyValue, label: labels.no_currency },
                        ...currencyOptions,
                    ]}
                />
            </Grid>
            <FieldSet>
                <FieldLegend>{labels.routing_title}</FieldLegend>
                <FieldDescription>
                    {labels.routing_description}
                </FieldDescription>
                <Grid columns={4} gap="lg">
                    {routingFields.map((field) => (
                        <TextField
                            key={field}
                            label={labels.routing_fields[field]}
                            error={errors[`local_routing_details.${field}`]}
                            input={{
                                value: data.local_routing_details[field],
                                maxLength: 64,
                                onChange: (event) =>
                                    change('local_routing_details', {
                                        ...data.local_routing_details,
                                        [field]: event.target.value,
                                    }),
                            }}
                        />
                    ))}
                </Grid>
            </FieldSet>
            <CheckboxField
                label={labels.fields.is_default}
                description={labels.field_descriptions.is_default}
                error={errors.is_default}
                checkbox={{
                    checked: data.is_default,
                    onCheckedChange: (checked) =>
                        change('is_default', checked === true),
                }}
            />
        </>
    );
}
