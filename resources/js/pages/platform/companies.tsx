import { Head } from '@inertiajs/react';
import { Stack } from '@/components/app/layout';
import { PageFrame } from '@/components/app/page-frame';
import { PageHeader } from '@/components/app/page-header';
import { PlatformCompaniesTable } from '@/features/platform/components/platform-companies-table';
import type {
    PlatformCompanyRow,
    PlatformCursorPage,
    PlatformTranslations,
} from '@/types';

export default function PlatformCompanies({
    page,
    search,
    translations,
}: {
    page: PlatformCursorPage<PlatformCompanyRow>;
    search: string;
    translations: PlatformTranslations;
}) {
    return (
        <>
            <Head title={translations.companies.head_title} />
            <PageFrame>
                <Stack gap="2xl">
                    <PageHeader
                        title={translations.companies.title}
                        subtitle={translations.companies.description}
                    />
                    <PlatformCompaniesTable
                        page={page}
                        search={search}
                        translations={translations}
                    />
                </Stack>
            </PageFrame>
        </>
    );
}
