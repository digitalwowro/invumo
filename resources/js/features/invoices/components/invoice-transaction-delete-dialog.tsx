import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { FormDialog } from '@/components/app/form-dialog';
import { SystemMessage } from '@/components/app/system-message';
import type {
    InvoiceTransactionRow,
    InvoiceTransactionTranslations,
} from '@/types/invoice-transaction';

export function InvoiceTransactionDeleteDialog(props: {
    transaction: InvoiceTransactionRow;
    labels: InvoiceTransactionTranslations;
    disabled?: boolean;
    disabledDescription?: string;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        edit_version: props.transaction.editVersion,
        mutation_key: crypto.randomUUID(),
        confirmed: true,
    });
    const generalError = (form.errors as Record<string, string>).transaction;
    const changeOpen = (next: boolean) => {
        if (next) {
            const initial = {
                edit_version: props.transaction.editVersion,
                mutation_key: crypto.randomUUID(),
                confirmed: true,
            };
            form.setDefaults(initial);
            form.setData(initial);
            form.clearErrors();
        }

        setOpen(next);
    };

    return (
        <FormDialog
            open={open}
            onOpenChange={changeOpen}
            triggerLabel={props.labels.delete}
            title={props.labels.delete_title}
            description={props.labels.delete_description}
            cancelLabel={props.labels.cancel}
            confirmLabel={props.labels.confirm_delete}
            closeLabel={props.labels.close}
            formId={`delete-transaction-${props.transaction.id}`}
            processing={form.processing}
            triggerDisabled={props.disabled}
            triggerDisabledDescription={props.disabledDescription}
            triggerSize="compact"
            onConfirm={() =>
                props.transaction.deleteUrl &&
                form.delete(props.transaction.deleteUrl, {
                    preserveScroll: true,
                    onSuccess: () => setOpen(false),
                })
            }
        >
            {props.transaction.receipt?.mayHaveBeenSent && (
                <SystemMessage
                    title={props.labels.receipt.warning}
                    tone="warning"
                />
            )}
            {generalError && (
                <SystemMessage title={generalError} tone="error" />
            )}
            <span className="sr-only">{props.labels.delete_description}</span>
        </FormDialog>
    );
}
