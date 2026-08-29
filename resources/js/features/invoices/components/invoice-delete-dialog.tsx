import { useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { DestructiveActionDialog } from '@/components/app/destructive-action-dialog';
import type { DependencyGuard } from '@/types/dependency-guard';
import type { InvoiceTranslations } from '@/types/invoice';

type Props = {
    url: string;
    number: string;
    highRisk: boolean;
    stateVersion: string;
    guard: DependencyGuard;
    labels: InvoiceTranslations['deletion'];
};

export function InvoiceDeleteDialog({
    url,
    number,
    highRisk,
    stateVersion,
    guard,
    labels,
}: Props) {
    const { i18n } = usePage().props;
    const [open, setOpen] = useState(false);
    const form = useForm({
        confirmed: true,
        confirmed_high_risk: false,
        confirmation_number: '',
        deletion_state: stateVersion,
    });
    const errors = form.errors as typeof form.errors & { invoice?: string };

    const setDialogOpen = (nextOpen: boolean) => {
        setOpen(nextOpen);

        if (!nextOpen) {
            form.reset('confirmation_number');
        }
    };

    const destroy = () => {
        form.transform((data) => ({
            ...data,
            deletion_state: stateVersion,
        }));
        form.delete(url, {
            preserveScroll: true,
            onSuccess: () => setDialogOpen(false),
        });
    };

    return (
        <DestructiveActionDialog
            open={open}
            onOpenChange={setDialogOpen}
            triggerLabel={labels.trigger}
            title={labels.title}
            description={
                highRisk ? labels.high_risk_description : labels.description
            }
            cancelLabel={i18n.common.actions.cancel}
            confirmLabel={labels.confirm}
            closeLabel={i18n.common.accessibility.close_navigation}
            guard={guard}
            warningTitle={labels.dependency_title}
            generalError={errors.invoice}
            processing={form.processing}
            onConfirm={destroy}
            strongConfirmation={
                highRisk
                    ? {
                          expectedValue: number,
                          value: form.data.confirmation_number,
                          valueLabel: labels.number_label,
                          valueDescription: labels.number_description,
                          valueError: form.errors.confirmation_number,
                          acknowledged: form.data.confirmed_high_risk,
                          acknowledgmentLabel: labels.acknowledgment,
                          onValueChange: (value) =>
                              form.setData('confirmation_number', value),
                          onAcknowledgmentChange: (checked) =>
                              form.setData('confirmed_high_risk', checked),
                      }
                    : undefined
            }
        />
    );
}
