import { Head, usePage } from '@inertiajs/react';
import { Stack } from '@/components/app/layout';
import { SectionHeader } from '@/components/app/section-header';
import { SystemMessage } from '@/components/app/system-message';
import { CompanyCurrencyCreateForm } from '@/features/companies/components/company-currency-create-form';
import { CompanyCurrencyTable } from '@/features/companies/components/company-currency-table';
import type { CompaniesUiTranslations, CompanyOption } from '@/types';
import type { CompanyCurrency } from '@/types/company-currency';

type Props = {
    currencies: CompanyCurrency[];
    currencyOptions: CompanyOption[];
    storeUrl: string;
    status?: string;
    translations: CompaniesUiTranslations;
};

export default function CompanyCurrencies({
    currencies,
    currencyOptions,
    storeUrl,
    status,
    translations,
}: Props) {
    const { i18n, errors } = usePage().props;
    const labels = translations.settings.currencies;

    return (
        <>
            <Head title={labels.head_title} />
            <Stack gap="2xl">
                <SectionHeader
                    title={labels.title}
                    description={labels.description}
                />
                {status && <SystemMessage title={status} tone="money" />}
                {errors.currency && (
                    <SystemMessage title={errors.currency} tone="error" />
                )}
                <CompanyCurrencyCreateForm
                    storeUrl={storeUrl}
                    currencyOptions={currencyOptions}
                    labels={labels}
                />
                <Stack gap="lg">
                    <SectionHeader
                        title={labels.list_title}
                        description={labels.list_description}
                    />
                    <CompanyCurrencyTable
                        currencies={currencies}
                        labels={labels}
                        cancelLabel={i18n.common.actions.cancel}
                        closeLabel={i18n.common.accessibility.close_navigation}
                    />
                </Stack>
            </Stack>
        </>
    );
}
