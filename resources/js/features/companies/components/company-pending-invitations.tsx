import { router } from '@inertiajs/react';
import { Cluster } from '@/components/app/layout';
import { OperationalTable } from '@/components/app/operational-table';
import type { OperationalTableStateCopy } from '@/components/app/operational-table';
import { ConfirmationDialog } from '@/components/app/responsive-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type {
    CompanyMembersTranslations,
    CompanyPendingInvitation,
    SupportedLocale,
} from '@/types';

type Props = {
    invitations: CompanyPendingInvitation[];
    locale: SupportedLocale;
    translations: CompanyMembersTranslations;
    cancelLabel: string;
    closeLabel: string;
};

const emptyCopy = (message: string): OperationalTableStateCopy => ({
    loading: '',
    emptyTitle: message,
    emptyDescription: message,
    noResultsTitle: '',
    noResultsDescription: '',
    errorTitle: '',
    errorDescription: '',
});

export function CompanyPendingInvitations({
    invitations,
    locale,
    translations,
    cancelLabel,
    closeLabel,
}: Props) {
    const dateFormatter = new Intl.DateTimeFormat(locale, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });

    return (
        <OperationalTable
            ariaLabel={translations.pending_title}
            rows={invitations}
            rowKey={(invitation) => invitation.id}
            state={invitations.length === 0 ? 'empty' : 'ready'}
            stateCopy={emptyCopy(translations.empty_pending)}
            columns={[
                {
                    key: 'email',
                    label: translations.email_column,
                    kind: 'identity',
                    render: (invitation) => invitation.email,
                },
                {
                    key: 'role',
                    label: translations.role_column,
                    kind: 'status',
                    render: (invitation) => (
                        <Badge variant="muted">
                            {translations.roles[invitation.role]}
                        </Badge>
                    ),
                },
                {
                    key: 'expires',
                    label: translations.expires_column,
                    kind: 'data',
                    render: (invitation) => (
                        <Cluster gap="sm">
                            <time dateTime={invitation.expiresAt}>
                                {dateFormatter.format(
                                    new Date(invitation.expiresAt),
                                )}
                            </time>
                            <Badge
                                variant={
                                    invitation.expired ? 'warning' : 'quiet'
                                }
                            >
                                {invitation.expired
                                    ? translations.expired
                                    : translations.pending}
                            </Badge>
                        </Cluster>
                    ),
                },
                {
                    key: 'actions',
                    label: translations.actions_column,
                    kind: 'actions',
                    render: (invitation) => (
                        <Cluster gap="sm">
                            <Button
                                type="button"
                                variant="secondary"
                                onClick={() =>
                                    router.post(
                                        invitation.resendUrl,
                                        {},
                                        {
                                            preserveScroll: true,
                                        },
                                    )
                                }
                            >
                                {translations.resend}
                            </Button>
                            <ConfirmationDialog
                                triggerLabel={translations.revoke}
                                title={translations.revoke_title}
                                description={translations.revoke_description}
                                confirmLabel={translations.confirm_revoke}
                                cancelLabel={cancelLabel}
                                closeLabel={closeLabel}
                                onConfirm={() =>
                                    router.delete(invitation.revokeUrl, {
                                        preserveScroll: true,
                                    })
                                }
                            />
                        </Cluster>
                    ),
                },
            ]}
        />
    );
}
