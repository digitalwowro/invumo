import { Head } from '@inertiajs/react';
import { Users } from 'lucide-react';
import { ActionLink } from '@/components/app/action-link';
import { PageFrame } from '@/components/app/page-frame';
import { PageHeader } from '@/components/app/page-header';
import type { DashboardTranslations } from '@/types';

export default function Dashboard({
    company,
    membersUrl,
    translations,
}: {
    company: { name: string };
    membersUrl: string;
    translations: DashboardTranslations;
}) {
    return (
        <>
            <Head title={translations.title} />
            <PageFrame>
                <PageHeader
                    title={translations.title}
                    subtitle={`${company.name} · ${translations.subtitle}`}
                    actions={
                        <ActionLink href={membersUrl} variant="secondary">
                            <Users aria-hidden="true" />
                            {translations.members}
                        </ActionLink>
                    }
                />
            </PageFrame>
        </>
    );
}
