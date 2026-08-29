import { router, usePage } from '@inertiajs/react';
import { GuardedActionDialog } from '@/components/app/guarded-action-dialog';
import type { DependencyGuard } from '@/types/dependency-guard';
import type { QuoteTranslations } from '@/types/quote';

type Props = {
    url: string;
    highRisk: boolean;
    stateVersion: string;
    guard: DependencyGuard;
    labels: QuoteTranslations['deletion'];
};

export function QuoteDeleteDialog({
    url,
    highRisk,
    stateVersion,
    guard,
    labels,
}: Props) {
    const { i18n, errors } = usePage<{ errors: { quote?: string } }>().props;

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
            generalError={errors.quote}
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
