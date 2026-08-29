import { useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { DestructiveActionDialog } from '@/components/app/destructive-action-dialog';
import { FormSection } from '@/components/app/form-section';
import { SystemMessage } from '@/components/app/system-message';
import type { CompanyDataLifecycleTranslations } from '@/types/company-settings';
import type { DependencyGuard } from '@/types/dependency-guard';

type Props = {
    url: string;
    companyName: string;
    stateVersion: string;
    guard: DependencyGuard;
    labels: CompanyDataLifecycleTranslations;
};

export function CompanyErasure(props: Props) {
    const [open, setOpen] = useState(false);
    const { i18n } = usePage().props;
    const form = useForm({
        confirmed: true,
        confirmed_high_risk: false,
        confirmation_name: '',
        deletion_state: props.stateVersion,
    });
    const errors = form.errors as typeof form.errors & { company?: string };

    const erase = () => {
        form.transform((data) => ({
            ...data,
            deletion_state: props.stateVersion,
        }));
        form.delete(props.url, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <FormSection
            title={props.labels.title}
            description={props.labels.description}
        >
            <SystemMessage
                tone="error"
                title={props.labels.warning_title}
                description={props.labels.warning_description}
            />
            <DestructiveActionDialog
                open={open}
                onOpenChange={(nextOpen) => {
                    setOpen(nextOpen);

                    if (!nextOpen) {
                        form.setData('confirmation_name', '');
                        form.setData('confirmed_high_risk', false);
                        form.clearErrors();
                    }
                }}
                triggerLabel={props.labels.trigger}
                title={props.labels.dialog_title}
                description={props.labels.dialog_description}
                cancelLabel={i18n.common.actions.cancel}
                confirmLabel={props.labels.confirm}
                closeLabel={i18n.common.accessibility.close_navigation}
                guard={props.guard}
                warningTitle={props.labels.dependency_title}
                generalError={errors.company}
                processing={form.processing}
                onConfirm={erase}
                strongConfirmation={{
                    expectedValue: props.companyName,
                    value: form.data.confirmation_name,
                    valueLabel: props.labels.name_label,
                    valueDescription: props.labels.name_description.replace(
                        ':value',
                        props.companyName,
                    ),
                    valueError: form.errors.confirmation_name,
                    acknowledged: form.data.confirmed_high_risk,
                    acknowledgmentLabel: props.labels.acknowledgment,
                    onValueChange: (value) =>
                        form.setData('confirmation_name', value),
                    onAcknowledgmentChange: (checked) =>
                        form.setData('confirmed_high_risk', checked),
                }}
            />
        </FormSection>
    );
}
