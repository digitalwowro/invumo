import { Head, usePage } from '@inertiajs/react';
import { Stack } from '@/components/app/layout';
import { SectionHeader } from '@/components/app/section-header';
import { SystemMessage } from '@/components/app/system-message';
import { TaxPresetCreateForm } from '@/features/companies/components/tax-preset-create-form';
import { TaxPresetTable } from '@/features/companies/components/tax-preset-table';
import type { CompaniesUiTranslations } from '@/types/company';
import type { TaxPreset } from '@/types/company-tax';

type Props = {
    taxPresets: TaxPreset[];
    storeUrl: string;
    status?: string;
    translations: CompaniesUiTranslations;
};

export default function CompanyTaxes({
    taxPresets,
    storeUrl,
    status,
    translations,
}: Props) {
    const { i18n, errors } = usePage().props;
    const labels = translations.settings.taxes;

    return (
        <>
            <Head title={labels.head_title} />
            <Stack gap="2xl">
                <SectionHeader
                    title={labels.title}
                    description={labels.description}
                />
                {status && <SystemMessage title={status} tone="money" />}
                {errors.tax_preset && (
                    <SystemMessage title={errors.tax_preset} tone="error" />
                )}
                <TaxPresetCreateForm storeUrl={storeUrl} labels={labels} />
                <Stack gap="lg">
                    <SectionHeader
                        title={labels.list_title}
                        description={labels.list_description}
                    />
                    <TaxPresetTable
                        presets={taxPresets}
                        labels={labels}
                        cancelLabel={i18n.common.actions.cancel}
                        closeLabel={i18n.common.accessibility.close_navigation}
                    />
                </Stack>
            </Stack>
        </>
    );
}
