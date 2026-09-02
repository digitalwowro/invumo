import { Form } from '@inertiajs/react';
import { FormActions, SaveButton } from '@/components/app/form-actions';
import { TextField } from '@/components/app/form-field';
import { FormSection } from '@/components/app/form-section';
import { Grid } from '@/components/app/layout';
import { SelectField } from '@/components/app/select-field';
import { UnsavedChangesGuard } from '@/components/app/unsaved-changes-guard';
import { interpolate } from '@/lib/translations';
import type { CustomerDefaultsFormProps } from '@/types/customer-defaults';

export function CustomerDefaultsForm({
    defaults,
    currencyOptions,
    languageOptions,
    taxPresetOptions,
    companyPaymentTermDays,
    maxPaymentTermDays,
    updateUrl,
    labels,
}: CustomerDefaultsFormProps) {
    const disabled = updateUrl === null;
    const companyPaymentTerm = companyPaymentTermDays
        ? interpolate(labels.resolved_payment_term, {
              value: companyPaymentTermDays,
          })
        : labels.not_configured;
    const inheritedPaymentTerm = interpolate(labels.inherit_payment_term, {
        value: companyPaymentTerm,
    });

    return (
        <Form
            action={updateUrl ?? ''}
            method="patch"
            options={{ preserveScroll: true }}
            setDefaultsOnSuccess
            disableWhileProcessing
        >
            {({ errors, isDirty, processing }) => (
                <>
                    <UnsavedChangesGuard
                        active={!disabled && isDirty && !processing}
                        message={labels.unsaved_warning}
                    />
                    <FormSection
                        title={labels.title}
                        description={labels.form_description}
                        actions={
                            !disabled ? (
                                <FormActions>
                                    <SaveButton
                                        processing={processing}
                                        dirty={isDirty}
                                    >
                                        {labels.save}
                                    </SaveButton>
                                </FormActions>
                            ) : undefined
                        }
                    >
                        <Grid gap="lg">
                            <SelectField
                                name="currency_id"
                                label={labels.fields.currency_id}
                                description={
                                    labels.field_descriptions.currency_id
                                }
                                error={errors.currency_id}
                                defaultValue={defaults.currencyId ?? 'INHERIT'}
                                options={currencyOptions}
                                disabled={disabled}
                                required
                            />
                            <SelectField
                                name="document_language"
                                label={labels.fields.document_language}
                                description={
                                    labels.field_descriptions.document_language
                                }
                                error={errors.document_language}
                                defaultValue={
                                    defaults.documentLanguage ?? 'INHERIT'
                                }
                                options={languageOptions}
                                disabled={disabled}
                                required
                            />
                            <TextField
                                label={labels.fields.payment_term_days}
                                description={
                                    labels.field_descriptions.payment_term_days
                                }
                                inheritedCaption={inheritedPaymentTerm}
                                error={errors.payment_term_days}
                                input={{
                                    name: 'payment_term_days',
                                    type: 'number',
                                    defaultValue:
                                        defaults.paymentTermDays ?? '',
                                    min: 0,
                                    max: maxPaymentTermDays,
                                    step: 1,
                                    inputMode: 'numeric',
                                    disabled,
                                }}
                            />
                            <SelectField
                                name="tax_preset_id"
                                label={labels.fields.tax_preset_id}
                                description={
                                    labels.field_descriptions.tax_preset_id
                                }
                                error={errors.tax_preset_id}
                                defaultValue={defaults.taxPresetId ?? 'INHERIT'}
                                options={taxPresetOptions}
                                disabled={disabled}
                                required
                            />
                        </Grid>
                    </FormSection>
                </>
            )}
        </Form>
    );
}
