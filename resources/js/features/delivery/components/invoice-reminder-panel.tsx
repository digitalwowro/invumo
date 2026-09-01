import { ContentSection } from '@/components/app/content-section';
import { Cluster, Inline, Stack } from '@/components/app/layout';
import { SubtleMessage } from '@/components/app/subtle-message';
import { StatusBadge } from '@/components/domain/status-badge';
import { DocumentDeliveryRetryDialog } from '@/features/delivery/components/document-delivery-retry-dialog';
import { ReminderRuleForm } from '@/features/delivery/components/reminder-rule-form';
import type {
    InvoiceReminder,
    InvoiceReminderTranslations,
    ReminderRelation,
    ReminderStatus,
} from '@/types/reminder';
import type { Status } from '@/types/status';

type Props = {
    reminders: InvoiceReminder;
    editVersion: number;
    locale: string;
    timezone: string;
    closeLabel: string;
    labels: InvoiceReminderTranslations;
};

const statusPresentation: Record<ReminderStatus, Status> = {
    PENDING: 'pending',
    CLAIMED: 'claimed',
    SENT: 'sent',
    SKIPPED: 'skipped',
    SUPERSEDED: 'superseded',
    SUPPRESSED: 'suppressed',
    FAILED: 'failed',
};

export function InvoiceReminderPanel({
    reminders,
    editVersion,
    locale,
    timezone,
    closeLabel,
    labels,
}: Props) {
    const relationOptions = (
        ['BEFORE_DUE', 'AFTER_DUE'] as ReminderRelation[]
    ).map((value) => ({ value, label: labels.relations[value] }));
    const date = new Intl.DateTimeFormat(locale, {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: timezone,
    });

    return (
        <ContentSection title={labels.title} description={labels.description}>
            {reminders.saveUrl && (
                <div className="p-5 sm:p-6">
                    <ReminderRuleForm
                        rules={reminders.rules}
                        relationOptions={relationOptions}
                        maxRules={reminders.limits.rules}
                        maxDayOffset={reminders.limits.dayOffset}
                        saveUrl={reminders.saveUrl}
                        editVersion={editVersion}
                        allowRemoval
                        embedded
                        labels={labels}
                    />
                </div>
            )}
            <Stack
                as="section"
                gap="md"
                className="border-t border-divider p-5 sm:p-6"
            >
                <h3 className="font-semibold text-foreground">
                    {labels.history_title}
                </h3>
                {reminders.history.length === 0 ? (
                    <SubtleMessage>{labels.history_empty}</SubtleMessage>
                ) : (
                    <div className="divide-y divide-divider">
                        {reminders.history.map((item) => (
                            <Cluster
                                as="article"
                                key={item.id}
                                className="justify-between py-4 first:pt-0 last:pb-0"
                            >
                                <Stack className="min-w-0" gap="xs">
                                    <p className="font-medium text-foreground">
                                        {labels.relations[item.relation]} ·{' '}
                                        {item.dayOffset}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {labels.scheduled_for}{' '}
                                        {date.format(
                                            new Date(item.scheduledAt),
                                        )}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {labels.attempts}: {item.attemptCount}
                                    </p>
                                    {item.failure && (
                                        <p className="text-sm text-danger-text">
                                            {item.failure}
                                        </p>
                                    )}
                                </Stack>
                                <Inline gap="sm">
                                    <StatusBadge
                                        status={statusPresentation[item.status]}
                                        label={labels.statuses[item.status]}
                                    />
                                    {item.retryUrl && (
                                        <DocumentDeliveryRetryDialog
                                            url={item.retryUrl}
                                            labels={labels}
                                            closeLabel={closeLabel}
                                        />
                                    )}
                                </Inline>
                            </Cluster>
                        ))}
                    </div>
                )}
            </Stack>
        </ContentSection>
    );
}
