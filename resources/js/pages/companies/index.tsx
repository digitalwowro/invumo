import { Head } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { ActionLink } from '@/components/app/action-link';
import { Stack } from '@/components/app/layout';
import { PageFrame } from '@/components/app/page-frame';
import { PageHeader } from '@/components/app/page-header';
import { CompanyList } from '@/features/companies/components/company-list';
import { create as createCompany } from '@/routes/companies';
import type { CompaniesUiTranslations, CompanySummary } from '@/types';

type Props = {
    companies: CompanySummary[];
    translations: CompaniesUiTranslations;
};

export default function CompaniesIndex({ companies, translations }: Props) {
    const labels = translations.index;

    return (
        <>
            <Head title={labels.head_title} />
            <PageFrame>
                <Stack gap="2xl">
                    <PageHeader
                        title={labels.title}
                        subtitle={labels.description}
                        actions={
                            <ActionLink href={createCompany()}>
                                <Plus aria-hidden="true" />
                                {labels.create}
                            </ActionLink>
                        }
                    />
                    <CompanyList
                        companies={companies}
                        translations={translations}
                        createUrl={createCompany().url}
                    />
                </Stack>
            </PageFrame>
        </>
    );
}
