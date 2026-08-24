import { Head } from '@inertiajs/react';
import { Stack } from '@/components/app/layout';
import { PageFrame } from '@/components/app/page-frame';
import { PageHeader } from '@/components/app/page-header';
import { SystemMessage } from '@/components/app/system-message';
import { PlatformLifecycleTable } from '@/features/platform/components/platform-lifecycle-table';
import type {
    PlanStatus,
    PlatformAccountRow,
    PlatformCursorPage,
    PlatformPlan,
    PlatformTranslations,
} from '@/types';

export default function PlatformPlanLifecycle({
    page,
    plans,
    search,
    selectedStatus,
    selectedExpiryDays,
    cancelAtPeriodEndOnly,
    status,
    translations,
}: {
    page: PlatformCursorPage<PlatformAccountRow>;
    plans: PlatformPlan[];
    search: string;
    selectedStatus: PlanStatus | null;
    selectedExpiryDays: number | null;
    cancelAtPeriodEndOnly: boolean;
    status?: string;
    translations: PlatformTranslations;
}) {
    return (
        <>
            <Head title={translations.plan_lifecycle.head_title} />
            <PageFrame>
                <Stack gap="2xl">
                    <PageHeader
                        title={translations.plan_lifecycle.title}
                        subtitle={translations.plan_lifecycle.description}
                    />
                    {status && <SystemMessage title={status} tone="money" />}
                    <PlatformLifecycleTable
                        page={page}
                        plans={plans}
                        search={search}
                        selectedStatus={selectedStatus}
                        selectedExpiryDays={selectedExpiryDays}
                        cancelAtPeriodEndOnly={cancelAtPeriodEndOnly}
                        translations={translations}
                    />
                </Stack>
            </PageFrame>
        </>
    );
}
