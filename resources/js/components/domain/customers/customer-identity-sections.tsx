import { useState } from 'react';
import { ChoiceField } from '@/components/app/choice-field';
import { TextField } from '@/components/app/form-field';
import { FormSection } from '@/components/app/form-section';
import { Grid } from '@/components/app/layout';
import type {
    CustomerFieldLimits,
    CustomerOption,
    CustomerRecord,
    CustomerTranslations,
    CustomerType,
} from '@/types/customer';

type Props = {
    customer: CustomerRecord;
    customerTypeOptions: CustomerOption[];
    limits: CustomerFieldLimits;
    labels: CustomerTranslations['form'];
    errors: Record<string, string>;
    disabled: boolean;
};

export function CustomerIdentitySections({
    customer,
    customerTypeOptions,
    limits,
    labels,
    errors,
    disabled,
}: Props) {
    const [type, setType] = useState<CustomerType>(customer.type);
    const field = labels.fields;

    return (
        <>
            <FormSection
                title={labels.identity_title}
                description={labels.identity_description}
            >
                <ChoiceField
                    id="customer-type"
                    name="type"
                    label={field.type}
                    defaultValue={customer.type}
                    onValueChange={(value) => setType(value as CustomerType)}
                    options={customerTypeOptions}
                    error={errors.type}
                    required
                    disabled={disabled}
                />
                {type === 'INDIVIDUAL' ? (
                    <Grid>
                        <TextField
                            label={field.first_name}
                            error={errors.first_name}
                            input={{
                                name: 'first_name',
                                defaultValue: customer.firstName ?? '',
                                maxLength: limits.name,
                                required: true,
                                disabled,
                                autoComplete: 'given-name',
                            }}
                        />
                        <TextField
                            label={field.last_name}
                            error={errors.last_name}
                            input={{
                                name: 'last_name',
                                defaultValue: customer.lastName ?? '',
                                maxLength: limits.name,
                                required: true,
                                disabled,
                                autoComplete: 'family-name',
                            }}
                        />
                    </Grid>
                ) : (
                    <TextField
                        label={field.legal_name}
                        error={errors.legal_name}
                        input={{
                            name: 'legal_name',
                            defaultValue: customer.legalName ?? '',
                            maxLength: limits.name,
                            required: true,
                            disabled,
                            autoComplete: 'organization',
                        }}
                    />
                )}
                <TextField
                    label={field.external_reference}
                    error={errors.external_reference}
                    input={{
                        name: 'external_reference',
                        defaultValue: customer.externalReference ?? '',
                        maxLength: limits.externalReference,
                        disabled,
                    }}
                />
            </FormSection>

            <FormSection
                title={labels.contact_title}
                description={labels.contact_description}
            >
                <Grid>
                    <TextField
                        label={field.email}
                        error={errors.email}
                        input={{
                            name: 'email',
                            type: 'email',
                            defaultValue: customer.email ?? '',
                            maxLength: limits.email,
                            disabled,
                            autoComplete: 'email',
                        }}
                    />
                    <TextField
                        label={field.phone}
                        error={errors.phone}
                        input={{
                            name: 'phone',
                            type: 'tel',
                            defaultValue: customer.phone ?? '',
                            maxLength: limits.phone,
                            disabled,
                            autoComplete: 'tel',
                        }}
                    />
                </Grid>
            </FormSection>
        </>
    );
}
