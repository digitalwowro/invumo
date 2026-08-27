import { Head } from '@inertiajs/react';
import { Stack } from '@/components/app/layout';
import { PageFrame } from '@/components/app/page-frame';
import { PageHeader } from '@/components/app/page-header';
import { CompanyTransactionTable } from '@/features/transactions/components/company-transaction-table';
import type {
    CompanyTransactionCursorPage,
    CompanyTransactionFilters,
    CompanyTransactionTranslations,
} from '@/types/company-transaction';

type Props = {
    transactions: CompanyTransactionCursorPage;
    filters: CompanyTransactionFilters;
    indexUrl: string;
    translations: CompanyTransactionTranslations;
};

export default function CompanyTransactionIndex(props: Props) {
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
                        indexUrl={props.indexUrl}
                        labels={props.translations}
                    />
                </Stack>
            </PageFrame>
        </>
    );
}
