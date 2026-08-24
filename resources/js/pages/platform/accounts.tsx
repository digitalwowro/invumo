import { Head } from '@inertiajs/react';
import { Stack } from '@/components/app/layout';
import { PageFrame } from '@/components/app/page-frame';
import { PageHeader } from '@/components/app/page-header';
import { SystemMessage } from '@/components/app/system-message';
import { PlatformAccountsTable } from '@/features/platform/components/platform-accounts-table';
import type {
    PlatformAccountRow,
    PlatformCursorPage,
    PlatformTranslations,
} from '@/types';

export default function PlatformAccounts({
    page,
    search,
    status,
    translations,
}: {
    page: PlatformCursorPage<PlatformAccountRow>;
    search: string;
    status?: string;
    translations: PlatformTranslations;
}) {
    return (
        <>
            <Head title={translations.accounts.head_title} />
            <PageFrame>
                <Stack gap="2xl">
                    <PageHeader
                        title={translations.accounts.title}
                        subtitle={translations.accounts.description}
                    />
                    {status && <SystemMessage title={status} tone="money" />}
                    <PlatformAccountsTable
                        page={page}
                        search={search}
                        translations={translations}
                    />
                </Stack>
            </PageFrame>
        </>
    );
}
