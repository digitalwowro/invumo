import { router } from '@inertiajs/react';
import { Cluster } from '@/components/app/layout';
import { OperationalTable } from '@/components/app/operational-table';
import type { OperationalTableStateCopy } from '@/components/app/operational-table';
import { ConfirmationDialog } from '@/components/app/responsive-dialog';
import { BodyStrong, SecondaryText } from '@/components/app/typography';
import { Badge } from '@/components/ui/badge';
import { interpolate } from '@/lib/translations';
import type { CompanyMember, CompanyMembersTranslations } from '@/types';

type Props = {
    members: CompanyMember[];
    translations: CompanyMembersTranslations;
    cancelLabel: string;
    closeLabel: string;
};

const stateCopy = (emptyDescription: string): OperationalTableStateCopy => ({
    loading: '',
    emptyTitle: emptyDescription,
    emptyDescription,
    noResultsTitle: '',
    noResultsDescription: '',
    errorTitle: '',
    errorDescription: '',
});

export function CompanyMemberDirectory({
    members,
    translations,
    cancelLabel,
    closeLabel,
}: Props) {
    return (
        <OperationalTable
            ariaLabel={translations.directory_title}
            rows={members}
            rowKey={(member) => member.id}
            state={members.length === 0 ? 'empty' : 'ready'}
            stateCopy={stateCopy(translations.directory_description)}
            columns={[
                {
                    key: 'name',
                    label: translations.name_column,
                    kind: 'identity',
                    render: (member) => (
                        <div>
                            <BodyStrong>{member.name}</BodyStrong>
                            {member.isCurrentUser && (
                                <SecondaryText>
                                    {translations.current_user}
                                </SecondaryText>
                            )}
                        </div>
                    ),
                },
                {
                    key: 'email',
                    label: translations.email_column,
                    render: (member) => member.email,
                },
                {
                    key: 'role',
                    label: translations.role_column,
                    kind: 'status',
                    render: (member) => (
                        <Badge variant="muted">
                            {translations.roles[member.role]}
                        </Badge>
                    ),
                },
                {
                    key: 'actions',
                    label: translations.actions_column,
                    kind: 'actions',
                    render: (member) => (
                        <Cluster gap="sm">
                            {member.updateUrl && member.nextRole && (
                                <ConfirmationDialog
                                    triggerLabel={translations.change_role}
                                    title={translations.change_role_title}
                                    description={interpolate(
                                        translations.change_role_description,
                                        {
                                            name: member.name,
                                            role: translations.roles[
                                                member.nextRole
                                            ],
                                        },
                                    )}
                                    confirmLabel={
                                        translations.confirm_role_change
                                    }
                                    cancelLabel={cancelLabel}
                                    closeLabel={closeLabel}
                                    tone="default"
                                    onConfirm={() =>
                                        router.patch(
                                            member.updateUrl as string,
                                            { role: member.nextRole },
                                            { preserveScroll: true },
                                        )
                                    }
                                />
                            )}
                            {member.removeUrl && (
                                <ConfirmationDialog
                                    triggerLabel={translations.remove_member}
                                    title={translations.remove_member_title}
                                    description={interpolate(
                                        translations.remove_member_description,
                                        { name: member.name },
                                    )}
                                    confirmLabel={
                                        translations.confirm_remove_member
                                    }
                                    cancelLabel={cancelLabel}
                                    closeLabel={closeLabel}
                                    onConfirm={() =>
                                        router.delete(
                                            member.removeUrl as string,
                                            { preserveScroll: true },
                                        )
                                    }
                                />
                            )}
                        </Cluster>
                    ),
                },
            ]}
        />
    );
}
