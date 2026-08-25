import { Head } from '@inertiajs/react';
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
    CustomerTranslations,
} from '@/types/customer';

type Props = {
    customers: CustomerCursorPage;
    filters: CustomerFilters;
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
    countryOptions,
    abilities,
    indexUrl,
    createUrl,
    status,
    translations,
}: Props) {
    const labels = translations.index;

    return (
        <>
            <Head title={labels.head_title} />
            <PageFrame>
                <Stack gap="2xl">
                    <PageHeader
                        title={labels.title}
                        subtitle={labels.description}
                        actions={
                            abilities.create ? (
                                <ActionLink href={createUrl}>
                                    <Plus aria-hidden="true" />
                                    {labels.create}
                                </ActionLink>
                            ) : undefined
                        }
                    />
                    {status && <SystemMessage title={status} tone="money" />}
                    <CustomerTable
                        page={customers}
                        filters={filters}
                        countryOptions={countryOptions}
                        indexUrl={indexUrl}
                        labels={labels}
                    />
                </Stack>
            </PageFrame>
        </>
    );
}
