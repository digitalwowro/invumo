import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Stack } from '@/components/app/layout';
import { FormDialog } from '@/components/app/responsive-dialog';
import { SystemMessage } from '@/components/app/system-message';
import type {
    InvoiceTransactionRow,
    InvoiceTransactionTranslations,
} from '@/types/invoice-transaction';

type Props = {
    transaction: InvoiceTransactionRow;
    labels: InvoiceTransactionTranslations;
    disabled: boolean;
    disabledDescription?: string;
};

export function PaymentReceivedDialog(props: Props) {
    const [open, setOpen] = useState(false);
    const receipt = props.transaction.receipt;
    const form = useForm({
        delivery_key: crypto.randomUUID(),
        transaction_edit_version: props.transaction.editVersion,
        confirmed: true,
    });
    const deliveryError = (form.errors as Record<string, string>).delivery;
    const changeOpen = (next: boolean) => {
        if (next) {
            const initial = {
                delivery_key: crypto.randomUUID(),
                transaction_edit_version: props.transaction.editVersion,
                confirmed: true,
            };
            form.setDefaults(initial);
            form.setData(initial);
            form.clearErrors();
        }

        setOpen(next);
    };
    const send = () => {
        if (!receipt?.sendUrl) {
            return;
        }

        form.post(receipt.sendUrl, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    if (!receipt?.sendUrl) {
        return null;
    }

    return (
        <FormDialog
            open={open}
            onOpenChange={changeOpen}
            triggerLabel={
                receipt.count > 0
                    ? props.labels.receipt.send_again
                    : props.labels.receipt.send
            }
            title={props.labels.receipt.title}
            description={props.labels.receipt.description}
            cancelLabel={props.labels.cancel}
            confirmLabel={props.labels.receipt.confirm}
            closeLabel={props.labels.close}
            formId={`payment-received-${props.transaction.id}`}
            processing={form.processing}
            triggerDisabled={props.disabled}
            triggerDisabledDescription={props.disabledDescription}
            onConfirm={send}
        >
            <Stack gap="md">
                {receipt.mayHaveBeenSent && (
                    <SystemMessage
                        title={props.labels.receipt.warning}
                        tone="warning"
                    />
                )}
                {deliveryError && (
                    <SystemMessage title={deliveryError} tone="error" />
                )}
                <span className="sr-only">
                    {props.labels.receipt.description}
                </span>
            </Stack>
        </FormDialog>
    );
}
