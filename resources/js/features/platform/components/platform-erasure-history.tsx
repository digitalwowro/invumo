import { OperationalTable } from '@/components/app/operational-table';
import type { OperationalColumn } from '@/components/app/operational-table';
import { SectionHeader } from '@/components/app/section-header';
import {
    BodyStrong,
    SecondaryText,
    TableValue,
} from '@/components/app/typography';
import { StatusBadge } from '@/components/domain/status-badge';
import {
    formatPlatformDate,
    PlatformCursorControls,
    platformTableStateCopy,
} from '@/features/platform/components/platform-list-tools';
import type {
    PlatformCursorPage,
    PlatformErasureRow,
    PlatformTranslations,
} from '@/types';

type Props = {
    page: PlatformCursorPage<PlatformErasureRow>;
    translations: PlatformTranslations;
    locale: string;
};

export function PlatformErasureHistory({ page, translations, locale }: Props) {
    const copy = translations.audit;
    const common = translations.common;
    const columns: OperationalColumn<PlatformErasureRow>[] = [
        {
            key: 'time',
            label: copy.time,
            kind: 'data',
            render: (event) => (
                <TableValue>
                    {formatPlatformDate(
                        event.occurredAt,
                        locale,
                        common.not_available,
                    )}
                </TableValue>
            ),
        },
        {
            key: 'action',
            label: copy.action,
            kind: 'text',
            render: (event) => (
                <div className="space-y-1">
                    <BodyStrong>{event.action.replaceAll('_', ' ')}</BodyStrong>
                    <SecondaryText>
                        {event.actorName ?? translations.overview.system_actor}
                    </SecondaryText>
                </div>
            ),
        },
        {
            key: 'subject',
            label: copy.erasure_subject,
            kind: 'data',
            render: (event) => (
                <div className="space-y-1">
                    <BodyStrong>{event.subjectType}</BodyStrong>
                    <SecondaryText>{event.subjectId}</SecondaryText>
                </div>
            ),
        },
        {
            key: 'cleanup',
            label: copy.erasure_cleanup,
            kind: 'status',
            render: (event) => {
                const status = cleanupStatus(event);

                return (
                    <div className="space-y-1">
                        <StatusBadge
                            status={status}
                            label={copy[`erasure_${status}`]}
                        />
                        {event.fileCount > 0 && (
                            <SecondaryText>
                                {copy.erasure_files
                                    .replace(
                                        ':pending',
                                        String(event.pendingFileCount),
                                    )
                                    .replace(':total', String(event.fileCount))}
                            </SecondaryText>
                        )}
                    </div>
                );
            },
        },
    ];

    return (
        <div className="space-y-4">
            <SectionHeader
                title={copy.erasure_title}
                description={copy.erasure_description}
            />
            <OperationalTable
                ariaLabel={copy.erasure_title}
                columns={columns}
                rows={page.items}
                rowKey={(event) => event.id}
                state={page.items.length > 0 ? 'ready' : 'empty'}
                stateCopy={platformTableStateCopy(common)}
                footer={
                    <PlatformCursorControls
                        previousUrl={page.previousUrl}
                        nextUrl={page.nextUrl}
                        previousLabel={common.previous}
                        nextLabel={common.next}
                    />
                }
            />
        </div>
    );
}

function cleanupStatus(
    event: PlatformErasureRow,
): 'completed' | 'failed' | 'pending' {
    if (event.failedFileCount > 0) {
        return 'failed';
    }

    return event.pendingFileCount > 0 ? 'pending' : 'completed';
}
