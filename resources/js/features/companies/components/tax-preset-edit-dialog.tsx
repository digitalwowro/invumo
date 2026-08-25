import { router } from '@inertiajs/react';
import { useId, useState } from 'react';
import type { FormEvent } from 'react';
import { CheckboxField, TextField } from '@/components/app/form-field';
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
    CompanyTaxPresetTranslations,
    TaxPreset,
} from '@/types/company-tax';

type Props = {
    preset: TaxPreset;
    labels: CompanyTaxPresetTranslations;
    cancelLabel: string;
    closeLabel: string;
};

export function TaxPresetEditDialog({
    preset,
    labels,
    cancelLabel,
    closeLabel,
}: Props) {
    const formId = useId();
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [name, setName] = useState(preset.name);
    const [percentage, setPercentage] = useState(preset.percentage);
    const [isDefault, setIsDefault] = useState(preset.isDefault);
    const isDirty =
        name !== preset.name ||
        percentage !== preset.percentage ||
        isDefault !== preset.isDefault;

    const reset = () => {
        setName(preset.name);
        setPercentage(preset.percentage);
        setIsDefault(preset.isDefault);
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

        if (!preset.updateUrl) {
            return;
        }

        router.patch(
            preset.updateUrl,
            { name, percentage, is_default: isDefault },
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
                        {errors.tax_preset && (
                            <SystemMessage
                                title={errors.tax_preset}
                                tone="error"
                            />
                        )}
                        <TextField
                            label={labels.fields.name}
                            error={errors.name}
                            input={{
                                value: name,
                                required: true,
                                maxLength: 120,
                                onChange: (event) =>
                                    setName(event.target.value),
                            }}
                        />
                        <TextField
                            label={labels.fields.percentage}
                            description={labels.field_descriptions.percentage}
                            error={errors.percentage}
                            input={{
                                value: percentage,
                                required: true,
                                inputMode: 'decimal',
                                onChange: (event) =>
                                    setPercentage(event.target.value),
                            }}
                        />
                        <CheckboxField
                            label={labels.fields.is_default}
                            description={labels.field_descriptions.is_default}
                            error={errors.is_default}
                            checkbox={{
                                checked: isDefault,
                                onCheckedChange: (checked) =>
                                    setIsDefault(checked === true),
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
