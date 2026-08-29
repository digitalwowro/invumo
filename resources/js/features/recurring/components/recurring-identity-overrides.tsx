import { ChoiceField } from '@/components/app/choice-field';
import { TextField } from '@/components/app/form-field';
import { FormSection } from '@/components/app/form-section';
import { Grid } from '@/components/app/layout';
import { SelectField } from '@/components/app/select-field';
import type { CustomerTranslations } from '@/types/customer';
import type { DocumentCustomerFormOptions } from '@/types/document';
import type {
    RecurringInheritance,
    RecurringInheritanceTranslations,
} from '@/types/recurring';

type Props = {
    value: RecurringInheritance;
    labels: RecurringInheritanceTranslations;
    customerLabels: CustomerTranslations['form'];
    customerForm: DocumentCustomerFormOptions;
    errors: Record<string, string>;
    onChange: (value: RecurringInheritance) => void;
};

export function RecurringIdentityOverrides(props: Props) {
    const identity = props.value.identity;
    const field = props.customerLabels.fields;
    const explicit = props.value.identityMode === 'EXPLICIT';
    const change = (name: string, value: string) =>
        props.onChange({
            ...props.value,
            identity: { ...identity, [name]: value || null },
        });

    return (
        <FormSection
            title={props.labels.identity_title}
            description={props.labels.identity_description}
        >
            <ChoiceField
                name="inheritance.identity_mode"
                label={props.labels.identity_mode}
                defaultValue={props.value.identityMode}
                required
                options={modeOptions(props.labels)}
                onValueChange={(value) =>
                    props.onChange({
                        ...props.value,
                        identityMode: value as 'INHERIT' | 'EXPLICIT',
                    })
                }
            />
            {explicit && (
                <>
                    <ChoiceField
                        name="inheritance.identity.type"
                        label={field.type}
                        defaultValue={identity.type ?? 'COMPANY'}
                        required
                        options={props.customerForm.customerTypeOptions}
                        error={error(props, 'identity.type')}
                        onValueChange={(value) =>
                            props.onChange({
                                ...props.value,
                                identity: {
                                    ...identity,
                                    type: value,
                                    first_name:
                                        value === 'COMPANY'
                                            ? null
                                            : identity.first_name,
                                    last_name:
                                        value === 'COMPANY'
                                            ? null
                                            : identity.last_name,
                                    legal_name:
                                        value === 'INDIVIDUAL'
                                            ? null
                                            : identity.legal_name,
                                },
                            })
                        }
                    />
                    {identity.type === 'INDIVIDUAL' ? (
                        <Grid>
                            <IdentityField
                                name="first_name"
                                label={field.first_name}
                                limit={props.customerForm.limits.name}
                                {...{ props, identity, change }}
                            />
                            <IdentityField
                                name="last_name"
                                label={field.last_name}
                                limit={props.customerForm.limits.name}
                                {...{ props, identity, change }}
                            />
                        </Grid>
                    ) : (
                        <IdentityField
                            name="legal_name"
                            label={field.legal_name}
                            limit={props.customerForm.limits.name}
                            {...{ props, identity, change }}
                        />
                    )}
                    <Grid>
                        <IdentityField
                            name="contact_name"
                            label={props.labels.contact_name}
                            limit={props.customerForm.limits.name}
                            {...{ props, identity, change }}
                        />
                        <IdentityField
                            name="contact_position_title"
                            label={props.labels.contact_position_title}
                            limit={props.customerForm.limits.name}
                            {...{ props, identity, change }}
                        />
                        <IdentityField
                            name="email"
                            label={field.email}
                            limit={props.customerForm.limits.email}
                            type="email"
                            {...{ props, identity, change }}
                        />
                        <IdentityField
                            name="phone"
                            label={field.phone}
                            limit={props.customerForm.limits.phone}
                            type="tel"
                            {...{ props, identity, change }}
                        />
                    </Grid>
                    <Grid>
                        <IdentityField
                            name="address_line_1"
                            label={field.address_line_1}
                            limit={props.customerForm.limits.addressLine}
                            {...{ props, identity, change }}
                        />
                        <IdentityField
                            name="address_line_2"
                            label={field.address_line_2}
                            limit={props.customerForm.limits.addressLine}
                            {...{ props, identity, change }}
                        />
                        <IdentityField
                            name="city"
                            label={field.city}
                            limit={props.customerForm.limits.locality}
                            {...{ props, identity, change }}
                        />
                        <IdentityField
                            name="region"
                            label={field.region}
                            limit={props.customerForm.limits.locality}
                            {...{ props, identity, change }}
                        />
                        <IdentityField
                            name="postal_code"
                            label={field.postal_code}
                            limit={props.customerForm.limits.postalCode}
                            {...{ props, identity, change }}
                        />
                        <SelectField
                            name="inheritance.identity.country_code"
                            label={field.country_code}
                            value={identity.country_code ?? ''}
                            options={props.customerForm.countryOptions}
                            error={error(props, 'identity.country_code')}
                            onValueChange={(value) =>
                                change('country_code', value)
                            }
                        />
                    </Grid>
                    <Grid>
                        <IdentityField
                            name="tax_registration_label"
                            label={field.tax_registration_label}
                            limit={props.customerForm.limits.registrationLabel}
                            {...{ props, identity, change }}
                        />
                        <IdentityField
                            name="tax_registration_identifier"
                            label={field.tax_registration_identifier}
                            limit={props.customerForm.limits.registrationValue}
                            {...{ props, identity, change }}
                        />
                        <IdentityField
                            name="business_registration_label"
                            label={field.business_registration_label}
                            limit={props.customerForm.limits.registrationLabel}
                            {...{ props, identity, change }}
                        />
                        <IdentityField
                            name="business_registration_number"
                            label={field.business_registration_number}
                            limit={props.customerForm.limits.registrationValue}
                            {...{ props, identity, change }}
                        />
                    </Grid>
                </>
            )}
        </FormSection>
    );
}

type IdentityFieldProps = {
    name: string;
    label: string;
    limit: number;
    type?: 'email' | 'tel';
    props: Props;
    identity: Record<string, string | null>;
    change: (name: string, value: string) => void;
};

function IdentityField({
    name,
    label,
    limit,
    type,
    props,
    identity,
    change,
}: IdentityFieldProps) {
    return (
        <TextField
            label={label}
            error={error(props, `identity.${name}`)}
            input={{
                type,
                value: identity[name] ?? '',
                maxLength: limit,
                onChange: (event) => change(name, event.target.value),
            }}
        />
    );
}

function error(props: Props, field: string) {
    return props.errors[`inheritance.${field}`];
}

function modeOptions(labels: RecurringInheritanceTranslations) {
    return [
        { value: 'INHERIT', label: labels.inherit },
        { value: 'EXPLICIT', label: labels.explicit },
    ];
}
