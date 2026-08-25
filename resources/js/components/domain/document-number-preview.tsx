import { Stack } from '@/components/app/layout';
import { Body, MetaLabel, MetricValue } from '@/components/app/typography';

type Props = {
    label: string;
    value: string | null;
    unavailableMessage: string;
};

export function DocumentNumberPreview({
    label,
    value,
    unavailableMessage,
}: Props) {
    return (
        <Stack
            gap="sm"
            className="rounded-md border border-border bg-surface-inset p-4"
        >
            <MetaLabel>{label}</MetaLabel>
            <div aria-live="polite" className="min-w-0 break-all">
                {value === null ? (
                    <Body>{unavailableMessage}</Body>
                ) : (
                    <MetricValue>{value}</MetricValue>
                )}
            </div>
        </Stack>
    );
}
