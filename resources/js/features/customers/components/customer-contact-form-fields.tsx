import { CheckboxField, TextField } from '@/components/app/form-field';
import { Grid } from '@/components/app/layout';
import type {
    CustomerContactFormData,
    CustomerContactTranslations,
    CustomerFieldLimits,
} from '@/types/customer';

type Props = {
    data: CustomerContactFormData;
    errors: Record<string, string>;
    limits: CustomerFieldLimits;
    labels: CustomerContactTranslations;
    disabled?: boolean;
    onChange: (data: CustomerContactFormData) => void;
};

export function CustomerContactFormFields({
    data,
    errors,
    limits,
    labels,
    disabled = false,
    onChange,
}: Props) {
    const change = <Key extends keyof CustomerContactFormData>(
        key: Key,
        value: CustomerContactFormData[Key],
    ) => onChange({ ...data, [key]: value });

    return (
        <>
            <Grid columns={2} gap="lg">
                <TextField
                    label={labels.fields.name}
                    error={errors.name}
                    input={{
                        value: data.name,
                        required: true,
                        maxLength: limits.name,
                        disabled,
                        onChange: (event) => change('name', event.target.value),
                    }}
                />
                <TextField
                    label={labels.fields.position_title}
                    error={errors.position_title}
                    input={{
                        value: data.position_title,
                        maxLength: limits.name,
                        disabled,
                        onChange: (event) =>
                            change('position_title', event.target.value),
                    }}
                />
                <TextField
                    label={labels.fields.email}
                    error={errors.email}
                    input={{
                        type: 'email',
                        value: data.email,
                        maxLength: limits.email,
                        autoComplete: 'email',
                        disabled,
                        onChange: (event) =>
                            change('email', event.target.value),
                    }}
                />
                <TextField
                    label={labels.fields.phone}
                    error={errors.phone}
                    input={{
                        type: 'tel',
                        value: data.phone,
                        maxLength: limits.phone,
                        autoComplete: 'tel',
                        disabled,
                        onChange: (event) =>
                            change('phone', event.target.value),
                    }}
                />
            </Grid>
            <Grid columns={2} gap="lg">
                <CheckboxField
                    label={labels.fields.is_primary}
                    description={labels.field_descriptions.is_primary}
                    error={errors.is_primary}
                    checkbox={{
                        checked: data.is_primary,
                        disabled,
                        onCheckedChange: (checked) =>
                            change('is_primary', checked === true),
                    }}
                />
                <CheckboxField
                    label={labels.fields.is_billing}
                    description={labels.field_descriptions.is_billing}
                    error={errors.is_billing}
                    checkbox={{
                        checked: data.is_billing,
                        disabled,
                        onCheckedChange: (checked) =>
                            change('is_billing', checked === true),
                    }}
                />
            </Grid>
        </>
    );
}
