import type { ComponentProps, ReactNode } from 'react';
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

type DialogTriggerVariant = ComponentProps<typeof Button>['variant'];
type DialogTriggerSize = ComponentProps<typeof Button>['size'];

type FormDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    triggerLabel: string;
    title: string;
    description: string;
    cancelLabel: string;
    confirmLabel: string;
    closeLabel: string;
    formId: string;
    processing: boolean;
    children: ReactNode;
    triggerTestId?: string;
    confirmTestId?: string;
    triggerDisabled?: boolean;
    triggerDisabledDescription?: string;
    triggerVariant?: DialogTriggerVariant;
    triggerSize?: DialogTriggerSize;
    confirmDisabled?: boolean;
    confirmDisabledDescription?: string;
    onConfirm?: () => void;
};

export function FormDialog({
    open,
    onOpenChange,
    triggerLabel,
    title,
    description,
    cancelLabel,
    confirmLabel,
    closeLabel,
    formId,
    processing,
    children,
    triggerTestId,
    confirmTestId,
    triggerDisabled = false,
    triggerDisabledDescription,
    triggerVariant = 'secondary',
    triggerSize = 'default',
    confirmDisabled = false,
    confirmDisabledDescription,
    onConfirm,
}: FormDialogProps) {
    const triggerHelpId = `${formId}-trigger-help`;
    const confirmHelpId = `${formId}-confirm-help`;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogTrigger asChild>
                <Button
                    type="button"
                    variant={triggerVariant}
                    size={triggerSize}
                    data-testid={triggerTestId}
                    disabled={triggerDisabled}
                    aria-describedby={
                        triggerDisabledDescription ? triggerHelpId : undefined
                    }
                    title={triggerDisabledDescription}
                >
                    {triggerLabel}
                </Button>
            </DialogTrigger>
            {triggerDisabledDescription && (
                <span id={triggerHelpId} className="sr-only">
                    {triggerDisabledDescription}
                </span>
            )}
            <DialogContent closeLabel={closeLabel}>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                <div className="min-h-0 overflow-y-auto">{children}</div>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button type="button" variant="secondary">
                            {cancelLabel}
                        </Button>
                    </DialogClose>
                    <Button
                        type={onConfirm ? 'button' : 'submit'}
                        form={onConfirm ? undefined : formId}
                        disabled={processing || confirmDisabled}
                        aria-describedby={
                            confirmDisabledDescription
                                ? confirmHelpId
                                : undefined
                        }
                        title={confirmDisabledDescription}
                        data-testid={confirmTestId}
                        onClick={onConfirm}
                    >
                        {processing && <Spinner />}
                        {confirmLabel}
                    </Button>
                    {confirmDisabledDescription && (
                        <span id={confirmHelpId} className="sr-only">
                            {confirmDisabledDescription}
                        </span>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export type { DialogTriggerSize, DialogTriggerVariant };
