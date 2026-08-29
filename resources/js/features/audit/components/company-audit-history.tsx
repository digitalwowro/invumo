import { Link } from '@inertiajs/react';
import { ActivityTimeline } from '@/components/app/activity-timeline';
import type { ActivityTimelineItem } from '@/components/app/activity-timeline';
import { AuditChangesDialog } from '@/components/domain/audit-changes-dialog';
import { Button } from '@/components/ui/button';
import { CompanyAuditListTools } from '@/features/audit/components/company-audit-list-tools';
import type {
    CompanyAuditCursorPage,
    CompanyAuditFilters,
    CompanyAuditRow,
    CompanyAuditTranslations,
} from '@/types/company-audit';

type Props = {
    page: CompanyAuditCursorPage;
    filters: CompanyAuditFilters;
    targetOptions: string[];
    indexUrl: string;
    timezone: string;
    locale: string;
    closeLabel: string;
    labels: CompanyAuditTranslations;
};

export function CompanyAuditHistory(props: Props) {
    const filtered = Object.entries(props.filters).some(([key, value]) =>
        key === 'sort'
            ? value !== 'newest'
            : key === 'perPage'
              ? value !== 25
              : key === 'actorType' || key === 'targetType'
                ? value !== 'all'
                : value !== '',
    );
    const items = props.page.items.map((event) => timelineItem(event, props));

    return (
        <ActivityTimeline
            ariaLabel={props.labels.title}
            items={items}
            emptyTitle={
                filtered
                    ? props.labels.no_results_title
                    : props.labels.empty_title
            }
            emptyDescription={
                filtered
                    ? props.labels.no_results_description
                    : props.labels.empty_description
            }
            toolbar={
                <CompanyAuditListTools
                    action={props.indexUrl}
                    filters={props.filters}
                    targetOptions={props.targetOptions}
                    labels={props.labels}
                />
            }
            footer={<Pagination page={props.page} labels={props.labels} />}
        />
    );
}

function timelineItem(
    event: CompanyAuditRow,
    props: Props,
): ActivityTimelineItem {
    const target =
        props.labels.target_types[event.targetType] ?? event.targetType;
    const reason = event.reason
        ? `${props.labels.reason}: ${event.reason}`
        : undefined;
    const impersonator = event.impersonatorName
        ? props.labels.original_operator.replace(
              ':name',
              event.impersonatorName,
          )
        : undefined;

    return {
        id: event.id,
        action: props.labels.actions[event.action] ?? event.action,
        actor:
            event.actorName ??
            event.actorReference ??
            props.labels.actor_types[event.actorType],
        timestamp: formatDate(event.occurredAt, props.locale, props.timezone),
        context: props.labels.target_context
            .replace(':type', target)
            .replace(':id', event.targetId),
        description: reason,
        detail: impersonator,
        control:
            event.before || event.after ? (
                <AuditChangesDialog
                    before={event.before}
                    after={event.after}
                    triggerLabel={props.labels.changes}
                    title={props.labels.changes_title}
                    description={props.labels.changes_description}
                    beforeLabel={props.labels.before}
                    afterLabel={props.labels.after}
                    notAvailable={props.labels.not_available}
                    closeLabel={props.closeLabel}
                />
            ) : undefined,
    };
}

function formatDate(value: string, locale: string, timezone: string): string {
    return new Intl.DateTimeFormat(locale, {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: timezone,
    }).format(new Date(value));
}

function Pagination(props: {
    page: CompanyAuditCursorPage;
    labels: CompanyAuditTranslations;
}) {
    return (
        <nav
            aria-label={`${props.labels.previous} / ${props.labels.next}`}
            className="flex justify-end gap-2"
        >
            <PageLink
                href={props.page.previousUrl}
                label={props.labels.previous}
            />
            <PageLink href={props.page.nextUrl} label={props.labels.next} />
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
