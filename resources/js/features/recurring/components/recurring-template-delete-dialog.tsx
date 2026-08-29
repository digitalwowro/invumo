import { router, usePage } from '@inertiajs/react';
import { ConfirmationDialog } from '@/components/app/responsive-dialog';
import type { RecurringTranslations } from '@/types/recurring-translations';

export function RecurringTemplateDeleteDialog({
    url,
    labels,
}: {
    url: string;
    labels: RecurringTranslations['deletion'];
}) {
    const { i18n } = usePage().props;

    return (
        <ConfirmationDialog
            triggerLabel={labels.delete}
            title={labels.title}
            description={labels.description}
            confirmLabel={labels.confirm}
            cancelLabel={i18n.common.actions.cancel}
            closeLabel={i18n.common.accessibility.close_navigation}
            tone="destructive"
            onConfirm={() => router.delete(url, { data: { confirmed: true } })}
        />
    );
}
