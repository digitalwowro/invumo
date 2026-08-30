import { Head, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { ActionLink } from '@/components/app/action-link';
import { Stack } from '@/components/app/layout';
import { PageFrame } from '@/components/app/page-frame';
import { PageHeader } from '@/components/app/page-header';
import { SystemMessage } from '@/components/app/system-message';
import { InvoiceTable } from '@/features/invoices/components/invoice-table';
import type {
    InvoiceCursorPage,
    InvoiceFilters,
    InvoiceListDatePresets,
    InvoiceListSummary,
    InvoiceTranslations,
} from '@/types/invoice';

type Props = {
    invoices: InvoiceCursorPage;
    filters: InvoiceFilters;
    summary: InvoiceListSummary;
    datePresets: InvoiceListDatePresets;
    indexUrl: string;
    createUrl: string | null;
    status?: string;
    translations: InvoiceTranslations;
};

export default function InvoiceIndex(props: Props) {
    const commonLabels = usePage().props.i18n.common.operational_list;

    return (
        <>
            <Head title={props.translations.index.head_title} />
            <PageFrame width="full">
                <Stack gap="2xl">
                    <PageHeader
                        title={props.translations.index.title}
                        subtitle={props.translations.index.description}
                        actions={
                            props.createUrl ? (
                                <ActionLink href={props.createUrl}>
                                    <Plus aria-hidden="true" />
                                    {props.translations.index.create}
                                </ActionLink>
                            ) : undefined
                        }
                    />
                    {props.status && (
                        <SystemMessage title={props.status} tone="money" />
                    )}
                    <InvoiceTable
                        page={props.invoices}
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
