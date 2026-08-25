import { Form } from '@inertiajs/react';
import { FormActions, SubmitButton } from '@/components/app/form-actions';
import { CheckboxField, TextField } from '@/components/app/form-field';
import { FormSection } from '@/components/app/form-section';
import { Grid } from '@/components/app/layout';
import { UnsavedChangesGuard } from '@/components/app/unsaved-changes-guard';
import type { CompanyTaxPresetTranslations } from '@/types/company-tax';

type Props = {
    storeUrl: string;
    labels: CompanyTaxPresetTranslations;
};

export function TaxPresetCreateForm({ storeUrl, labels }: Props) {
    return (
        <Form action={storeUrl} method="post" resetOnSuccess>
            {({ errors, isDirty, processing }) => (
                <FormSection
                    title={labels.create_title}
                    description={labels.create_description}
                    actions={
                        <FormActions>
                            <SubmitButton processing={processing}>
                                {labels.create}
                            </SubmitButton>
                        </FormActions>
                    }
                >
                    <UnsavedChangesGuard
                        active={isDirty && !processing}
                        message={labels.unsaved_warning}
                    />
                    <Grid columns={2} gap="lg">
                        <TextField
                            id="tax-preset-name"
                            label={labels.fields.name}
                            error={errors.name}
                            input={{
                                name: 'name',
                                required: true,
                                maxLength: 120,
                            }}
                        />
                        <TextField
                            id="tax-preset-percentage"
                            label={labels.fields.percentage}
                            description={labels.field_descriptions.percentage}
                            error={errors.percentage}
                            input={{
                                name: 'percentage',
                                required: true,
                                inputMode: 'decimal',
                                defaultValue: '0',
                            }}
                        />
                    </Grid>
                    <CheckboxField
                        id="tax-preset-default"
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
