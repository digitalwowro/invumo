import { Head, usePage } from '@inertiajs/react';
import { Stack } from '@/components/app/layout';
import { PageFrame } from '@/components/app/page-frame';
import { PageHeader } from '@/components/app/page-header';
import { SectionHeader } from '@/components/app/section-header';
import { SystemMessage } from '@/components/app/system-message';
import { CompanyInvitationForm } from '@/features/companies/components/company-invitation-form';
import { CompanyMemberDirectory } from '@/features/companies/components/company-member-directory';
import { CompanyPendingInvitations } from '@/features/companies/components/company-pending-invitations';
import { interpolate } from '@/lib/translations';
import type {
    CompaniesUiTranslations,
    CompanyMember,
    CompanyPendingInvitation,
} from '@/types';

type Props = {
    company: { id: string; name: string };
    members: CompanyMember[];
    invitations: CompanyPendingInvitation[];
    canManageMembers: boolean;
    storeUrl: string;
    status?: string;
    translations: CompaniesUiTranslations;
};

export default function CompanyMembers({
    company,
    members,
    invitations,
    canManageMembers,
    storeUrl,
    status,
    translations,
}: Props) {
    const { i18n, errors } = usePage().props;
    const labels = translations.members;

    return (
        <>
            <Head title={labels.head_title} />
            <PageFrame>
                <Stack gap="2xl">
                    <PageHeader
                        title={labels.title}
                        subtitle={interpolate(labels.description, {
                            company: company.name,
                        })}
                    />
                    {status && <SystemMessage title={status} tone="money" />}
                    {errors.invitation && (
                        <SystemMessage title={errors.invitation} tone="error" />
                    )}
                    {canManageMembers && (
                        <CompanyInvitationForm
                            storeUrl={storeUrl}
                            translations={labels}
                        />
                    )}
                    <Stack gap="lg">
                        <SectionHeader
                            title={labels.directory_title}
                            description={labels.directory_description}
                        />
                        <CompanyMemberDirectory
                            members={members}
                            translations={labels}
                        />
                    </Stack>
                    {canManageMembers && (
                        <Stack gap="lg">
                            <SectionHeader
                                title={labels.pending_title}
                                description={labels.pending_description}
                            />
                            <CompanyPendingInvitations
                                invitations={invitations}
                                locale={i18n.locale}
                                translations={labels}
                                cancelLabel={i18n.common.actions.cancel}
                                closeLabel={
                                    i18n.common.accessibility.close_navigation
                                }
                            />
                        </Stack>
                    )}
                </Stack>
            </PageFrame>
        </>
    );
}
