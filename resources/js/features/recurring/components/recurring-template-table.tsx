import { Link, router, usePage } from '@inertiajs/react';
import type { KeyboardEvent, MouseEvent } from 'react';
import { Cluster } from '@/components/app/layout';
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
import { RecurringTemplateListTools } from '@/features/recurring/components/recurring-template-list-tools';
import type {
    RecurringTemplateCursorPage,
    RecurringTemplateFilters,
    RecurringTemplateRow,
} from '@/types/recurring';
import type { RecurringTranslations } from '@/types/recurring-translations';
import type { Status } from '@/types/status';

type Props = {
    page: RecurringTemplateCursorPage;
    filters: RecurringTemplateFilters;
    indexUrl: string;
    labels: RecurringTranslations;
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
                <BodyStrong>{template.internalName}</BodyStrong>
            ),
        },
        {
            key: 'customer',
            label: labels.columns.customer,
            kind: 'text',
            render: (template) => (
                <TableValue>{template.customerName}</TableValue>
            ),
        },
        {
            key: 'reference',
            label: labels.columns.reference,
            kind: 'data',
            render: (template) => (
                <TableValue>
                    {template.customerReference ?? labels.not_available}
                </TableValue>
            ),
        },
        {
            key: 'state',
            label: labels.columns.state,
            kind: 'status',
            render: (template) => (
                <StatusBadge
                    status={template.state.toLowerCase() as Status}
                    label={labels.states[template.state]}
                />
            ),
        },
        {
            key: 'outcome',
            label: labels.columns.outcome,
            kind: 'status',
            render: (template) =>
                template.lastRunOutcome === null ? (
                    <SecondaryText>{labels.not_available}</SecondaryText>
                ) : (
                    <StatusBadge
                        status={
                            template.lastRunOutcome === 'SUCCEEDED'
                                ? 'completed'
                                : (template.lastRunOutcome.toLowerCase() as Status)
                        }
                        label={labels.outcomes[template.lastRunOutcome]}
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
            key: 'updated',
            label: labels.columns.next_run,
            kind: 'data',
            render: (template) => (
                <SecondaryText>
                    {template.nextRunAt === null
                        ? labels.not_available
                        : new Intl.DateTimeFormat(i18n.locale, {
                              dateStyle: 'medium',
                              timeStyle: 'short',
                          }).format(new Date(template.nextRunAt))}
                </SecondaryText>
            ),
        },
        {
            key: 'actions',
            label: labels.columns.actions,
            kind: 'actions',
            render: (template) => (
                <TemplateActions template={template} labels={props.labels} />
            ),
        },
    ];
    const filtered =
        props.filters.q !== '' ||
        props.filters.sort !== 'recent' ||
        props.filters.outcome !== 'all';
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
                />
            }
            footer={<Pagination page={props.page} labels={labels} />}
        />
    );
}

function TemplateActions({
    template,
    labels,
}: {
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
                    <Link href={template.editUrl}>
                        {labels.index.columns.open}
                    </Link>
                </Button>
                {template.lastInvoiceUrl && (
                    <Button asChild variant="secondary">
                        <Link href={template.lastInvoiceUrl}>
                            {labels.index.columns.open_invoice}
                        </Link>
                    </Button>
                )}
                {template.canDelete && (
                    <RecurringTemplateDeleteDialog
                        url={template.deleteUrl}
                        highRisk={template.deletion.highRisk}
                        guard={template.deletion.guard}
                        labels={labels.deletion}
                    />
                )}
            </Cluster>
        </div>
    );
}

function Pagination({
    page,
    labels,
}: {
    page: RecurringTemplateCursorPage;
    labels: RecurringTranslations['index'];
}) {
    return (
        <nav
            aria-label={`${labels.previous} / ${labels.next}`}
            className="flex justify-end gap-2"
        >
            <PageLink href={page.previousUrl} label={labels.previous} />
            <PageLink href={page.nextUrl} label={labels.next} />
        </nav>
    );
}

function PageLink({ href, label }: { href: string | null; label: string }) {
    return href ? (
        <Button asChild variant="secondary">
            <Link href={href} preserveScroll>
                {label}
            </Link>
        </Button>
    ) : (
        <Button disabled variant="secondary">
            {label}
        </Button>
    );
}
