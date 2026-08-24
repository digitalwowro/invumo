import { usePage } from '@inertiajs/react';
import { OperationalTable } from '@/components/app/operational-table';
import type { OperationalColumn } from '@/components/app/operational-table';
import {
    BodyStrong,
    SecondaryText,
    TableValue,
} from '@/components/app/typography';
import { StatusBadge } from '@/components/domain/status-badge';
import { Badge } from '@/components/ui/badge';
import { PlatformImpersonateButton } from '@/features/platform/components/platform-impersonate-button';
import {
    formatPlatformDate,
    planStatusPresentation,
    PlatformCursorControls,
    PlatformSearch,
    platformTableStateCopy,
} from '@/features/platform/components/platform-list-tools';
import { PlatformMutationDialog } from '@/features/platform/components/platform-mutation-dialog';
import type {
    PlatformCursorPage,
    PlatformTranslations,
    PlatformUserRow,
} from '@/types';

type UsersTableProps = {
    page: PlatformCursorPage<PlatformUserRow>;
    search: string;
    translations: PlatformTranslations;
};

export function PlatformUsersTable({
    page,
    search,
    translations,
}: UsersTableProps) {
    const { i18n, platformContext } = usePage().props;
    const copy = translations.users;
    const common = translations.common;
    const columns: OperationalColumn<PlatformUserRow>[] = [
        {
            key: 'user',
            label: copy.name,
            kind: 'identity',
            render: (user) => (
                <div className="space-y-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <BodyStrong>{user.name}</BodyStrong>
                        {user.isOperator && (
                            <Badge variant="muted">{copy.operator}</Badge>
                        )}
                    </div>
                    <SecondaryText>{user.email}</SecondaryText>
                </div>
            ),
        },
        {
            key: 'access',
            label: copy.access,
            kind: 'status',
            render: (user) => (
                <div className="space-y-2">
                    <StatusBadge
                        status={user.suspended ? 'paused' : 'active'}
                        label={
                            user.suspended ? common.suspended : common.active
                        }
                    />
                    <SecondaryText>
                        {user.verified ? common.verified : common.unverified}
                    </SecondaryText>
                </div>
            ),
        },
        {
            key: 'plan',
            label: copy.plan,
            kind: 'status',
            render: (user) =>
                user.planStatus ? (
                    <div className="space-y-2">
                        <BodyStrong>
                            {user.planName ?? common.not_available}
                        </BodyStrong>
                        <StatusBadge
                            status={planStatusPresentation(user.planStatus)}
                            label={translations.statuses[user.planStatus]}
                        />
                    </div>
                ) : (
                    <SecondaryText>{common.not_available}</SecondaryText>
                ),
        },
        {
            key: 'companies',
            label: copy.companies,
            kind: 'data',
            render: (user) => <TableValue>{user.companyCount}</TableValue>,
        },
        {
            key: 'last-login',
            label: copy.last_login,
            kind: 'data',
            render: (user) => (
                <TableValue>
                    {formatPlatformDate(
                        user.lastLoginAt,
                        i18n.locale,
                        common.never,
                    )}
                </TableValue>
            ),
        },
        {
            key: 'registered',
            label: copy.registered,
            kind: 'data',
            render: (user) => (
                <TableValue>
                    {formatPlatformDate(
                        user.createdAt,
                        i18n.locale,
                        common.not_available,
                    )}
                </TableValue>
            ),
        },
        {
            key: 'actions',
            label: common.actions,
            kind: 'actions',
            render: (user) => (
                <div className="flex items-center justify-end gap-2">
                    {platformContext?.abilities.impersonate_users &&
                        user.canImpersonate && (
                            <PlatformImpersonateButton
                                url={user.impersonateUrl}
                                targetName={user.name}
                                confirmationStatusUrl={
                                    platformContext.reauthentication.statusUrl
                                }
                                confirmationStoreUrl={
                                    platformContext.reauthentication.confirmUrl
                                }
                                translations={translations}
                            />
                        )}
                    {user.isOperator ? (
                        <SecondaryText>{copy.operator}</SecondaryText>
                    ) : (
                        <PlatformMutationDialog
                            method={user.suspended ? 'delete' : 'post'}
                            url={
                                user.suspended
                                    ? user.reactivateUrl
                                    : user.suspendUrl
                            }
                            triggerLabel={
                                user.suspended ? copy.reactivate : copy.suspend
                            }
                            title={
                                user.suspended
                                    ? copy.reactivate_title
                                    : copy.suspend_title
                            }
                            description={
                                user.suspended
                                    ? copy.reactivate_description
                                    : copy.suspend_description
                            }
                            confirmLabel={
                                user.suspended
                                    ? copy.reactivate_confirm
                                    : copy.suspend_confirm
                            }
                            translations={common}
                            destructive={!user.suspended}
                        />
                    )}
                </div>
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
            rowKey={(user) => user.id}
            state={state}
            stateCopy={platformTableStateCopy(common)}
            toolbar={
                <PlatformSearch
                    action={platformContext?.routes.users ?? ''}
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
