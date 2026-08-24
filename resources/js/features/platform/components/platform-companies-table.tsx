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
    formatPlatformDate,
    PlatformCursorControls,
    PlatformSearch,
    platformTableStateCopy,
} from '@/features/platform/components/platform-list-tools';
import type {
    PlatformCompanyRow,
    PlatformCursorPage,
    PlatformTranslations,
} from '@/types';

type CompaniesTableProps = {
    page: PlatformCursorPage<PlatformCompanyRow>;
    search: string;
    translations: PlatformTranslations;
};

export function PlatformCompaniesTable({
    page,
    search,
    translations,
}: CompaniesTableProps) {
    const { i18n, platformContext } = usePage().props;
    const copy = translations.companies;
    const common = translations.common;
    const columns: OperationalColumn<PlatformCompanyRow>[] = [
        {
            key: 'company',
            label: copy.company,
            kind: 'identity',
            render: (company) => <BodyStrong>{company.name}</BodyStrong>,
        },
        {
            key: 'owner',
            label: copy.owner,
            kind: 'text',
            render: (company) => (
                <div className="space-y-1">
                    <BodyStrong>{company.ownerName}</BodyStrong>
                    <SecondaryText>{company.ownerEmail}</SecondaryText>
                </div>
            ),
        },
        {
            key: 'members',
            label: copy.members,
            kind: 'data',
            render: (company) => <TableValue>{company.memberCount}</TableValue>,
        },
        {
            key: 'state',
            label: copy.state,
            kind: 'status',
            render: (company) => (
                <StatusBadge
                    status={company.archived ? 'archived' : 'active'}
                    label={company.archived ? copy.archived : common.active}
                />
            ),
        },
        {
            key: 'created',
            label: copy.created,
            kind: 'data',
            render: (company) => (
                <TableValue>
                    {formatPlatformDate(
                        company.createdAt,
                        i18n.locale,
                        common.not_available,
                    )}
                </TableValue>
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
            rowKey={(company) => company.id}
            state={state}
            stateCopy={platformTableStateCopy(common)}
            toolbar={
                <PlatformSearch
                    action={platformContext?.routes.companies ?? ''}
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
