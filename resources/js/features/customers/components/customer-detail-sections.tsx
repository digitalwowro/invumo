import { FormActions, SubmitButton } from '@/components/app/form-actions';
import { TextareaField, TextField } from '@/components/app/form-field';
import { FormSection } from '@/components/app/form-section';
import { Grid } from '@/components/app/layout';
import { SelectField } from '@/components/app/select-field';
import type {
    CustomerFieldLimits,
    CustomerOption,
    CustomerRecord,
    CustomerTranslations,
} from '@/types/customer';

type Props = {
    customer: CustomerRecord;
    countryOptions: CustomerOption[];
    limits: CustomerFieldLimits;
    labels: CustomerTranslations['form'];
    errors: Record<string, string>;
    disabled: boolean;
    processing: boolean;
    submitLabel: string;
};

export function CustomerDetailSections({
    customer,
    countryOptions,
    limits,
    labels,
    errors,
    disabled,
    processing,
    submitLabel,
}: Props) {
    const field = labels.fields;

    return (
        <>
            <FormSection
                title={labels.address_title}
                description={labels.address_description}
            >
                <Grid>
                    <TextField
                        label={field.address_line_1}
                        error={errors.address_line_1}
                        input={{
                            name: 'address_line_1',
                            defaultValue: customer.addressLine1 ?? '',
                            maxLength: limits.addressLine,
                            disabled,
                            autoComplete: 'address-line1',
                        }}
                    />
                    <TextField
                        label={field.address_line_2}
                        error={errors.address_line_2}
                        input={{
                            name: 'address_line_2',
                            defaultValue: customer.addressLine2 ?? '',
                            maxLength: limits.addressLine,
                            disabled,
                            autoComplete: 'address-line2',
                        }}
                    />
                    <TextField
                        label={field.city}
                        error={errors.city}
                        input={{
                            name: 'city',
                            defaultValue: customer.city ?? '',
                            maxLength: limits.locality,
                            disabled,
                            autoComplete: 'address-level2',
                        }}
                    />
                    <TextField
                        label={field.region}
                        error={errors.region}
                        input={{
                            name: 'region',
                            defaultValue: customer.region ?? '',
                            maxLength: limits.locality,
                            disabled,
                            autoComplete: 'address-level1',
                        }}
                    />
                    <TextField
                        label={field.postal_code}
                        error={errors.postal_code}
                        input={{
                            name: 'postal_code',
                            defaultValue: customer.postalCode ?? '',
                            maxLength: limits.postalCode,
                            disabled,
                            autoComplete: 'postal-code',
                        }}
                    />
                    <SelectField
                        name="country_code"
                        label={field.country_code}
                        placeholder={labels.country_placeholder}
                        defaultValue={customer.countryCode ?? undefined}
                        error={errors.country_code}
                        options={countryOptions}
                        disabled={disabled}
                    />
                </Grid>
            </FormSection>

            <FormSection
                title={labels.registration_title}
                description={labels.registration_description}
            >
                <Grid>
                    <TextField
                        label={field.tax_registration_label}
                        error={errors.tax_registration_label}
                        input={{
                            name: 'tax_registration_label',
                            defaultValue: customer.taxRegistrationLabel ?? '',
                            maxLength: limits.registrationLabel,
                            disabled,
                        }}
                    />
                    <TextField
                        label={field.tax_registration_identifier}
                        error={errors.tax_registration_identifier}
                        input={{
                            name: 'tax_registration_identifier',
                            defaultValue:
                                customer.taxRegistrationIdentifier ?? '',
                            maxLength: limits.registrationValue,
                            disabled,
                        }}
                    />
                    <TextField
                        label={field.business_registration_label}
                        error={errors.business_registration_label}
                        input={{
                            name: 'business_registration_label',
                            defaultValue:
                                customer.businessRegistrationLabel ?? '',
                            maxLength: limits.registrationLabel,
                            disabled,
                        }}
                    />
                    <TextField
                        label={field.business_registration_number}
                        error={errors.business_registration_number}
                        input={{
                            name: 'business_registration_number',
                            defaultValue:
                                customer.businessRegistrationNumber ?? '',
                            maxLength: limits.registrationValue,
                            disabled,
                        }}
                    />
                </Grid>
            </FormSection>

            <FormSection
                title={labels.notes_title}
                description={labels.notes_description}
                actions={
                    !disabled ? (
                        <FormActions>
                            <SubmitButton processing={processing}>
                                {submitLabel}
                            </SubmitButton>
                        </FormActions>
                    ) : undefined
                }
            >
                <TextareaField
                    label={field.internal_notes}
                    error={errors.internal_notes}
                    textarea={{
                        name: 'internal_notes',
                        defaultValue: customer.internalNotes ?? '',
                        maxLength: limits.internalNotes,
                        rows: 6,
                        disabled,
                    }}
                />
            </FormSection>
        </>
    );
}
