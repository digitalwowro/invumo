import { Form } from '@inertiajs/react';
import { Stack } from '@/components/app/layout';
import { UnsavedChangesGuard } from '@/components/app/unsaved-changes-guard';
import { CompanyDefaultFields } from '@/features/companies/components/company-default-fields';
import { CompanyIdentityFields } from '@/features/companies/components/company-identity-fields';
import type {
    CompanyConfiguration,
    CompanyOption,
    CompanySettingsTranslations,
} from '@/types';

type Props = {
    configuration: CompanyConfiguration;
    countryOptions: CompanyOption[];
    currencyOptions: CompanyOption[];
    timezoneOptions: CompanyOption[];
    currencyDisplayOptions: CompanyOption[];
    updateUrl: string;
    labels: CompanySettingsTranslations['profile'];
};

export function CompanyConfigurationForm({
    configuration,
    countryOptions,
    currencyOptions,
    timezoneOptions,
    currencyDisplayOptions,
    updateUrl,
    labels,
}: Props) {
    return (
        <Form
            action={updateUrl}
            method="patch"
            options={{ preserveScroll: true }}
        >
            {({ errors, isDirty, processing }) => (
                <Stack gap="2xl">
                    <UnsavedChangesGuard
                        active={isDirty && !processing}
                        message={labels.unsaved_warning}
                    />
                    <CompanyIdentityFields
                        configuration={configuration}
                        countryOptions={countryOptions}
                        errors={errors}
                        labels={labels}
                    />
                    <CompanyDefaultFields
                        configuration={configuration}
                        timezoneOptions={timezoneOptions}
                        currencyOptions={currencyOptions}
                        currencyDisplayOptions={currencyDisplayOptions}
                        errors={errors}
                        labels={labels}
                        processing={processing}
                    />
                </Stack>
            )}
        </Form>
    );
}
