import { useState } from 'react';
import { ChoiceField } from '@/components/app/choice-field';
import { FormActions, SaveButton } from '@/components/app/form-actions';
import { CheckboxField, TextField } from '@/components/app/form-field';
import { FormSection } from '@/components/app/form-section';
import { Grid } from '@/components/app/layout';
import { SelectField } from '@/components/app/select-field';
import type { CompanyConfiguration, CompanyOption } from '@/types';
import type { CompanySettingsTranslations } from '@/types/company-settings';

type Props = {
    configuration: CompanyConfiguration;
    timezoneOptions: CompanyOption[];
    currencyOptions: CompanyOption[];
    currencyDisplayOptions: CompanyOption[];
    errors: Record<string, string>;
    labels: CompanySettingsTranslations['profile'];
    processing: boolean;
    dirty: boolean;
};

export function CompanyDefaultFields({
    configuration,
    timezoneOptions,
    currencyOptions,
    currencyDisplayOptions,
    errors,
    labels,
    processing,
    dirty,
}: Props) {
    const [timezone, setTimezone] = useState(configuration.timezone ?? '');
    const [automationTime, setAutomationTime] = useState(
        configuration.automationLocalTime,
    );
    const scheduleChanged =
        configuration.timezone !== null &&
        (timezone !== configuration.timezone ||
            automationTime !== configuration.automationLocalTime);

    return (
        <>
            <FormSection
                title={labels.schedule_title}
                description={labels.schedule_description}
            >
                <Grid columns={2} gap="lg">
                    <SelectField
                        id="timezone"
                        name="timezone"
                        label={labels.fields.timezone}
                        error={errors.timezone}
                        placeholder={labels.timezone_placeholder}
                        value={timezone}
                        onValueChange={setTimezone}
                        required
                        options={timezoneOptions}
                    />
                    <TextField
                        id="automation_local_time"
                        label={labels.fields.automation_local_time}
                        error={errors.automation_local_time}
                        input={{
                            type: 'time',
                            name: 'automation_local_time',
                            value: automationTime,
                            onChange: (event) =>
                                setAutomationTime(event.target.value),
                            required: true,
                        }}
                    />
                </Grid>
                {scheduleChanged && (
                    <CheckboxField
                        id="confirm_schedule_change"
                        label={labels.schedule_confirmation}
                        error={errors.confirm_schedule_change}
                        checkbox={{
                            name: 'confirm_schedule_change',
                            value: '1',
                        }}
                    />
                )}
            </FormSection>

            <FormSection
                title={labels.currency_title}
                description={labels.currency_description}
                actions={
                    <FormActions>
                        <SaveButton processing={processing} dirty={dirty}>
                            {labels.save}
                        </SaveButton>
                    </FormActions>
                }
            >
                <Grid columns={2} gap="lg">
                    <SelectField
                        id="currency_code"
                        name="currency_code"
                        label={labels.fields.currency_code}
                        error={errors.currency_code}
                        placeholder={labels.currency_placeholder}
                        defaultValue={configuration.currencyCode ?? undefined}
                        required
                        options={currencyOptions}
                    />
                    <TextField
                        id="currency_precision"
                        label={labels.fields.currency_precision}
                        error={errors.currency_precision}
                        input={{
                            type: 'number',
                            name: 'currency_precision',
                            defaultValue:
                                configuration.currencyPrecision ?? undefined,
                            required: true,
                            min: 0,
                            max: 8,
                            step: 1,
                            inputMode: 'numeric',
                        }}
                    />
                </Grid>
                <ChoiceField
                    id="currency_display_style"
                    name="currency_display_style"
                    label={labels.fields.currency_display_style}
                    error={errors.currency_display_style}
                    defaultValue={
                        configuration.currencyDisplayStyle ?? undefined
                    }
                    required
                    options={currencyDisplayOptions}
                />
            </FormSection>
        </>
    );
}
