import { Link, usePage } from '@inertiajs/react';
import { FormSection } from '@/components/app/form-section';
import { Grid, Stack } from '@/components/app/layout';
import { Body, MetaLabel, SecondaryText } from '@/components/app/typography';
import { StatusBadge } from '@/components/domain/status-badge';
import { Button } from '@/components/ui/button';
import type { RecurringExecution } from '@/types/recurring';
import type { RecurringTranslations } from '@/types/recurring-translations';

type Props = {
    execution: RecurringExecution;
    labels: RecurringTranslations;
};

export function RecurringTemplateExecution({ execution, labels }: Props) {
    const { i18n } = usePage().props;
    const copy = labels.execution;
    const format = (value: string | null) =>
        value === null
            ? copy.not_run
            : new Intl.DateTimeFormat(i18n.locale, {
                  dateStyle: 'medium',
                  timeStyle: 'short',
              }).format(new Date(value));

    return (
        <FormSection
            title={copy.title}
            description={copy.description}
            actions={
                execution.lastInvoiceUrl && (
                    <Button asChild variant="secondary">
                        <Link href={execution.lastInvoiceUrl}>
                            {copy.open_invoice}
                        </Link>
                    </Button>
                )
            }
        >
            <Grid columns={4} gap="lg">
                <ExecutionValue
                    label={copy.successful_count}
                    value={String(execution.successfulOccurrenceCount)}
                />
                <Stack gap="xs">
                    <MetaLabel>{copy.last_outcome}</MetaLabel>
                    {execution.lastRunOutcome === null ? (
                        <SecondaryText>{copy.not_run}</SecondaryText>
                    ) : (
                        <StatusBadge
                            status={
                                execution.lastRunOutcome === 'SUCCEEDED'
                                    ? 'completed'
                                    : (execution.lastRunOutcome.toLowerCase() as
                                          'failed' | 'skipped')
                            }
                            label={
                                labels.index.outcomes[execution.lastRunOutcome]
                            }
                        />
                    )}
                </Stack>
                <ExecutionValue
                    label={copy.last_started}
                    value={format(execution.lastRunStartedAt)}
                />
                <ExecutionValue
                    label={copy.last_completed}
                    value={format(execution.lastRunCompletedAt)}
                />
            </Grid>
            {execution.lastFailure && (
                <Stack gap="xs">
                    <MetaLabel>{copy.last_failure}</MetaLabel>
                    <Body>{execution.lastFailure}</Body>
                </Stack>
            )}
        </FormSection>
    );
}

function ExecutionValue({ label, value }: { label: string; value: string }) {
    return (
        <Stack gap="xs">
            <MetaLabel>{label}</MetaLabel>
            <Body>{value}</Body>
        </Stack>
    );
}
