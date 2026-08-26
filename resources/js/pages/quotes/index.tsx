import { Head } from '@inertiajs/react';
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
    QuoteTranslations,
} from '@/types/quote';

type Props = {
    quotes: QuoteCursorPage;
    filters: QuoteFilters;
    indexUrl: string;
    createUrl: string;
    status?: string;
    translations: QuoteTranslations;
};

export default function QuoteIndex(props: Props) {
    return (
        <>
            <Head title={props.translations.index.head_title} />
            <PageFrame width="full">
                <Stack gap="2xl">
                    <PageHeader
                        title={props.translations.index.title}
                        subtitle={props.translations.index.description}
                        actions={
                            <ActionLink href={props.createUrl}>
                                <Plus aria-hidden="true" />
                                {props.translations.index.create}
                            </ActionLink>
                        }
                    />
                    {props.status && (
                        <SystemMessage title={props.status} tone="money" />
                    )}
                    <QuoteTable
                        page={props.quotes}
                        filters={props.filters}
                        indexUrl={props.indexUrl}
                        labels={props.translations}
                    />
                </Stack>
            </PageFrame>
        </>
    );
}
