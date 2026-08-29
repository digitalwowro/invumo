import { Head, usePage } from '@inertiajs/react';
import { Stack } from '@/components/app/layout';
import { PageFrame } from '@/components/app/page-frame';
import { PageHeader } from '@/components/app/page-header';
import { PlatformAuditTable } from '@/features/platform/components/platform-audit-table';
import { PlatformErasureHistory } from '@/features/platform/components/platform-erasure-history';
import type {
    PlatformAuditRow,
    PlatformCursorPage,
    PlatformErasureRow,
    PlatformTranslations,
} from '@/types';

export default function PlatformAudit({
    page,
    erasurePage,
    translations,
}: {
    page: PlatformCursorPage<PlatformAuditRow>;
    erasurePage: PlatformCursorPage<PlatformErasureRow>;
    translations: PlatformTranslations;
}) {
    const { i18n } = usePage().props;

    return (
        <>
            <Head title={translations.audit.head_title} />
            <PageFrame>
                <Stack gap="2xl">
                    <PageHeader
                        title={translations.audit.title}
                        subtitle={translations.audit.description}
                    />
                    <PlatformAuditTable
                        page={page}
                        translations={translations}
                    />
                    <PlatformErasureHistory
                        page={erasurePage}
                        translations={translations}
                        locale={i18n.locale}
                    />
                </Stack>
            </PageFrame>
        </>
    );
}
