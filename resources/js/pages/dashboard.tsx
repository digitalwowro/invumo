import { Head } from '@inertiajs/react';
import { PageFrame } from '@/components/app/page-frame';
import { PageHeader } from '@/components/app/page-header';
import type { DashboardTranslations } from '@/types';

export default function Dashboard({
    translations,
}: {
    translations: DashboardTranslations;
}) {
    return (
        <>
            <Head title={translations.title} />
            <PageFrame>
                <PageHeader
                    title={translations.title}
                    subtitle={translations.subtitle}
                />
            </PageFrame>
        </>
    );
}
