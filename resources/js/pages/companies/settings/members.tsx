import { Head, usePage } from '@inertiajs/react';
import { Stack } from '@/components/app/layout';
import { SectionHeader } from '@/components/app/section-header';
import { SystemMessage } from '@/components/app/system-message';
import { CompanyInvitationForm } from '@/features/companies/components/company-invitation-form';
import { CompanyMemberDirectory } from '@/features/companies/components/company-member-directory';
import { CompanyMembershipExit } from '@/features/companies/components/company-membership-exit';
import { CompanyOwnershipTransfer } from '@/features/companies/components/company-ownership-transfer';
import { CompanyPendingInvitations } from '@/features/companies/components/company-pending-invitations';
import { interpolate } from '@/lib/translations';
import type {
    CompaniesUiTranslations,
    CompanyMember,
    CompanyOwnershipCandidate,
    CompanyPendingInvitation,
} from '@/types';

type Props = {
    company: { id: string; name: string };
    members: CompanyMember[];
    invitations: CompanyPendingInvitation[];
    canManageMembers: boolean;
    canLeaveCompany: boolean;
    leaveUrl: string | null;
    canTransferOwnership: boolean;
    transferOwnershipUrl: string | null;
    transferCandidates: CompanyOwnershipCandidate[];
    storeUrl: string;
    status?: string;
    translations: CompaniesUiTranslations;
};

export default function CompanyMembers({
    company,
    members,
    invitations,
    canManageMembers,
    canLeaveCompany,
    leaveUrl,
    canTransferOwnership,
    transferOwnershipUrl,
    transferCandidates,
    storeUrl,
    status,
    translations,
}: Props) {
    const { i18n, errors } = usePage().props;
    const labels = translations.members;

    return (
        <>
            <Head title={labels.head_title} />
            <Stack gap="2xl">
                <SectionHeader
                    title={labels.title}
                    description={interpolate(labels.description, {
                        company: company.name,
                    })}
                />
                {status && <SystemMessage title={status} tone="money" />}
                {errors.invitation && (
                    <SystemMessage title={errors.invitation} tone="error" />
                )}
                {errors.membership && (
                    <SystemMessage title={errors.membership} tone="error" />
                )}
                {errors.ownership && (
                    <SystemMessage title={errors.ownership} tone="error" />
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
                        cancelLabel={i18n.common.actions.cancel}
                        closeLabel={i18n.common.accessibility.close_navigation}
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
                {canLeaveCompany && leaveUrl && (
                    <CompanyMembershipExit
                        leaveUrl={leaveUrl}
                        translations={labels}
                        cancelLabel={i18n.common.actions.cancel}
                        closeLabel={i18n.common.accessibility.close_navigation}
                    />
                )}
                {canTransferOwnership && transferOwnershipUrl && (
                    <CompanyOwnershipTransfer
                        transferUrl={transferOwnershipUrl}
                        candidates={transferCandidates}
                        translations={labels}
                        cancelLabel={i18n.common.actions.cancel}
                        closeLabel={i18n.common.accessibility.close_navigation}
                    />
                )}
            </Stack>
        </>
    );
}
