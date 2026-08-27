import { router } from '@inertiajs/react';
import { ConfirmationDialog } from '@/components/app/responsive-dialog';
import type { CustomerTranslations } from '@/types/customer';

type Props = {
    archiveUrl: string | null;
    restoreUrl: string | null;
    deleteUrl: string | null;
    publicDecisionIdentity: { count: number; eraseUrl: string | null };
    labels: CustomerTranslations['workspace'];
    cancelLabel: string;
    closeLabel: string;
};

export function CustomerLifecycleActions({
    archiveUrl,
    restoreUrl,
    deleteUrl,
    publicDecisionIdentity,
    labels,
    cancelLabel,
    closeLabel,
}: Props) {
    return (
        <>
            {archiveUrl && (
                <ConfirmationDialog
                    triggerLabel={labels.archive}
                    title={labels.archive_title}
                    description={labels.archive_description}
                    confirmLabel={labels.confirm_archive}
                    cancelLabel={cancelLabel}
                    closeLabel={closeLabel}
                    onConfirm={() =>
                        router.post(archiveUrl, {}, { preserveScroll: true })
                    }
                />
            )}
            {restoreUrl && (
                <ConfirmationDialog
                    tone="default"
                    triggerLabel={labels.restore}
                    title={labels.restore_title}
                    description={labels.restore_description}
                    confirmLabel={labels.confirm_restore}
                    cancelLabel={cancelLabel}
                    closeLabel={closeLabel}
                    onConfirm={() =>
                        router.post(restoreUrl, {}, { preserveScroll: true })
                    }
                />
            )}
            {deleteUrl && (
                <ConfirmationDialog
                    triggerLabel={labels.delete}
                    title={labels.delete_title}
                    description={labels.delete_description}
                    confirmLabel={labels.confirm_delete}
                    cancelLabel={cancelLabel}
                    closeLabel={closeLabel}
                    onConfirm={() => router.delete(deleteUrl)}
                />
            )}
            {publicDecisionIdentity.eraseUrl && (
                <ConfirmationDialog
                    triggerLabel={labels.erase_public_decision_identity}
                    title={labels.erase_public_decision_identity_title}
                    description={
                        labels.erase_public_decision_identity_description
                    }
                    confirmLabel={labels.confirm_erase_public_decision_identity}
                    cancelLabel={cancelLabel}
                    closeLabel={closeLabel}
                    onConfirm={() =>
                        router.delete(publicDecisionIdentity.eraseUrl!, {
                            data: { confirmed: true },
                            preserveScroll: true,
                        })
                    }
                />
            )}
        </>
    );
}
