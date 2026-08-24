import { usePage } from '@inertiajs/react';
import { OperationalTable } from '@/components/app/operational-table';
import type { OperationalColumn } from '@/components/app/operational-table';
import {
    BodyStrong,
    SecondaryText,
    TableValue,
} from '@/components/app/typography';
import { StatusBadge } from '@/components/domain/status-badge';
import {
    planStatusPresentation,
    PlatformCursorControls,
    PlatformSearch,
    platformTableStateCopy,
} from '@/features/platform/components/platform-list-tools';
import { PlatformMutationDialog } from '@/features/platform/components/platform-mutation-dialog';
import type {
    PlatformAccountRow,
    PlatformCursorPage,
    PlatformTranslations,
} from '@/types';

type AccountsTableProps = {
    page: PlatformCursorPage<PlatformAccountRow>;
    search: string;
    translations: PlatformTranslations;
};

export function PlatformAccountsTable({
    page,
    search,
    translations,
}: AccountsTableProps) {
    const { platformContext } = usePage().props;
    const copy = translations.accounts;
    const common = translations.common;
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
            kind: 'text',
            render: (account) => <BodyStrong>{account.planName}</BodyStrong>,
        },
        {
            key: 'lifecycle',
            label: copy.lifecycle,
            kind: 'status',
            render: (account) => (
                <StatusBadge
                    status={planStatusPresentation(account.planStatus)}
                    label={translations.statuses[account.planStatus]}
                />
            ),
        },
        {
            key: 'companies',
            label: copy.companies,
            kind: 'data',
            render: (account) => (
                <TableValue>{account.companyCount}</TableValue>
            ),
        },
        {
            key: 'access',
            label: copy.access,
            kind: 'status',
            render: (account) => (
                <StatusBadge
                    status={account.suspended ? 'paused' : 'active'}
                    label={account.suspended ? common.suspended : common.active}
                />
            ),
        },
        {
            key: 'actions',
            label: common.actions,
            kind: 'actions',
            render: (account) => (
                <PlatformMutationDialog
                    method={account.suspended ? 'delete' : 'post'}
                    url={
                        account.suspended
                            ? account.reactivateUrl
                            : account.suspendUrl
                    }
                    triggerLabel={
                        account.suspended ? copy.reactivate : copy.suspend
                    }
                    title={
                        account.suspended
                            ? copy.reactivate_title
                            : copy.suspend_title
                    }
                    description={
                        account.suspended
                            ? copy.reactivate_description
                            : copy.suspend_description
                    }
                    confirmLabel={
                        account.suspended
                            ? copy.reactivate_confirm
                            : copy.suspend_confirm
                    }
                    translations={common}
                    destructive={!account.suspended}
                />
            ),
        },
    ];
    const state =
        page.items.length > 0 ? 'ready' : search ? 'no-results' : 'empty';

    return (
        <OperationalTable
            ariaLabel={copy.title}
            columns={columns}
            rows={page.items}
            rowKey={(account) => account.id}
            state={state}
            stateCopy={platformTableStateCopy(common)}
            toolbar={
                <PlatformSearch
                    action={platformContext?.routes.accounts ?? ''}
                    initialValue={search}
                    label={common.search}
                    placeholder={copy.search_placeholder}
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
