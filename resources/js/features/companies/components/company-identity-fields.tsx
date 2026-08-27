import { TextField } from '@/components/app/form-field';
import { FormSection } from '@/components/app/form-section';
import { Grid } from '@/components/app/layout';
import { SelectField } from '@/components/app/select-field';
import type { CompanyConfiguration, CompanyOption } from '@/types';
import type { CompanySettingsTranslations } from '@/types/company-settings';

type Props = {
    configuration: CompanyConfiguration;
    countryOptions: CompanyOption[];
    errors: Record<string, string>;
    labels: CompanySettingsTranslations['profile'];
};

export function CompanyIdentityFields({
    configuration,
    countryOptions,
    errors,
    labels,
}: Props) {
    const fields = labels.fields;

    return (
        <>
            <FormSection
                title={labels.identity_title}
                description={labels.identity_description}
            >
                <Grid columns={2} gap="lg">
                    <TextField
                        id="display_name"
                        label={fields.display_name}
                        error={errors.display_name}
                        input={{
                            name: 'display_name',
                            defaultValue: configuration.displayName,
                            required: true,
                            maxLength: 160,
                            autoComplete: 'organization',
                        }}
                    />
                    <TextField
                        id="legal_name"
                        label={fields.legal_name}
                        error={errors.legal_name}
                        input={{
                            name: 'legal_name',
                            defaultValue: configuration.legalName,
                            required: true,
                            maxLength: 160,
                        }}
                    />
                    <TextField
                        id="trading_name"
                        label={fields.trading_name}
                        error={errors.trading_name}
                        input={{
                            name: 'trading_name',
                            defaultValue:
                                configuration.tradingName ?? undefined,
                            maxLength: 160,
                        }}
                    />
                </Grid>
            </FormSection>

            <FormSection
                title={labels.address_title}
                description={labels.address_description}
            >
                <Grid columns={2} gap="lg">
                    <TextField
                        id="address_line_1"
                        label={fields.address_line_1}
                        error={errors.address_line_1}
                        input={{
                            name: 'address_line_1',
                            defaultValue:
                                configuration.addressLine1 ?? undefined,
                            maxLength: 200,
                            autoComplete: 'address-line1',
                        }}
                    />
                    <TextField
                        id="address_line_2"
                        label={fields.address_line_2}
                        error={errors.address_line_2}
                        input={{
                            name: 'address_line_2',
                            defaultValue:
                                configuration.addressLine2 ?? undefined,
                            maxLength: 200,
                            autoComplete: 'address-line2',
                        }}
                    />
                    <TextField
                        id="city"
                        label={fields.city}
                        error={errors.city}
                        input={{
                            name: 'city',
                            defaultValue: configuration.city ?? undefined,
                            maxLength: 120,
                            autoComplete: 'address-level2',
                        }}
                    />
                    <TextField
                        id="region"
                        label={fields.region}
                        error={errors.region}
                        input={{
                            name: 'region',
                            defaultValue: configuration.region ?? undefined,
                            maxLength: 120,
                            autoComplete: 'address-level1',
                        }}
                    />
                    <TextField
                        id="postal_code"
                        label={fields.postal_code}
                        error={errors.postal_code}
                        input={{
                            name: 'postal_code',
                            defaultValue: configuration.postalCode ?? undefined,
                            maxLength: 32,
                            autoComplete: 'postal-code',
                        }}
                    />
                    <SelectField
                        id="country_code"
                        name="country_code"
                        label={fields.country_code}
                        error={errors.country_code}
                        placeholder={labels.country_placeholder}
                        defaultValue={configuration.countryCode ?? undefined}
                        options={countryOptions}
                    />
                </Grid>
            </FormSection>

            <FormSection
                title={labels.registration_title}
                description={labels.registration_description}
            >
                <Grid columns={2} gap="lg">
                    <TextField
                        id="tax_registration_label"
                        label={fields.tax_registration_label}
                        error={errors.tax_registration_label}
                        input={{
                            name: 'tax_registration_label',
                            defaultValue:
                                configuration.taxRegistrationLabel ?? undefined,
                            maxLength: 80,
                        }}
                    />
                    <TextField
                        id="tax_registration_identifier"
                        label={fields.tax_registration_identifier}
                        error={errors.tax_registration_identifier}
                        input={{
                            name: 'tax_registration_identifier',
                            defaultValue:
                                configuration.taxRegistrationIdentifier ??
                                undefined,
                            maxLength: 120,
                        }}
                    />
                    <TextField
                        id="business_registration_label"
                        label={fields.business_registration_label}
                        error={errors.business_registration_label}
                        input={{
                            name: 'business_registration_label',
                            defaultValue:
                                configuration.businessRegistrationLabel ??
                                undefined,
                            maxLength: 80,
                        }}
                    />
                    <TextField
                        id="business_registration_number"
                        label={fields.business_registration_number}
                        error={errors.business_registration_number}
                        input={{
                            name: 'business_registration_number',
                            defaultValue:
                                configuration.businessRegistrationNumber ??
                                undefined,
                            maxLength: 120,
                        }}
                    />
                    <TextField
                        id="email"
                        label={fields.email}
                        error={errors.email}
                        input={{
                            type: 'email',
                            name: 'email',
                            defaultValue: configuration.email ?? undefined,
                            maxLength: 254,
                            autoComplete: 'email',
                        }}
                    />
                    <TextField
                        id="phone"
                        label={fields.phone}
                        error={errors.phone}
                        input={{
                            type: 'tel',
                            name: 'phone',
                            defaultValue: configuration.phone ?? undefined,
                            maxLength: 50,
                            autoComplete: 'tel',
                        }}
                    />
                    <TextField
                        id="website"
                        label={fields.website}
                        error={errors.website}
                        input={{
                            type: 'url',
                            name: 'website',
                            defaultValue: configuration.website ?? undefined,
                            maxLength: 2048,
                            autoComplete: 'url',
                        }}
                    />
                </Grid>
            </FormSection>
        </>
    );
}
