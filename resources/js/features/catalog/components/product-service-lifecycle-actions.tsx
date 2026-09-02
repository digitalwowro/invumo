import { router } from '@inertiajs/react';
import { GuardedActionDialog } from '@/components/app/guarded-action-dialog';
import { ConfirmationDialog } from '@/components/app/responsive-dialog';
import type { CatalogTranslations } from '@/types/catalog';
import type { DependencyGuard } from '@/types/dependency-guard';

type Props = {
    archiveUrl: string | null;
    restoreUrl: string | null;
    deleteUrl: string;
    deleteGuard: DependencyGuard;
    labels: CatalogTranslations['actions'];
    cancelLabel: string;
    closeLabel: string;
};

export function ProductServiceLifecycleActions(props: Props) {
    return (
        <>
            {props.archiveUrl && (
                <ConfirmationDialog
                    tone="default"
                    triggerLabel={props.labels.archive}
                    title={props.labels.archive_title}
                    description={props.labels.archive_description}
                    confirmLabel={props.labels.confirm_archive}
                    cancelLabel={props.cancelLabel}
                    closeLabel={props.closeLabel}
                    onConfirm={() =>
                        router.post(
                            props.archiveUrl!,
                            {},
                            { preserveScroll: true },
                        )
                    }
                />
            )}
            {props.restoreUrl && (
                <ConfirmationDialog
                    tone="default"
                    triggerLabel={props.labels.restore}
                    title={props.labels.restore_title}
                    description={props.labels.restore_description}
                    confirmLabel={props.labels.confirm_restore}
                    cancelLabel={props.cancelLabel}
                    closeLabel={props.closeLabel}
                    onConfirm={() =>
                        router.post(
                            props.restoreUrl!,
                            {},
                            { preserveScroll: true },
                        )
                    }
                />
            )}
            <GuardedActionDialog
                triggerLabel={props.labels.delete}
                title={props.labels.delete_title}
                description={props.labels.delete_description}
                confirmLabel={props.labels.confirm_delete}
                cancelLabel={props.cancelLabel}
                closeLabel={props.closeLabel}
                warningTitle={props.labels.dependency_warning_title}
                guard={props.deleteGuard}
                onConfirm={() => router.delete(props.deleteUrl)}
            />
        </>
    );
}
