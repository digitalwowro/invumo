import { Form } from '@inertiajs/react';
import { FormActions, SubmitButton } from '@/components/app/form-actions';
import { CheckboxField, TextField } from '@/components/app/form-field';
import { FormSection } from '@/components/app/form-section';
import { Grid } from '@/components/app/layout';
import { SelectField } from '@/components/app/select-field';
import { UnsavedChangesGuard } from '@/components/app/unsaved-changes-guard';
import type { CompanyOption } from '@/types';
import type { CompanyCurrencyTranslations } from '@/types/company-currency';

type Props = {
    storeUrl: string;
    currencyOptions: CompanyOption[];
    labels: CompanyCurrencyTranslations;
};

export function CompanyCurrencyCreateForm({
    storeUrl,
    currencyOptions,
    labels,
}: Props) {
    const hasOptions = currencyOptions.length > 0;

    return (
        <Form action={storeUrl} method="post" resetOnSuccess>
            {({ errors, isDirty, processing }) => (
                <FormSection
                    title={labels.create_title}
                    description={labels.create_description}
                    actions={
                        <FormActions>
                            <SubmitButton
                                processing={processing}
                                disabled={!hasOptions}
                            >
                                {labels.add}
                            </SubmitButton>
                        </FormActions>
                    }
                >
                    <UnsavedChangesGuard
                        active={isDirty && !processing}
                        message={labels.unsaved_warning}
                    />
                    <Grid columns={2} gap="lg">
                        <SelectField
                            id="currency_code"
                            name="currency_code"
                            label={labels.fields.currency_code}
                            error={errors.currency_code}
                            placeholder={
                                hasOptions
                                    ? labels.code_placeholder
                                    : labels.no_options
                            }
                            required
                            disabled={!hasOptions}
                            testId="company-currency-code"
                            options={currencyOptions}
                        />
                        <TextField
                            id="currency_precision"
                            label={labels.fields.currency_precision}
                            description={
                                labels.field_descriptions.currency_precision
                            }
                            error={errors.currency_precision}
                            input={{
                                type: 'number',
                                name: 'currency_precision',
                                defaultValue: 2,
                                required: true,
                                min: 0,
                                max: 8,
                                step: 1,
                                inputMode: 'numeric',
                            }}
                        />
                    </Grid>
                    <CheckboxField
                        id="currency_is_default"
                        label={labels.fields.is_default}
                        description={labels.field_descriptions.is_default}
                        error={errors.is_default}
                        checkbox={{ name: 'is_default', value: '1' }}
                    />
                </FormSection>
            )}
        </Form>
    );
}
