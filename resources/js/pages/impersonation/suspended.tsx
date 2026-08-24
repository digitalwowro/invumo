import { Head } from '@inertiajs/react';
import { Stack } from '@/components/app/layout';
import { PageFrame } from '@/components/app/page-frame';
import { PageHeader } from '@/components/app/page-header';
import { SystemMessage } from '@/components/app/system-message';

export default function SuspendedImpersonation({
    translations,
}: {
    translations: { title: string; description: string };
}) {
    return (
        <>
            <Head title={translations.title} />
            <PageFrame>
                <Stack gap="2xl">
                    <PageHeader title={translations.title} />
                    <SystemMessage
                        title={translations.title}
                        description={translations.description}
                        tone="warning"
                    />
                </Stack>
            </PageFrame>
        </>
    );
}
