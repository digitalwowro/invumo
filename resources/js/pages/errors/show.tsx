import { Head } from '@inertiajs/react';
import { ActionLink } from '@/components/app/action-link';
import { Stack } from '@/components/app/layout';
import { SystemMessage } from '@/components/app/system-message';
import { home } from '@/routes';
import type { ErrorPageTranslations } from '@/types';

type Props = {
    status: number;
    translations: ErrorPageTranslations;
};

export default function ErrorPage({ status, translations }: Props) {
    const { page } = translations;

    return (
        <>
            <Head title={page.headTitle} />
            <Stack gap="xl">
                <SystemMessage
                    title={page.title}
                    description={page.description}
                    tone={status >= 500 ? 'error' : 'warning'}
                    action={
                        <ActionLink href={home()}>{page.action}</ActionLink>
                    }
                />
            </Stack>
        </>
    );
}
