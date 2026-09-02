import { router } from '@inertiajs/react';
import { Cluster, Stack } from '@/components/app/layout';
import { OperationalTable } from '@/components/app/operational-table';
import type { OperationalTableStateCopy } from '@/components/app/operational-table';
import { ConfirmationDialog } from '@/components/app/responsive-dialog';
import { BodyStrong, SecondaryText } from '@/components/app/typography';
import { Badge } from '@/components/ui/badge';
import { CustomerContactEditDialog } from '@/features/customers/components/customer-contact-edit-dialog';
import type {
    CustomerContact,
    CustomerContactTranslations,
    CustomerFieldLimits,
} from '@/types/customer';

type Props = {
    contacts: CustomerContact[];
    limits: CustomerFieldLimits;
    labels: CustomerContactTranslations;
    cancelLabel: string;
    closeLabel: string;
};

type ContactActionsProps = Omit<Props, 'contacts'> & {
    contact: CustomerContact;
};

function stateCopy(
    labels: CustomerContactTranslations,
): OperationalTableStateCopy {
    return {
        loading: '',
        emptyTitle: labels.empty_title,
        emptyDescription: labels.empty_description,
        noResultsTitle: '',
        noResultsDescription: '',
        errorTitle: '',
        errorDescription: '',
    };
}

export function CustomerContactTable({
    contacts,
    limits,
    labels,
    cancelLabel,
    closeLabel,
}: Props) {
    return (
        <OperationalTable
            ariaLabel={labels.title}
            rows={contacts}
            rowKey={(contact) => contact.id}
            state={contacts.length === 0 ? 'empty' : 'ready'}
            stateCopy={stateCopy(labels)}
            columns={[
                {
                    key: 'contact',
                    label: labels.columns.contact,
                    kind: 'identity',
                    render: (contact) => (
                        <Stack gap="xs">
                            <BodyStrong>{contact.name}</BodyStrong>
                            {contact.positionTitle && (
                                <SecondaryText>
                                    {contact.positionTitle}
                                </SecondaryText>
                            )}
                        </Stack>
                    ),
                },
                {
                    key: 'details',
                    label: labels.columns.details,
                    render: (contact) => (
                        <Stack gap="xs">
                            <BodyStrong>
                                {contact.email ?? labels.not_available}
                            </BodyStrong>
                            {contact.phone && (
                                <SecondaryText>{contact.phone}</SecondaryText>
                            )}
                        </Stack>
                    ),
                },
                {
                    key: 'designations',
                    label: labels.columns.designations,
                    kind: 'status',
                    render: (contact) => (
                        <Cluster gap="xs">
                            {contact.isPrimary && (
                                <Badge variant="positive">
                                    {labels.primary}
                                </Badge>
                            )}
                            {contact.isBilling && (
                                <Badge variant="quiet">{labels.billing}</Badge>
                            )}
                        </Cluster>
                    ),
                },
                {
                    key: 'status',
                    label: labels.columns.status,
                    kind: 'status',
                    render: (contact) => (
                        <Badge variant={contact.archived ? 'muted' : 'quiet'}>
                            {contact.archived ? labels.archived : labels.active}
                        </Badge>
                    ),
                },
                {
                    key: 'actions',
                    label: labels.columns.actions,
                    kind: 'actions',
                    render: (contact) => (
                        <ContactActions
                            contact={contact}
                            limits={limits}
                            labels={labels}
                            cancelLabel={cancelLabel}
                            closeLabel={closeLabel}
                        />
                    ),
                },
            ]}
        />
    );
}

function ContactActions({
    contact,
    limits,
    labels,
    cancelLabel,
    closeLabel,
}: ContactActionsProps) {
    return (
        <Cluster gap="sm">
            {contact.updateUrl && (
                <CustomerContactEditDialog
                    contact={contact}
                    limits={limits}
                    labels={labels}
                    cancelLabel={cancelLabel}
                    closeLabel={closeLabel}
                />
            )}
            {contact.archiveUrl && (
                <ConfirmationDialog
                    tone="default"
                    triggerLabel={labels.archive}
                    title={labels.archive_title}
                    description={labels.archive_description}
                    confirmLabel={labels.confirm_archive}
                    cancelLabel={cancelLabel}
                    closeLabel={closeLabel}
                    onConfirm={() =>
                        router.post(
                            contact.archiveUrl as string,
                            {},
                            {
                                preserveScroll: true,
                            },
                        )
                    }
                />
            )}
            {contact.restoreUrl && (
                <ConfirmationDialog
                    tone="default"
                    triggerLabel={labels.restore}
                    title={labels.restore_title}
                    description={labels.restore_description}
                    confirmLabel={labels.confirm_restore}
                    cancelLabel={cancelLabel}
                    closeLabel={closeLabel}
                    onConfirm={() =>
                        router.post(
                            contact.restoreUrl as string,
                            {},
                            {
                                preserveScroll: true,
                            },
                        )
                    }
                />
            )}
            {contact.deleteUrl && (
                <ConfirmationDialog
                    triggerLabel={labels.delete}
                    title={labels.delete_title}
                    description={labels.delete_description}
                    confirmLabel={labels.confirm_delete}
                    cancelLabel={cancelLabel}
                    closeLabel={closeLabel}
                    onConfirm={() =>
                        router.delete(contact.deleteUrl as string, {
                            preserveScroll: true,
                        })
                    }
                />
            )}
        </Cluster>
    );
}
