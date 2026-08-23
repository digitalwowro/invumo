import {
    EmptyState,
    ErrorState,
    LoadingState,
} from '@/components/app/async-state';
import { Cluster, Grid, Stack } from '@/components/app/layout';
import { ConfirmationDialog } from '@/components/app/responsive-dialog';
import { SectionHeader } from '@/components/app/section-header';
import { Surface } from '@/components/app/surface';
import { SystemMessage } from '@/components/app/system-message';
import type { SystemMessageTone } from '@/components/app/system-message';
import { StatusBadge } from '@/components/domain/status-badge';
import type { Status } from '@/components/domain/status-badge';
import { Button } from '@/components/ui/button';
import type {
    DesignSystemStatusLabels,
    DesignSystemTranslations,
} from '@/types';

const statuses: Status[] = [
    'paid',
    'accepted',
    'completed',
    'overdue',
    'rejected',
    'failed',
    'partial',
    'expired',
    'paused',
    'issued',
    'sent',
    'active',
    'unpaid',
    'draft',
    'cancelled',
    'archived',
];

const feedbackTones: SystemMessageTone[] = [
    'neutral',
    'money',
    'warning',
    'error',
    'info',
];

type GalleryStatesProps = {
    labels: DesignSystemTranslations;
    statusLabels: DesignSystemStatusLabels;
};

export function GalleryStates({ labels, statusLabels }: GalleryStatesProps) {
    return (
        <Stack gap="2xl">
            <Stack gap="lg">
                <SectionHeader title={labels.sections.statuses} />
                <Surface>
                    <Cluster gap="sm">
                        {statuses.map((status) => (
                            <StatusBadge
                                key={status}
                                status={status}
                                label={statusLabels[status]}
                            />
                        ))}
                    </Cluster>
                </Surface>
            </Stack>

            <Stack gap="lg">
                <SectionHeader title={labels.sections.feedback} />
                <Stack gap="md">
                    {feedbackTones.map((tone) => (
                        <SystemMessage
                            key={tone}
                            tone={tone}
                            title={labels.feedback[tone].title}
                            description={labels.feedback[tone].description}
                        />
                    ))}
                </Stack>
            </Stack>

            <Stack gap="lg">
                <SectionHeader title={labels.sections.asyncStates} />
                <Grid columns={3} gap="lg">
                    <LoadingState label={labels.asyncStates.loading} />
                    <EmptyState
                        title={labels.asyncStates.emptyTitle}
                        description={labels.asyncStates.emptyDescription}
                    />
                    <ErrorState
                        title={labels.asyncStates.errorTitle}
                        description={labels.asyncStates.errorDescription}
                        retry={
                            <Button variant="secondary">
                                {labels.actions.retry}
                            </Button>
                        }
                    />
                </Grid>
            </Stack>

            <Stack gap="lg">
                <SectionHeader title={labels.sections.dialog} />
                <Surface>
                    <ConfirmationDialog
                        triggerLabel={labels.dialog.trigger}
                        title={labels.dialog.title}
                        description={labels.dialog.description}
                        confirmLabel={labels.dialog.confirm}
                        cancelLabel={labels.dialog.cancel}
                        closeLabel={labels.dialog.close}
                    />
                </Surface>
            </Stack>
        </Stack>
    );
}
