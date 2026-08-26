import { useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { CheckboxField, TextareaField } from '@/components/app/form-field';
import { Stack } from '@/components/app/layout';
import { FormDialog } from '@/components/app/responsive-dialog';
import type { QuoteTranslations } from '@/types/quote';

type Props = {
    url: string;
    invoiceNumber: string;
    labels: QuoteTranslations['unlink'];
};

export function QuoteInvoiceUnlinkDialog({
    url,
    invoiceNumber,
    labels,
}: Props) {
    const { i18n } = usePage().props;
    const [open, setOpen] = useState(false);
    const form = useForm({ reason: '', confirmed: false });
    const errors = form.errors as Record<string, string>;

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
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
            title={labels.title.replace(':number', invoiceNumber)}
            description={labels.description}
            cancelLabel={i18n.common.actions.cancel}
            confirmLabel={labels.confirm}
            closeLabel={i18n.common.accessibility.close_navigation}
            formId={`unlink-invoice-${invoiceNumber}`}
            processing={form.processing}
        >
            <form id={`unlink-invoice-${invoiceNumber}`} onSubmit={submit}>
                <Stack gap="lg">
                    <TextareaField
                        label={labels.reason}
                        error={errors.reason ?? errors.unlink}
                        textarea={{
                            value: form.data.reason,
                            maxLength: 500,
                            rows: 4,
                            onChange: (event) =>
                                form.setData('reason', event.target.value),
                        }}
                    />
                    <CheckboxField
                        label={labels.confirmation}
                        error={form.errors.confirmed}
                        checkbox={{
                            checked: form.data.confirmed,
                            onCheckedChange: (checked) =>
                                form.setData('confirmed', checked === true),
                        }}
                    />
                </Stack>
            </form>
        </FormDialog>
    );
}
