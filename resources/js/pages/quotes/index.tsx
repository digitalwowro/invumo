import { Head, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { ActionLink } from '@/components/app/action-link';
import { Stack } from '@/components/app/layout';
import { PageFrame } from '@/components/app/page-frame';
import { PageHeader } from '@/components/app/page-header';
import { SystemMessage } from '@/components/app/system-message';
import { QuoteTable } from '@/features/quotes/components/quote-table';
import type {
    QuoteCursorPage,
    QuoteFilters,
    QuoteListDatePresets,
    QuoteListSummary,
    QuoteTranslations,
} from '@/types/quote';

type Props = {
    quotes: QuoteCursorPage;
    filters: QuoteFilters;
    summary: QuoteListSummary;
    datePresets: QuoteListDatePresets;
    indexUrl: string;
    createUrl: string;
    status?: string;
    translations: QuoteTranslations;
};

export default function QuoteIndex(props: Props) {
    const { errors, i18n } = usePage().props;
    const commonLabels = i18n.common.operational_list;

    return (
        <>
            <Head title={props.translations.index.head_title} />
            <PageFrame width="full">
                <Stack gap="2xl">
                    <PageHeader
                        title={props.translations.index.title}
                        subtitle={props.translations.index.description}
                        actionsPlacement="top-right"
                        actions={
                            <ActionLink href={props.createUrl}>
                                <Plus
                                    aria-hidden="true"
                                    data-icon="inline-start"
                                />
                                {props.translations.index.create}
                            </ActionLink>
                        }
                    />
                    {props.status && (
                        <SystemMessage title={props.status} tone="money" />
                    )}
                    {errors.quote && (
                        <SystemMessage title={errors.quote} tone="error" />
                    )}
                    <QuoteTable
                        page={props.quotes}
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
