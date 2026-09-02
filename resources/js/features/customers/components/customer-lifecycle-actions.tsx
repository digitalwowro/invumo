import { router } from '@inertiajs/react';
import { GuardedActionDialog } from '@/components/app/guarded-action-dialog';
import { ConfirmationDialog } from '@/components/app/responsive-dialog';
import type {
    CustomerDeleteGuard,
    CustomerTranslations,
} from '@/types/customer';

type Props = {
    archiveUrl: string | null;
    restoreUrl: string | null;
    deleteUrl: string | null;
    deleteGuard: CustomerDeleteGuard;
    publicDecisionIdentity: { count: number; eraseUrl: string | null };
    labels: CustomerTranslations['workspace'];
    cancelLabel: string;
    closeLabel: string;
};

export function CustomerLifecycleActions({
    archiveUrl,
    restoreUrl,
    deleteUrl,
    deleteGuard,
    publicDecisionIdentity,
    labels,
    cancelLabel,
    closeLabel,
}: Props) {
    return (
        <>
            {archiveUrl && (
                <ConfirmationDialog
                    tone="default"
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
                <GuardedActionDialog
                    triggerLabel={labels.delete}
                    title={labels.delete_title}
                    description={labels.delete_description}
                    confirmLabel={labels.confirm_delete}
                    cancelLabel={cancelLabel}
                    closeLabel={closeLabel}
                    warningTitle={labels.delete_dependency_title}
                    guard={deleteGuard}
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
