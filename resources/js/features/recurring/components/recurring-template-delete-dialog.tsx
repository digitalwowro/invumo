import { router, usePage } from '@inertiajs/react';
import { GuardedActionDialog } from '@/components/app/guarded-action-dialog';
import type { DependencyGuard } from '@/types/dependency-guard';
import type { RecurringTranslations } from '@/types/recurring-translations';

export function RecurringTemplateDeleteDialog({
    url,
    highRisk,
    stateVersion,
    guard,
    labels,
}: {
    url: string;
    highRisk: boolean;
    stateVersion: string;
    guard: DependencyGuard;
    labels: RecurringTranslations['deletion'];
}) {
    const { i18n, errors } = usePage<{ errors: { template?: string } }>().props;

    return (
        <GuardedActionDialog
            triggerLabel={labels.delete}
            title={labels.title}
            description={
                highRisk ? labels.high_risk_description : labels.description
            }
            confirmLabel={labels.confirm}
            cancelLabel={i18n.common.actions.cancel}
            closeLabel={i18n.common.accessibility.close_navigation}
            warningTitle={labels.dependency_title}
            guard={guard}
            generalError={errors.template}
            tone="destructive"
            onConfirm={() =>
                router.delete(url, {
                    data: {
                        confirmed: true,
                        confirmed_high_risk: highRisk,
                        deletion_state: stateVersion,
                    },
                })
            }
        />
    );
}
