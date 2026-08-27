import { useId } from 'react';
import type { FormEvent } from 'react';
import { CheckboxField, TextField } from '@/components/app/form-field';
import { Stack } from '@/components/app/layout';
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

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    triggerLabel: string;
    title: string;
    description: string;
    cancelLabel: string;
    confirmLabel: string;
    closeLabel: string;
    strongConfirmation?: {
        expectedValue: string;
        value: string;
        valueLabel: string;
        valueDescription: string;
        valueError?: string;
        acknowledged: boolean;
        acknowledgmentLabel: string;
        onValueChange: (value: string) => void;
        onAcknowledgmentChange: (checked: boolean) => void;
    };
    generalError?: string;
    processing: boolean;
    onConfirm: () => void;
};

export function DestructiveActionDialog(props: Props) {
    const formId = useId();
    const strong = props.strongConfirmation;
    const ready =
        strong === undefined ||
        (strong.acknowledged && strong.value === strong.expectedValue);

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (ready) {
            props.onConfirm();
        }
    };

    return (
        <Dialog open={props.open} onOpenChange={props.onOpenChange}>
            <DialogTrigger asChild>
                <Button type="button" variant="destructive">
                    {props.triggerLabel}
                </Button>
            </DialogTrigger>
            <DialogContent closeLabel={props.closeLabel}>
                <DialogHeader>
                    <DialogTitle>{props.title}</DialogTitle>
                    <DialogDescription>{props.description}</DialogDescription>
                </DialogHeader>
                <form id={formId} onSubmit={submit}>
                    <Stack gap="lg">
                        {strong && (
                            <>
                                <SystemMessage
                                    title={strong.expectedValue}
                                    tone="warning"
                                />
                                <TextField
                                    label={strong.valueLabel}
                                    description={strong.valueDescription}
                                    error={strong.valueError}
                                    input={{
                                        value: strong.value,
                                        maxLength: 131,
                                        autoComplete: 'off',
                                        onChange: (event) =>
                                            strong.onValueChange(
                                                event.target.value,
                                            ),
                                    }}
                                />
                                <CheckboxField
                                    label={strong.acknowledgmentLabel}
                                    checkbox={{
                                        checked: strong.acknowledged,
                                        onCheckedChange: (checked) =>
                                            strong.onAcknowledgmentChange(
                                                checked === true,
                                            ),
                                    }}
                                />
                            </>
                        )}
                        {props.generalError && (
                            <SystemMessage
                                title={props.generalError}
                                tone="error"
                            />
                        )}
                    </Stack>
                </form>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button type="button" variant="secondary">
                            {props.cancelLabel}
                        </Button>
                    </DialogClose>
                    <Button
                        type="submit"
                        form={formId}
                        variant="destructive-confirm"
                        disabled={props.processing || !ready}
                        data-testid="destructive-action-confirm"
                    >
                        {props.processing && <Spinner />}
                        {props.confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
