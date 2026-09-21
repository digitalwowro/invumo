import { router } from '@inertiajs/react';
import { useId, useState } from 'react';
import type { FormEvent } from 'react';
import { TextField } from '@/components/app/form-field';
import { SystemMessage } from '@/components/app/system-message';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { FieldGroup } from '@/components/ui/field';
import { Spinner } from '@/components/ui/spinner';
import type {
    CompanyCurrency,
    CompanyCurrencyTranslations,
} from '@/types/company-currency';

type Props = {
    currency: CompanyCurrency;
    labels: CompanyCurrencyTranslations;
    cancelLabel: string;
    closeLabel: string;
};

export function CompanyCurrencyEditDialog({
    currency,
    labels,
    cancelLabel,
    closeLabel,
}: Props) {
    const formId = useId();
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [precision, setPrecision] = useState(String(currency.precision));
    const isDirty = precision !== String(currency.precision);

    const reset = () => {
        setPrecision(String(currency.precision));
        setErrors({});
    };

    const changeOpen = (nextOpen: boolean) => {
        if (
            !nextOpen &&
            isDirty &&
            !processing &&
            !window.confirm(labels.unsaved_warning)
        ) {
            return;
        }

        if (nextOpen) {
            reset();
        }

        setOpen(nextOpen);
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!currency.updateUrl) {
            return;
        }

        router.patch(
            currency.updateUrl,
            { currency_precision: precision },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onError: (nextErrors) => setErrors(nextErrors),
                onSuccess: () => {
                    setOpen(false);
                    reset();
                },
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={changeOpen}>
            <DialogTrigger asChild>
                <Button type="button" variant="secondary">
                    {labels.edit}
                </Button>
            </DialogTrigger>
            <DialogContent closeLabel={closeLabel}>
                <DialogHeader>
                    <DialogTitle>{labels.edit_title}</DialogTitle>
                    <DialogDescription>
                        {labels.edit_description}
                    </DialogDescription>
                </DialogHeader>
                <form id={formId} onSubmit={submit}>
                    <FieldGroup>
                        {errors.currency && (
                            <SystemMessage
                                title={errors.currency}
                                tone="error"
                            />
                        )}
                        <TextField
                            label={labels.fields.currency_code}
                            input={{ value: currency.code, disabled: true }}
                        />
                        <TextField
                            label={labels.fields.currency_precision}
                            description={
                                labels.field_descriptions.currency_precision
                            }
                            error={errors.currency_precision}
                            input={{
                                type: 'number',
                                value: precision,
                                required: true,
                                min: 0,
                                max: 8,
                                step: 1,
                                inputMode: 'numeric',
                                onChange: (event) =>
                                    setPrecision(event.target.value),
                            }}
                        />
                    </FieldGroup>
                </form>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button type="button" variant="secondary">
                            {cancelLabel}
                        </Button>
                    </DialogClose>
                    <Button
                        type="submit"
                        form={formId}
                        disabled={processing || !isDirty}
                    >
                        {processing && <Spinner />}
                        {labels.save}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
