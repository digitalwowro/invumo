import { usePage } from '@inertiajs/react';
import { OperationalTable } from '@/components/app/operational-table';
import type { OperationalColumn } from '@/components/app/operational-table';
import { ResponsiveDialog } from '@/components/app/responsive-dialog';
import {
    BodyStrong,
    SecondaryText,
    TableValue,
} from '@/components/app/typography';
import { Button } from '@/components/ui/button';
import {
    formatPlatformDate,
    PlatformCursorControls,
    platformTableStateCopy,
} from '@/features/platform/components/platform-list-tools';
import type {
    PlatformAuditRow,
    PlatformCursorPage,
    PlatformTranslations,
} from '@/types';

type AuditTableProps = {
    page: PlatformCursorPage<PlatformAuditRow>;
    translations: PlatformTranslations;
};

export function PlatformAuditTable({ page, translations }: AuditTableProps) {
    const { i18n } = usePage().props;
    const copy = translations.audit;
    const common = translations.common;
    const columns: OperationalColumn<PlatformAuditRow>[] = [
        {
            key: 'time',
            label: copy.time,
            kind: 'data',
            render: (event) => (
                <TableValue>
                    {formatPlatformDate(
                        event.occurredAt,
                        i18n.locale,
                        common.not_available,
                    )}
                </TableValue>
            ),
        },
        {
            key: 'actor',
            label: copy.actor,
            kind: 'text',
            render: (event) => (
                <div className="space-y-1">
                    <BodyStrong>
                        {event.actorName ?? translations.overview.system_actor}
                    </BodyStrong>
                    {event.impersonatorName && (
                        <SecondaryText>
                            {copy.impersonator}: {event.impersonatorName}
                        </SecondaryText>
                    )}
                </div>
            ),
        },
        {
            key: 'action',
            label: copy.action,
            kind: 'text',
            render: (event) => (
                <BodyStrong>{event.action.replaceAll('.', ' ')}</BodyStrong>
            ),
        },
        {
            key: 'target',
            label: copy.target,
            kind: 'data',
            render: (event) => (
                <div className="space-y-1">
                    <BodyStrong>{event.targetType}</BodyStrong>
                    <SecondaryText>{event.targetId}</SecondaryText>
                </div>
            ),
        },
        {
            key: 'reason',
            label: copy.reason,
            kind: 'text',
            render: (event) => (
                <SecondaryText>
                    {event.reason ?? common.not_available}
                </SecondaryText>
            ),
        },
        {
            key: 'changes',
            label: copy.changes,
            kind: 'actions',
            render: (event) =>
                event.before || event.after ? (
                    <ResponsiveDialog
                        trigger={
                            <Button type="button" variant="secondary">
                                {copy.view_changes}
                            </Button>
                        }
                        title={copy.changes_title}
                        closeLabel={common.close}
                        size="wide"
                    >
                        <div className="grid gap-4 sm:grid-cols-2">
                            <AuditValue
                                label={copy.before}
                                value={event.before}
                                fallback={common.not_available}
                            />
                            <AuditValue
                                label={copy.after}
                                value={event.after}
                                fallback={common.not_available}
                            />
                        </div>
                    </ResponsiveDialog>
                ) : (
                    <SecondaryText>{common.not_available}</SecondaryText>
                ),
        },
    ];

    return (
        <OperationalTable
            ariaLabel={copy.title}
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
    );
}

function AuditValue({
    label,
    value,
    fallback,
}: {
    label: string;
    value: Record<string, unknown> | null;
    fallback: string;
}) {
    return (
        <div className="space-y-2">
            <BodyStrong>{label}</BodyStrong>
            <pre className="font-data overflow-x-auto rounded-md border border-border bg-surface-inset p-3 text-xs text-foreground">
                {value ? JSON.stringify(value, null, 2) : fallback}
            </pre>
        </div>
    );
}
