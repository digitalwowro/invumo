import { ActionLink } from '@/components/app/action-link';
import { Inline } from '@/components/app/layout';
import { OperationalTable } from '@/components/app/operational-table';
import type {
    OperationalColumn,
    OperationalTableStateCopy,
} from '@/components/app/operational-table';
import { StatusBadge } from '@/components/domain/status-badge';
import { DocumentDeliveryRetryDialog } from '@/features/delivery/components/document-delivery-retry-dialog';
import type {
    CompanyReminderFailure,
    CompanyReminderTranslations,
} from '@/types/reminder';

type Props = {
    failures: CompanyReminderFailure[];
    locale: string;
    timezone: string;
    closeLabel: string;
    labels: CompanyReminderTranslations;
};

export function CompanyReminderFailures({
    failures,
    locale,
    timezone,
    closeLabel,
    labels,
}: Props) {
    const date = (value: string) =>
        new Intl.DateTimeFormat(locale, {
            dateStyle: 'medium',
            timeStyle: 'short',
            timeZone: timezone,
        }).format(new Date(value));
    const columns: OperationalColumn<CompanyReminderFailure>[] = [
        {
            key: 'invoice',
            label: labels.failure_columns.invoice,
            kind: 'identity',
            render: (failure) => failure.invoiceNumber,
        },
        {
            key: 'scheduled',
            label: labels.failure_columns.scheduled,
            kind: 'data',
            render: (failure) => date(failure.scheduledAt),
        },
        {
            key: 'reason',
            label: labels.failure_columns.reason,
            render: (failure) => failure.failure,
        },
        {
            key: 'attempts',
            label: labels.failure_columns.attempts,
            kind: 'data',
            render: (failure) => failure.attemptCount,
        },
        {
            key: 'status',
            label: labels.failure_columns.status,
            kind: 'status',
            render: () => (
                <StatusBadge status="failed" label={labels.failure_status} />
            ),
        },
        {
            key: 'actions',
            label: labels.failure_columns.actions,
            kind: 'actions',
            render: (failure) => (
                <Inline>
                    <ActionLink href={failure.invoiceUrl} variant="secondary">
                        {labels.open_invoice}
                    </ActionLink>
                    <DocumentDeliveryRetryDialog
                        url={failure.retryUrl}
                        labels={labels}
                        closeLabel={closeLabel}
                    />
                </Inline>
            ),
        },
    ];
    const stateCopy: OperationalTableStateCopy = {
        loading: labels.failures_title,
        emptyTitle: labels.failures_empty_title,
        emptyDescription: labels.failures_empty_description,
        noResultsTitle: labels.failures_empty_title,
        noResultsDescription: labels.failures_empty_description,
        errorTitle: labels.failures_title,
        errorDescription: labels.failures_empty_description,
    };

    return (
        <OperationalTable
            ariaLabel={labels.failures_title}
            columns={columns}
            rows={failures}
            rowKey={(failure) => failure.id}
            state={failures.length === 0 ? 'empty' : 'ready'}
            stateCopy={stateCopy}
        />
    );
}
