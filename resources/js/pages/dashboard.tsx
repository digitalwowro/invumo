import { Head, usePage } from '@inertiajs/react';
import { Plus, ReceiptText } from 'lucide-react';
import { ActionLink } from '@/components/app/action-link';
import { Stack } from '@/components/app/layout';
import { PageFrame } from '@/components/app/page-frame';
import { PageHeader } from '@/components/app/page-header';
import { SystemMessage } from '@/components/app/system-message';
import { DashboardContent } from '@/features/dashboard/components/dashboard-content';
import { interpolate } from '@/lib/translations';
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
    const { errors } = usePage().props;

    return (
        <>
            <Head title={translations.title} />
            <PageFrame width="full">
                <Stack gap="2xl">
                    <PageHeader
                        title={translations.title}
                        subtitle={`${company.name} · ${interpolate(
                            translations.subtitle,
                            { date: dashboard.asOfDate },
                        )}`}
                        actionsPlacement="top-right"
                        actions={
                            <>
                                <ActionLink
                                    href={dashboard.invoicesUrl}
                                    variant="secondary"
                                >
                                    <ReceiptText aria-hidden="true" />
                                    {translations.view_invoices}
                                </ActionLink>
                                {dashboard.createInvoiceUrl && (
                                    <ActionLink
                                        href={dashboard.createInvoiceUrl}
                                    >
                                        <Plus aria-hidden="true" />
                                        {translations.new_invoice}
                                    </ActionLink>
                                )}
                            </>
                        }
                    />
                    {errors.invoice && (
                        <SystemMessage title={errors.invoice} tone="error" />
                    )}
                    <DashboardContent
                        dashboard={dashboard}
                        labels={translations}
                    />
                </Stack>
            </PageFrame>
        </>
    );
}
