import { Head } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { ActionLink } from '@/components/app/action-link';
import { Stack } from '@/components/app/layout';
import { PageFrame } from '@/components/app/page-frame';
import { PageHeader } from '@/components/app/page-header';
import { SystemMessage } from '@/components/app/system-message';
import { RecurringTemplateTable } from '@/features/recurring/components/recurring-template-table';
import type {
    RecurringTemplateCursorPage,
    RecurringTemplateFilters,
    RecurringTranslations,
} from '@/types/recurring';

type Props = {
    templates: RecurringTemplateCursorPage;
    filters: RecurringTemplateFilters;
    indexUrl: string;
    createUrl: string;
    status?: string;
    translations: RecurringTranslations;
};

export default function RecurringIndex(props: Props) {
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
                    <RecurringTemplateTable
                        page={props.templates}
                        filters={props.filters}
                        indexUrl={props.indexUrl}
                        labels={props.translations}
                    />
                </Stack>
            </PageFrame>
        </>
    );
}
