import type { ReactNode } from 'react';
import { Stack } from '@/components/app/layout';
import {
    Body,
    BodyStrong,
    SecondaryText,
    TableValue,
} from '@/components/app/typography';

type ActivityTimelineItem = {
    id: string;
    action: string;
    actor: string;
    timestamp: string;
    context?: string;
    description?: string;
    detail?: string;
    control?: ReactNode;
};

type ActivityTimelineProps = {
    ariaLabel: string;
    items: ActivityTimelineItem[];
    emptyTitle: string;
    emptyDescription: string;
    toolbar?: ReactNode;
    footer?: ReactNode;
};

export function ActivityTimeline({
    ariaLabel,
    items,
    emptyTitle,
    emptyDescription,
    toolbar,
    footer,
}: ActivityTimelineProps) {
    return (
        <section
            data-slot="activity-timeline"
            aria-label={ariaLabel}
            className="w-full max-w-full min-w-0 overflow-hidden rounded-lg border border-border bg-background"
        >
            {toolbar && (
                <div className="border-b border-divider p-4">{toolbar}</div>
            )}
            {items.length ? (
                <ol className="divide-y divide-divider">
                    {items.map((item) => (
                        <li
                            key={item.id}
                            className="grid min-w-0 grid-cols-[auto_minmax(0,1fr)] gap-3 p-4 sm:grid-cols-[auto_minmax(0,1fr)_auto]"
                        >
                            <span
                                aria-hidden="true"
                                className="mt-1.5 size-2 rounded-full bg-foreground-mid"
                            />
                            <Stack gap="xs" className="min-w-0">
                                <BodyStrong>{item.action}</BodyStrong>
                                <SecondaryText>
                                    {item.actor} · {item.timestamp}
                                </SecondaryText>
                                {item.context && (
                                    <div className="break-all">
                                        <TableValue>{item.context}</TableValue>
                                    </div>
                                )}
                                {item.description && (
                                    <Body>{item.description}</Body>
                                )}
                                {item.detail && (
                                    <SecondaryText>{item.detail}</SecondaryText>
                                )}
                            </Stack>
                            {item.control && (
                                <div className="col-start-2 row-start-2 sm:col-start-3 sm:row-start-1 sm:row-end-2">
                                    {item.control}
                                </div>
                            )}
                        </li>
                    ))}
                </ol>
            ) : (
                <div className="p-10 text-center">
                    <BodyStrong>{emptyTitle}</BodyStrong>
                    <SecondaryText>{emptyDescription}</SecondaryText>
                </div>
            )}
            {footer && (
                <div className="border-t border-divider p-4">{footer}</div>
            )}
        </section>
    );
}

export type { ActivityTimelineItem };
