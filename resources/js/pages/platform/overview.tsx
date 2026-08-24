import { Head, usePage } from '@inertiajs/react';
import { Stack } from '@/components/app/layout';
import { PageFrame } from '@/components/app/page-frame';
import { PageHeader } from '@/components/app/page-header';
import { PlatformOverviewContent } from '@/features/platform/components/platform-overview-content';
import type { PlatformActivityRow, PlatformTranslations } from '@/types';

export default function PlatformOverview({
    counts,
    recentActivity,
    translations,
}: {
    counts: {
        users: number;
        accounts: number;
        companies: number;
        operators: number;
    };
    recentActivity: PlatformActivityRow[];
    translations: PlatformTranslations;
}) {
    const { i18n } = usePage().props;

    return (
        <>
            <Head title={translations.overview.head_title} />
            <PageFrame>
                <Stack gap="2xl">
                    <PageHeader
                        title={translations.overview.title}
                        subtitle={translations.overview.description}
                    />
                    <PlatformOverviewContent
                        counts={counts}
                        recentActivity={recentActivity}
                        translations={translations}
                        locale={i18n.locale}
                    />
                </Stack>
            </PageFrame>
        </>
    );
}
