import { Head } from '@inertiajs/react';
import { Stack } from '@/components/app/layout';
import { SystemMessage } from '@/components/app/system-message';
import { CompanyAppearanceForm } from '@/features/companies/components/company-appearance-form';
import type { CompaniesUiTranslations } from '@/types';
import type {
    CompanyAppearance,
    CompanyBrandColorPreset,
} from '@/types/company-appearance';

type Props = {
    company: { id: string; name: string };
    appearance: CompanyAppearance;
    brandColorPresets: CompanyBrandColorPreset[];
    updateUrl: string;
    status?: string;
    translations: CompaniesUiTranslations;
};

export default function CompanyAppearancePage({
    company,
    appearance,
    brandColorPresets,
    updateUrl,
    status,
    translations,
}: Props) {
    const labels = translations.settings.appearance;

    return (
        <>
            <Head title={labels.head_title} />
            <Stack gap="2xl">
                {status && <SystemMessage title={status} tone="money" />}
                <CompanyAppearanceForm
                    companyName={company.name}
                    appearance={appearance}
                    presets={brandColorPresets}
                    updateUrl={updateUrl}
                    labels={labels}
                />
            </Stack>
        </>
    );
}
