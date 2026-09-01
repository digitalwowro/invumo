import { useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { FormDialog } from '@/components/app/form-dialog';
import { CheckboxField, TextareaField } from '@/components/app/form-field';
import { Stack } from '@/components/app/layout';
import { SelectField } from '@/components/app/select-field';
import type { QuoteLifecycle, QuoteTranslations } from '@/types/quote';

type Props = {
    lifecycle: QuoteLifecycle;
    url: string;
    labels: QuoteTranslations['lifecycle'];
};

export function QuoteLifecycleDialog({ lifecycle, url, labels }: Props) {
    const { i18n } = usePage().props;
    const [open, setOpen] = useState(false);
    const form = useForm({ lifecycle, reason: '', confirmed: false });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.patch(url, {
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
            formId="quote-lifecycle-form"
            processing={form.processing}
        >
            <form id="quote-lifecycle-form" onSubmit={submit}>
                <Stack gap="lg">
                    <SelectField
                        name="lifecycle"
                        label={labels.status}
                        value={form.data.lifecycle}
                        error={form.errors.lifecycle}
                        onValueChange={(value) =>
                            form.setData('lifecycle', value as QuoteLifecycle)
                        }
                        options={(
                            ['DRAFT', 'SENT', 'ACCEPTED', 'REJECTED'] as const
                        ).map((value) => ({ value, label: labels[value] }))}
                    />
                    <TextareaField
                        label={labels.reason}
                        error={form.errors.reason}
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
