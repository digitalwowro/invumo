import type { ReactNode } from 'react';
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

type ResponsiveDialogProps = {
    trigger: ReactNode;
    title: string;
    description?: string;
    children: ReactNode;
    actions?: ReactNode;
    size?: 'default' | 'wide';
    closeLabel?: string;
};

const sizeClasses = {
    default: 'sm:max-w-lg',
    wide: 'sm:max-w-2xl',
} as const;

export function ResponsiveDialog({
    trigger,
    title,
    description,
    children,
    actions,
    size = 'default',
    closeLabel,
}: ResponsiveDialogProps) {
    return (
        <Dialog>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent
                className={`${sizeClasses[size]} overflow-hidden`}
                closeLabel={closeLabel}
            >
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    {description && (
                        <DialogDescription>{description}</DialogDescription>
                    )}
                </DialogHeader>
                <div className="min-h-0 overflow-y-auto">{children}</div>
                {actions && <DialogFooter>{actions}</DialogFooter>}
            </DialogContent>
        </Dialog>
    );
}

type ConfirmationDialogProps = {
    triggerLabel: string;
    title: string;
    description: string;
    confirmLabel: string;
    cancelLabel: string;
    closeLabel?: string;
    onConfirm?: () => void;
    tone?: 'default' | 'destructive';
};

export function ConfirmationDialog({
    triggerLabel,
    title,
    description,
    confirmLabel,
    cancelLabel,
    closeLabel,
    onConfirm,
    tone = 'destructive',
}: ConfirmationDialogProps) {
    const triggerVariant = tone === 'destructive' ? 'destructive' : 'secondary';
    const confirmVariant =
        tone === 'destructive' ? 'destructive-confirm' : 'primary';

    return (
        <ResponsiveDialog
            trigger={
                <Button type="button" variant={triggerVariant}>
                    {triggerLabel}
                </Button>
            }
            title={title}
            description={description}
            closeLabel={closeLabel}
            actions={
                <>
                    <DialogClose asChild>
                        <Button type="button" variant="secondary">
                            {cancelLabel}
                        </Button>
                    </DialogClose>
                    <DialogClose asChild>
                        <Button
                            type="button"
                            variant={confirmVariant}
                            onClick={onConfirm}
                        >
                            {confirmLabel}
                        </Button>
                    </DialogClose>
                </>
            }
        >
            <span className="sr-only">{description}</span>
        </ResponsiveDialog>
    );
}

type DestructiveFormDialogProps = {
    triggerLabel: string;
    title: string;
    description: string;
    cancelLabel: string;
    confirmLabel: string;
    closeLabel: string;
    formId: string;
    processing: boolean;
    children: ReactNode;
    onDismiss?: () => void;
    triggerTestId?: string;
    confirmTestId?: string;
    triggerDisabled?: boolean;
};

export function DestructiveFormDialog({
    triggerLabel,
    title,
    description,
    cancelLabel,
    confirmLabel,
    closeLabel,
    formId,
    processing,
    children,
    onDismiss,
    triggerTestId,
    confirmTestId,
    triggerDisabled = false,
}: DestructiveFormDialogProps) {
    return (
        <Dialog
            onOpenChange={(open) => {
                if (!open) {
                    onDismiss?.();
                }
            }}
        >
            <DialogTrigger asChild>
                <Button
                    type="button"
                    variant="destructive"
                    data-testid={triggerTestId}
                    disabled={triggerDisabled}
                >
                    {triggerLabel}
                </Button>
            </DialogTrigger>
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
                        type="submit"
                        form={formId}
                        variant="destructive-confirm"
                        disabled={processing}
                        data-testid={confirmTestId}
                    >
                        {processing && <Spinner />}
                        {confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

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
                    variant="secondary"
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
