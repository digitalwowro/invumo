import { Head } from '@inertiajs/react';
import { Stack } from '@/components/app/layout';
import { SystemMessage } from '@/components/app/system-message';
import { CompanyConfigurationForm } from '@/features/companies/components/company-configuration-form';
import type {
    CompaniesUiTranslations,
    CompanyConfiguration,
    CompanyOption,
} from '@/types';

type Props = {
    configuration: CompanyConfiguration;
    countryOptions: CompanyOption[];
    currencyOptions: CompanyOption[];
    timezoneOptions: CompanyOption[];
    currencyDisplayOptions: CompanyOption[];
    updateUrl: string;
    status?: string;
    translations: CompaniesUiTranslations;
};

export default function CompanyProfile({
    configuration,
    countryOptions,
    currencyOptions,
    timezoneOptions,
    currencyDisplayOptions,
    updateUrl,
    status,
    translations,
}: Props) {
    const labels = translations.settings.profile;

    return (
        <>
            <Head title={labels.head_title} />
            <Stack gap="2xl">
                {status && <SystemMessage title={status} tone="money" />}
                <CompanyConfigurationForm
                    configuration={configuration}
                    countryOptions={countryOptions}
                    currencyOptions={currencyOptions}
                    timezoneOptions={timezoneOptions}
                    currencyDisplayOptions={currencyDisplayOptions}
                    updateUrl={updateUrl}
                    labels={labels}
                />
            </Stack>
        </>
    );
}
