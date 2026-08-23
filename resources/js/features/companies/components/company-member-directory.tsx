import { OperationalTable } from '@/components/app/operational-table';
import type { OperationalTableStateCopy } from '@/components/app/operational-table';
import { BodyStrong, SecondaryText } from '@/components/app/typography';
import { Badge } from '@/components/ui/badge';
import type { CompanyMember, CompanyMembersTranslations } from '@/types';

type Props = {
    members: CompanyMember[];
    translations: CompanyMembersTranslations;
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

export function CompanyMemberDirectory({ members, translations }: Props) {
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
            ]}
        />
    );
}
