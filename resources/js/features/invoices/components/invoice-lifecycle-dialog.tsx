import { useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { CheckboxField, TextareaField } from '@/components/app/form-field';
import { Stack } from '@/components/app/layout';
import { FormDialog } from '@/components/app/responsive-dialog';
import { SystemMessage } from '@/components/app/system-message';
import type {
    InvoiceLifecycleActions,
    InvoiceTranslations,
} from '@/types/invoice';

type Props = {
    action: 'cancel' | 'reopen';
    url: string;
    editVersion: number;
    workflow: InvoiceLifecycleActions;
    labels: InvoiceTranslations['lifecycle'];
    disabled: boolean;
};

export function InvoiceLifecycleDialog(props: Props) {
    const { i18n } = usePage().props;
    const [open, setOpen] = useState(false);
    const labels = props.labels[props.action];
    const form = useForm({
        edit_version: props.editVersion,
        reason: '',
        confirmed: props.action === 'cancel',
    });
    const errors = form.errors as typeof form.errors & { invoice?: string };
    const errorMessage = errors.invoice ?? errors.edit_version;
    const blocked = props.action === 'cancel' && !props.workflow.canCancel;

    const submit = (event?: FormEvent<HTMLFormElement>) => {
        event?.preventDefault();
        form.post(props.url, {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                form.reset('reason', 'confirmed');
            },
        });
    };

    return (
        <FormDialog
            open={open}
            onOpenChange={setOpen}
            triggerLabel={labels.trigger}
            title={labels.title}
            description={labels.description}
            cancelLabel={i18n.common.actions.cancel}
            confirmLabel={labels.confirm}
            closeLabel={i18n.common.accessibility.close_navigation}
            formId={`invoice-${props.action}-form`}
            processing={form.processing}
            triggerTestId={`invoice-${props.action}-trigger`}
            confirmTestId={`invoice-${props.action}-confirm`}
            triggerDisabled={props.disabled}
            triggerDisabledDescription={
                props.disabled ? labels.save_first : undefined
            }
            confirmDisabled={blocked}
            confirmDisabledDescription={
                blocked ? props.workflow.stateDescription : undefined
            }
            onConfirm={() => submit()}
        >
            <Stack gap="lg">
                {props.action === 'cancel' && (
                    <SystemMessage
                        title={props.workflow.stateTitle}
                        description={props.workflow.stateDescription}
                        tone={blocked ? 'warning' : 'info'}
                    />
                )}
                {props.action === 'reopen' && (
                    <form id="invoice-reopen-form" onSubmit={submit}>
                        <Stack gap="lg">
                            <TextareaField
                                label={props.labels.reopen.reason}
                                error={form.errors.reason}
                                textarea={{
                                    value: form.data.reason,
                                    maxLength: 500,
                                    rows: 4,
                                    onChange: (event) =>
                                        form.setData(
                                            'reason',
                                            event.target.value,
                                        ),
                                }}
                            />
                            <CheckboxField
                                label={props.labels.reopen.confirmation}
                                error={form.errors.confirmed}
                                checkbox={{
                                    checked: form.data.confirmed,
                                    onCheckedChange: (checked) =>
                                        form.setData(
                                            'confirmed',
                                            checked === true,
                                        ),
                                }}
                            />
                        </Stack>
                    </form>
                )}
                {errorMessage && (
                    <SystemMessage title={errorMessage} tone="error" />
                )}
            </Stack>
        </FormDialog>
    );
}
