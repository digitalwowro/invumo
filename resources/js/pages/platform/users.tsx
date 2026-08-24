import { Head } from '@inertiajs/react';
import { Stack } from '@/components/app/layout';
import { PageFrame } from '@/components/app/page-frame';
import { PageHeader } from '@/components/app/page-header';
import { SystemMessage } from '@/components/app/system-message';
import { PlatformUsersTable } from '@/features/platform/components/platform-users-table';
import type {
    PlatformCursorPage,
    PlatformTranslations,
    PlatformUserRow,
} from '@/types';

export default function PlatformUsers({
    page,
    search,
    status,
    translations,
}: {
    page: PlatformCursorPage<PlatformUserRow>;
    search: string;
    status?: string;
    translations: PlatformTranslations;
}) {
    return (
        <>
            <Head title={translations.users.head_title} />
            <PageFrame>
                <Stack gap="2xl">
                    <PageHeader
                        title={translations.users.title}
                        subtitle={translations.users.description}
                    />
                    {status && <SystemMessage title={status} tone="money" />}
                    <PlatformUsersTable
                        page={page}
                        search={search}
                        translations={translations}
                    />
                </Stack>
            </PageFrame>
        </>
    );
}
