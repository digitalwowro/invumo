import { Head } from '@inertiajs/react';
import { ReceiptText } from 'lucide-react';
import { ActionLink } from '@/components/app/action-link';
import { Stack } from '@/components/app/layout';
import { PageFrame } from '@/components/app/page-frame';
import { PageHeader } from '@/components/app/page-header';
import { DashboardContent } from '@/features/dashboard/components/dashboard-content';
import type { DashboardData, DashboardTranslations } from '@/types/dashboard';

export default function Dashboard({
    company,
    dashboard,
    translations,
}: {
    company: { name: string };
    dashboard: DashboardData;
    translations: DashboardTranslations;
}) {
    return (
        <>
            <Head title={translations.title} />
            <PageFrame width="full">
                <Stack gap="2xl">
                    <PageHeader
                        title={translations.title}
                        subtitle={`${company.name} · ${translations.subtitle}`}
                        actions={
                            <ActionLink
                                href={dashboard.invoicesUrl}
                                variant="secondary"
                            >
                                <ReceiptText aria-hidden="true" />
                                {translations.view_invoices}
                            </ActionLink>
                        }
                    />
                    <DashboardContent
                        dashboard={dashboard}
                        labels={translations}
                    />
                </Stack>
            </PageFrame>
        </>
    );
}
