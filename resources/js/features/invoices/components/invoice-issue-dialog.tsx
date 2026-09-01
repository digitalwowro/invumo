import { useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { FormDialog } from '@/components/app/form-dialog';
import { SystemMessage } from '@/components/app/system-message';
import type { InvoiceTranslations } from '@/types/invoice';

type Props = {
    url: string;
    editVersion: number;
    labels: InvoiceTranslations['issue'];
    disabled?: boolean;
};

export function InvoiceIssueDialog({
    url,
    editVersion,
    labels,
    disabled = false,
}: Props) {
    const { i18n } = usePage().props;
    const [open, setOpen] = useState(false);
    const form = useForm({ edit_version: editVersion });
    const issueError = (
        form.errors as typeof form.errors & { invoice?: string }
    ).invoice;
    const errorMessage = issueError ?? form.errors.edit_version;

    const submit = () => {
        form.post(url, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
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
            formId="invoice-issue-form"
            processing={form.processing}
            triggerTestId="invoice-issue-trigger"
            confirmTestId="invoice-issue-confirm"
            triggerDisabled={disabled}
            triggerDisabledDescription={
                disabled ? labels.save_first : undefined
            }
            onConfirm={submit}
        >
            {errorMessage && (
                <SystemMessage title={errorMessage} tone="error" />
            )}
        </FormDialog>
    );
}
