import { Link, router, usePage } from '@inertiajs/react';
import type { KeyboardEvent, MouseEvent } from 'react';
import { Cluster, Stack } from '@/components/app/layout';
import { OperationalListPagination } from '@/components/app/operational-list-pagination';
import { OperationalTable } from '@/components/app/operational-table';
import type {
    OperationalColumn,
    OperationalTableStateCopy,
} from '@/components/app/operational-table';
import {
    BodyStrong,
    SecondaryText,
    TableValue,
} from '@/components/app/typography';
import { StatusBadge } from '@/components/domain/status-badge';
import { Button } from '@/components/ui/button';
import { RecurringTemplateDeleteDialog } from '@/features/recurring/components/recurring-template-delete-dialog';
import { RecurringTemplateListSummaryCards } from '@/features/recurring/components/recurring-template-list-summary';
import { RecurringTemplateListTools } from '@/features/recurring/components/recurring-template-list-tools';
import {
    countRecurringTemplateFilters,
    recurringTemplateListQuery,
} from '@/features/recurring/lib/recurring-template-list-query';
import type { OperationalListTranslations } from '@/types/localization';
import type {
    RecurringTemplateCursorPage,
    RecurringTemplateFilters,
    RecurringTemplateListSummary,
    RecurringTemplateRow,
} from '@/types/recurring';
import type { RecurringTranslations } from '@/types/recurring-translations';
import type { Status } from '@/types/status';

type Props = {
    page: RecurringTemplateCursorPage;
    filters: RecurringTemplateFilters;
    summary: RecurringTemplateListSummary;
    indexUrl: string;
    labels: RecurringTranslations;
    commonLabels: OperationalListTranslations;
};

export function RecurringTemplateTable(props: Props) {
    const { i18n } = usePage().props;
    const labels = props.labels.index;
    const columns: OperationalColumn<RecurringTemplateRow>[] = [
        {
            key: 'template',
            label: labels.columns.template,
            kind: 'identity',
            render: (template) => (
                <div className="flex flex-col gap-1">
                    <BodyStrong>{template.internalName}</BodyStrong>
                    <SecondaryText>{template.customerName}</SecondaryText>
                </div>
            ),
        },
        {
            key: 'reference',
            label: props.commonLabels.columns.customer_reference,
            kind: 'data',
            render: (template) => (
                <TableValue>
                    {template.customerReference ??
                        props.commonLabels.not_available}
                </TableValue>
            ),
        },
        {
            key: 'schedule',
            label: labels.columns.schedule,
            kind: 'data',
            render: (template) => (
                <div className="flex flex-col gap-1">
                    <TableValue>
                        {template.nextRunAt === null
                            ? props.commonLabels.not_available
                            : new Intl.DateTimeFormat(i18n.locale, {
                                  dateStyle: 'medium',
                                  timeStyle: 'short',
                              }).format(new Date(template.nextRunAt))}
                    </TableValue>
                    <SecondaryText>
                        {template.lastRunOutcome === null
                            ? props.commonLabels.not_available
                            : labels.outcomes[template.lastRunOutcome]}
                    </SecondaryText>
                </div>
            ),
        },
        {
            key: 'state',
            label: props.commonLabels.columns.status,
            kind: 'status',
            render: (template) => (
                <StatusBadge
                    status={template.state.toLowerCase() as Status}
                    label={labels.states[template.state]}
                />
            ),
        },
        {
            key: 'automation',
            label: labels.columns.automation,
            kind: 'status',
            render: (template) => (
                <StatusBadge
                    status={
                        template.currencyReviewRequired
                            ? 'paused'
                            : template.automaticEmailEnabled
                              ? 'active'
                              : 'suppressed'
                    }
                    label={
                        template.currencyReviewRequired
                            ? labels.automation.review_required
                            : template.automaticEmailEnabled
                              ? labels.automation.enabled
                              : labels.automation.disabled
                    }
                />
            ),
        },
        {
            key: 'actions',
            label: props.commonLabels.columns.actions,
            kind: 'actions',
            render: (template) => (
                <TemplateActions template={template} labels={props.labels} />
            ),
        },
    ];
    const filtered =
        countRecurringTemplateFilters(props.filters) > 0 ||
        props.filters.sort !== 'recent' ||
        props.filters.perPage !== 25;
    const state = props.page.items.length
        ? 'ready'
        : filtered
          ? 'no-results'
          : 'empty';
    const stateCopy: OperationalTableStateCopy = {
        loading: labels.loading,
        emptyTitle: labels.empty_title,
        emptyDescription: labels.empty_description,
        noResultsTitle: labels.no_results_title,
        noResultsDescription: labels.no_results_description,
        errorTitle: labels.error_title,
        errorDescription: labels.error_description,
    };

    return (
        <Stack gap="lg">
            <RecurringTemplateListSummaryCards
                action={props.indexUrl}
                filters={props.filters}
                summary={props.summary}
                labels={labels}
                commonLabels={props.commonLabels}
            />
            <OperationalTable
                ariaLabel={labels.title}
                columns={columns}
                rows={props.page.items}
                rowKey={(template) => template.id}
                rowLabel={(template) => template.internalName}
                onRowActivate={(template) => router.visit(template.editUrl)}
                state={state}
                stateCopy={stateCopy}
                toolbar={
                    <RecurringTemplateListTools
                        action={props.indexUrl}
                        filters={props.filters}
                        labels={labels}
                        commonLabels={props.commonLabels}
                    />
                }
                footer={
                    <OperationalListPagination
                        shownCount={props.page.items.length}
                        previousUrl={props.page.previousUrl}
                        nextUrl={props.page.nextUrl}
                        perPage={props.filters.perPage}
                        onPerPageChange={(perPage) =>
                            router.get(
                                props.indexUrl,
                                recurringTemplateListQuery({
                                    ...props.filters,
                                    perPage,
                                }),
                                { preserveScroll: true, replace: true },
                            )
                        }
                        labels={props.commonLabels}
                    />
                }
            />
        </Stack>
    );
}

function TemplateActions(props: {
    template: RecurringTemplateRow;
    labels: RecurringTranslations;
}) {
    const stop = (event: MouseEvent<HTMLDivElement>) => event.stopPropagation();
    const stopKeyboard = (event: KeyboardEvent<HTMLDivElement>) =>
        event.stopPropagation();

    return (
        <div onClick={stop} onKeyDown={stopKeyboard}>
            <Cluster gap="sm">
                <Button asChild variant="secondary">
                    <Link href={props.template.editUrl}>
                        {props.labels.index.columns.open}
                    </Link>
                </Button>
                {props.template.lastInvoiceUrl && (
                    <Button asChild variant="ghost">
                        <Link href={props.template.lastInvoiceUrl}>
                            {props.labels.index.columns.open_invoice}
                        </Link>
                    </Button>
                )}
                {props.template.canDelete && (
                    <RecurringTemplateDeleteDialog
                        url={props.template.deleteUrl}
                        highRisk={props.template.deletion.highRisk}
                        stateVersion={props.template.deletion.stateVersion}
                        guard={props.template.deletion.guard}
                        labels={props.labels.deletion}
                    />
                )}
            </Cluster>
        </div>
    );
}
