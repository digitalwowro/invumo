import { usePage } from '@inertiajs/react';
import { OperationalTable } from '@/components/app/operational-table';
import type { OperationalColumn } from '@/components/app/operational-table';
import {
    BodyStrong,
    SecondaryText,
    TableValue,
} from '@/components/app/typography';
import { StatusBadge } from '@/components/domain/status-badge';
import { PlatformLifecycleFilters } from '@/features/platform/components/platform-lifecycle-filters';
import {
    formatPlatformDate,
    planStatusPresentation,
    PlatformCursorControls,
    platformTableStateCopy,
} from '@/features/platform/components/platform-list-tools';
import { PlatformPlanDialog } from '@/features/platform/components/platform-plan-dialog';
import type {
    PlanStatus,
    PlatformAccountRow,
    PlatformCursorPage,
    PlatformPlan,
    PlatformTranslations,
} from '@/types';

type LifecycleTableProps = {
    page: PlatformCursorPage<PlatformAccountRow>;
    plans: PlatformPlan[];
    search: string;
    selectedStatus: PlanStatus | null;
    selectedExpiryDays: number | null;
    cancelAtPeriodEndOnly: boolean;
    translations: PlatformTranslations;
};

export function PlatformLifecycleTable({
    page,
    plans,
    search,
    selectedStatus,
    selectedExpiryDays,
    cancelAtPeriodEndOnly,
    translations,
}: LifecycleTableProps) {
    const { i18n, platformContext } = usePage().props;
    const copy = translations.plan_lifecycle;
    const common = translations.common;
    const date = (value: string | null) =>
        formatPlatformDate(value, i18n.locale, common.not_available);
    const columns: OperationalColumn<PlatformAccountRow>[] = [
        {
            key: 'owner',
            label: copy.owner,
            kind: 'identity',
            render: (account) => (
                <div className="space-y-1">
                    <BodyStrong>{account.ownerName}</BodyStrong>
                    <SecondaryText>{account.ownerEmail}</SecondaryText>
                </div>
            ),
        },
        {
            key: 'plan',
            label: copy.plan,
            kind: 'status',
            render: (account) => (
                <div className="space-y-2">
                    <BodyStrong>{account.planName}</BodyStrong>
                    <StatusBadge
                        status={planStatusPresentation(account.planStatus)}
                        label={translations.statuses[account.planStatus]}
                    />
                </div>
            ),
        },
        {
            key: 'started',
            label: copy.started,
            kind: 'data',
            render: (account) => (
                <TableValue>{date(account.planStartedAt)}</TableValue>
            ),
        },
        {
            key: 'trial-end',
            label: copy.trial_ends,
            kind: 'data',
            render: (account) => (
                <TableValue>{date(account.trialEndsAt)}</TableValue>
            ),
        },
        {
            key: 'access-end',
            label: copy.access_ends,
            kind: 'data',
            render: (account) => (
                <div className="space-y-1">
                    <TableValue>{date(account.accessEndsAt)}</TableValue>
                    {account.cancelAtPeriodEnd && (
                        <SecondaryText>
                            {copy.cancel_at_period_end}
                        </SecondaryText>
                    )}
                </div>
            ),
        },
        {
            key: 'ended',
            label: copy.ended,
            kind: 'data',
            render: (account) => (
                <TableValue>{date(account.endedAt)}</TableValue>
            ),
        },
        {
            key: 'actions',
            label: common.actions,
            kind: 'actions',
            render: (account) => (
                <PlatformPlanDialog
                    account={account}
                    plans={plans}
                    translations={translations}
                />
            ),
        },
    ];
    const filtered = Boolean(
        search || selectedStatus || selectedExpiryDays || cancelAtPeriodEndOnly,
    );
    const state =
        page.items.length > 0 ? 'ready' : filtered ? 'no-results' : 'empty';

    return (
        <OperationalTable
            ariaLabel={copy.title}
            columns={columns}
            rows={page.items}
            rowKey={(account) => account.id}
            state={state}
            stateCopy={platformTableStateCopy(common)}
            toolbar={
                <PlatformLifecycleFilters
                    action={platformContext?.routes.planLifecycle ?? ''}
                    search={search}
                    selectedStatus={selectedStatus}
                    selectedExpiryDays={selectedExpiryDays}
                    cancelAtPeriodEndOnly={cancelAtPeriodEndOnly}
                    translations={translations}
                />
            }
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
