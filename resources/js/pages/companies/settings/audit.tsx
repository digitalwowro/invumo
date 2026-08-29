import { Head, usePage } from '@inertiajs/react';
import { Stack } from '@/components/app/layout';
import { SectionHeader } from '@/components/app/section-header';
import { CompanyAuditHistory } from '@/features/audit/components/company-audit-history';
import type { CompaniesUiTranslations } from '@/types/company';
import type {
    CompanyAuditCursorPage,
    CompanyAuditFilters,
} from '@/types/company-audit';

type Props = {
    audit: CompanyAuditCursorPage;
    filters: CompanyAuditFilters;
    targetOptions: string[];
    timezone: string;
    indexUrl: string;
    translations: CompaniesUiTranslations;
};

export default function CompanyAuditPage(props: Props) {
    const { i18n } = usePage().props;
    const labels = props.translations.settings.audit;

    return (
        <>
            <Head title={labels.head_title} />
            <Stack gap="2xl">
                <SectionHeader
                    title={labels.title}
                    description={labels.description}
                />
                <CompanyAuditHistory
                    page={props.audit}
                    filters={props.filters}
                    targetOptions={props.targetOptions}
                    indexUrl={props.indexUrl}
                    timezone={props.timezone}
                    locale={i18n.locale}
                    closeLabel={i18n.common.accessibility.close_navigation}
                    labels={labels}
                />
            </Stack>
        </>
    );
}
