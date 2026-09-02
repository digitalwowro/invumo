import { Head, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { ActionLink } from '@/components/app/action-link';
import { Stack } from '@/components/app/layout';
import { PageFrame } from '@/components/app/page-frame';
import { PageHeader } from '@/components/app/page-header';
import { SystemMessage } from '@/components/app/system-message';
import { CustomerTable } from '@/features/customers/components/customer-table';
import type {
    CustomerCursorPage,
    CustomerFilters,
    CustomerOption,
    CustomerListSummary,
    CustomerTranslations,
} from '@/types/customer';

type Props = {
    customers: CustomerCursorPage;
    filters: CustomerFilters;
    summary: CustomerListSummary;
    countryOptions: CustomerOption[];
    abilities: { create: boolean; delete: boolean };
    indexUrl: string;
    createUrl: string;
    status?: string;
    translations: CustomerTranslations;
};

export default function CustomersIndex({
    customers,
    filters,
    summary,
    countryOptions,
    abilities,
    indexUrl,
    createUrl,
    status,
    translations,
}: Props) {
    const labels = translations.index;
    const commonLabels = usePage().props.i18n.common.operational_list;

    return (
        <>
            <Head title={labels.head_title} />
            <PageFrame width="full">
                <Stack gap="2xl">
                    <PageHeader
                        title={labels.title}
                        subtitle={labels.description}
                        actionsPlacement="top-right"
                        actions={
                            abilities.create ? (
                                <ActionLink href={createUrl}>
                                    <Plus
                                        aria-hidden="true"
                                        data-icon="inline-start"
                                    />
                                    {labels.create}
                                </ActionLink>
                            ) : undefined
                        }
                    />
                    {status && <SystemMessage title={status} tone="money" />}
                    <CustomerTable
                        page={customers}
                        filters={filters}
                        summary={summary}
                        countryOptions={countryOptions}
                        indexUrl={indexUrl}
                        labels={labels}
                        commonLabels={commonLabels}
                    />
                </Stack>
            </PageFrame>
        </>
    );
}
