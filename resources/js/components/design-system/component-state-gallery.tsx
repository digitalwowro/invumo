import { Cluster, Stack } from '@/components/layout';
import { MoneyValue } from '@/components/money-value';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import type { Status } from '@/components/status-badge';
import { Surface } from '@/components/surface';
import { Button } from '@/components/ui/button';
import { Field, FieldDescription, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';

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

type GalleryLabels = {
    title: string;
    subtitle: string;
    field: string;
    fieldDescription: string;
    actions: Record<'primary' | 'secondary' | 'ghost' | 'destructive', string>;
    statuses: Record<Status, string>;
};

export function ComponentStateGallery({ labels }: { labels: GalleryLabels }) {
    return (
        <Stack gap="xl">
            <PageHeader title={labels.title} subtitle={labels.subtitle} />

            <Surface>
                <Cluster gap="sm">
                    <Button>{labels.actions.primary}</Button>
                    <Button variant="secondary">
                        {labels.actions.secondary}
                    </Button>
                    <Button variant="ghost">{labels.actions.ghost}</Button>
                    <Button variant="destructive">
                        {labels.actions.destructive}
                    </Button>
                </Cluster>
            </Surface>

            <Surface>
                <Field>
                    <FieldLabel htmlFor="gallery-field">
                        {labels.field}
                    </FieldLabel>
                    <Input id="gallery-field" />
                    <FieldDescription>
                        {labels.fieldDescription}
                    </FieldDescription>
                </Field>
            </Surface>

            <Surface>
                <Cluster gap="sm">
                    {statuses.map((status) => (
                        <StatusBadge
                            key={status}
                            status={status}
                            label={labels.statuses[status]}
                        />
                    ))}
                </Cluster>
            </Surface>

            <Surface>
                <Cluster gap="xl">
                    <MoneyValue value="€ 1.240,00" emphasis="strong" />
                    <MoneyValue value="€ 0,00" tone="positive" />
                    <MoneyValue value="€ 780,00" tone="danger" />
                </Cluster>
            </Surface>
        </Stack>
    );
}

export type { GalleryLabels };
