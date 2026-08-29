import { Head } from '@inertiajs/react';
import { CompanyErasure } from '@/features/companies/components/company-erasure';
import type { CompaniesUiTranslations } from '@/types';
import type { DependencyGuard } from '@/types/dependency-guard';

type Props = {
    erasure: {
        url: string;
        companyName: string;
        stateVersion: string;
        guard: DependencyGuard;
    };
    translations: CompaniesUiTranslations;
};

export default function CompanyDataLifecycle({ erasure, translations }: Props) {
    const labels = translations.settings.data_lifecycle;

    return (
        <>
            <Head title={labels.head_title} />
            <CompanyErasure {...erasure} labels={labels} />
        </>
    );
}
