import { useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Stack } from '@/components/app/layout';
import { FormDialog } from '@/components/app/responsive-dialog';
import { SystemMessage } from '@/components/app/system-message';
import { MoneyValue } from '@/components/domain/money-value';
import type { QuoteInvoiceAllocation, QuoteTranslations } from '@/types/quote';

type Props = {
    url: string;
    creationKey: string;
    allocation: QuoteInvoiceAllocation;
    currencyCode: string | null;
    dirty: boolean;
    labels: QuoteTranslations['conversion'];
};

export function QuoteConversionDialog({
    url,
    creationKey,
    allocation,
    currencyCode,
    dirty,
    labels,
}: Props) {
    const { i18n } = usePage().props;
    const [open, setOpen] = useState(false);
    const form = useForm({
        creation_key: creationKey,
        confirmed_override: allocation.conversionMode === 'override',
    });
    const errors = form.errors as Record<string, string>;
    const blocked = allocation.conversionMode === 'blocked';

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(url);
    };

    return (
        <FormDialog
            open={open}
            onOpenChange={setOpen}
            triggerLabel={labels.trigger}
            title={labels.title}
            description={
                allocation.conversionMode === 'override'
                    ? labels.override_description
                    : labels.description
            }
            cancelLabel={i18n.common.actions.cancel}
            confirmLabel={labels.confirm}
            closeLabel={i18n.common.accessibility.close_navigation}
            formId="quote-conversion-form"
            processing={form.processing}
            triggerDisabled={dirty || blocked}
            triggerDisabledDescription={
                dirty ? labels.save_first : blocked ? labels.blocked : undefined
            }
            triggerTestId="convert-quote"
            confirmTestId="confirm-convert-quote"
        >
            <form id="quote-conversion-form" onSubmit={submit}>
                <Stack gap="md">
                    <p className="text-sm text-foreground-muted">
                        {labels.invoice_total}{' '}
                        <MoneyValue
                            value={`${allocation.quoted} ${currencyCode ?? ''}`}
                            emphasis="strong"
                        />
                    </p>
                    {allocation.willOverAllocate && (
                        <SystemMessage
                            title={labels.over_allocation}
                            tone="warning"
                        />
                    )}
                    {errors.conversion && (
                        <SystemMessage title={errors.conversion} tone="error" />
                    )}
                </Stack>
            </form>
        </FormDialog>
    );
}
