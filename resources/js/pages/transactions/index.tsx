import { Head, usePage } from '@inertiajs/react';
import { Stack } from '@/components/app/layout';
import { PageFrame } from '@/components/app/page-frame';
import { PageHeader } from '@/components/app/page-header';
import { CompanyTransactionTable } from '@/features/transactions/components/company-transaction-table';
import type {
    CompanyTransactionCursorPage,
    CompanyTransactionFilters,
    CompanyTransactionListDatePresets,
    CompanyTransactionListSummary,
    CompanyTransactionTranslations,
} from '@/types/company-transaction';

type Props = {
    transactions: CompanyTransactionCursorPage;
    filters: CompanyTransactionFilters;
    summary: CompanyTransactionListSummary;
    datePresets: CompanyTransactionListDatePresets;
    indexUrl: string;
    translations: CompanyTransactionTranslations;
};

export default function CompanyTransactionIndex(props: Props) {
    const commonLabels = usePage().props.i18n.common.operational_list;

    return (
        <>
            <Head title={props.translations.head_title} />
            <PageFrame width="full">
                <Stack gap="2xl">
                    <PageHeader
                        title={props.translations.title}
                        subtitle={props.translations.description}
                    />
                    <CompanyTransactionTable
                        page={props.transactions}
                        filters={props.filters}
                        summary={props.summary}
                        datePresets={props.datePresets}
                        indexUrl={props.indexUrl}
                        labels={props.translations}
                        commonLabels={commonLabels}
                    />
                </Stack>
            </PageFrame>
        </>
    );
}
