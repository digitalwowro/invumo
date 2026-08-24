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
import { Spinner } from '@/components/ui/spinner';
import type { PlatformCommonTranslations } from '@/types';

type MutationDialogProps = {
    method: 'post' | 'delete';
    url: string;
    triggerLabel: string;
    title: string;
    description: string;
    confirmLabel: string;
    translations: PlatformCommonTranslations;
    destructive?: boolean;
    disabled?: boolean;
};

export function PlatformMutationDialog({
    method,
    url,
    triggerLabel,
    title,
    description,
    confirmLabel,
    translations,
    destructive = true,
    disabled = false,
}: MutationDialogProps) {
    const formId = useId();
    const [open, setOpen] = useState(false);
    const [reason, setReason] = useState('');
    const [confirmed, setConfirmed] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const reset = () => {
        setReason('');
        setConfirmed(false);
        setErrors({});
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.visit(url, {
            method,
            data: { reason, confirmed },
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onError: (nextErrors) => setErrors(nextErrors),
            onSuccess: () => {
                setOpen(false);
                reset();
            },
        });
    };

    const operationError = errors.operation ?? errors.confirmed;

    return (
        <Dialog
            open={open}
            onOpenChange={(nextOpen) => {
                setOpen(nextOpen);

                if (!nextOpen) {
                    reset();
                }
            }}
        >
            <DialogTrigger asChild>
                <Button
                    type="button"
                    variant={destructive ? 'destructive' : 'secondary'}
                    disabled={disabled}
                >
                    {triggerLabel}
                </Button>
            </DialogTrigger>
            <DialogContent closeLabel={translations.close}>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                <form id={formId} className="space-y-5" onSubmit={submit}>
                    {operationError && (
                        <SystemMessage title={operationError} tone="error" />
                    )}
                    <TextField
                        label={translations.reason}
                        error={errors.reason}
                        input={{
                            value: reason,
                            placeholder: translations.reason_placeholder,
                            required: true,
                            maxLength: 500,
                            onChange: (event) => setReason(event.target.value),
                        }}
                    />
                    <CheckboxField
                        label={translations.confirm}
                        checkbox={{
                            checked: confirmed,
                            required: true,
                            onCheckedChange: (checked) =>
                                setConfirmed(checked === true),
                        }}
                    />
                </form>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button type="button" variant="secondary">
                            {translations.cancel}
                        </Button>
                    </DialogClose>
                    <Button
                        type="submit"
                        form={formId}
                        variant={
                            destructive ? 'destructive-confirm' : 'primary'
                        }
                        disabled={processing || !confirmed}
                    >
                        {processing && <Spinner />}
                        {confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
