import { Head } from '@inertiajs/react';
import { Stack } from '@/components/app/layout';
import { PageFrame } from '@/components/app/page-frame';
import { PageHeader } from '@/components/app/page-header';
import { PlatformAuditTable } from '@/features/platform/components/platform-audit-table';
import type {
    PlatformAuditRow,
    PlatformCursorPage,
    PlatformTranslations,
} from '@/types';

export default function PlatformAudit({
    page,
    translations,
}: {
    page: PlatformCursorPage<PlatformAuditRow>;
    translations: PlatformTranslations;
}) {
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
                </Stack>
            </PageFrame>
        </>
    );
}
