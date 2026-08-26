import { router, usePage } from '@inertiajs/react';
import { ConfirmationDialog } from '@/components/app/responsive-dialog';
import type { QuoteTranslations } from '@/types/quote';

type Props = {
    url: string;
    highRisk: boolean;
    labels: QuoteTranslations['deletion'];
};

export function QuoteDeleteDialog({ url, highRisk, labels }: Props) {
    const { i18n } = usePage().props;

    return (
        <ConfirmationDialog
            triggerLabel={labels.delete}
            title={labels.title}
            description={
                highRisk ? labels.high_risk_description : labels.description
            }
            confirmLabel={labels.confirm}
            cancelLabel={i18n.common.actions.cancel}
            closeLabel={i18n.common.accessibility.close_navigation}
            tone="destructive"
            onConfirm={() =>
                router.delete(url, {
                    data: {
                        confirmed: true,
                        confirmed_high_risk: highRisk,
                    },
                })
            }
        />
    );
}
