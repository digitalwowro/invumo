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
import type { DependencyGuard } from '@/types/dependency-guard';

type Props = {
    triggerLabel: string;
    title: string;
    description: string;
    confirmLabel: string;
    cancelLabel: string;
    closeLabel: string;
    warningTitle: string;
    guard: DependencyGuard;
    onConfirm: () => void;
    tone?: 'default' | 'destructive';
};

export function GuardedActionDialog({
    triggerLabel,
    title,
    description,
    confirmLabel,
    cancelLabel,
    closeLabel,
    warningTitle,
    guard,
    onConfirm,
    tone = 'destructive',
}: Props) {
    const triggerVariant = tone === 'destructive' ? 'destructive' : 'secondary';
    const confirmVariant =
        tone === 'destructive' ? 'destructive-confirm' : 'primary';

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button type="button" variant={triggerVariant}>
                    {triggerLabel}
                </Button>
            </DialogTrigger>
            <DialogContent closeLabel={closeLabel}>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                {guard.blocked && guard.description && (
                    <SystemMessage
                        title={warningTitle}
                        description={guard.description}
                        tone="warning"
                    />
                )}
                <DialogFooter>
                    <DialogClose asChild>
                        <Button type="button" variant="secondary">
                            {cancelLabel}
                        </Button>
                    </DialogClose>
                    {guard.blocked ? (
                        <Button type="button" variant={confirmVariant} disabled>
                            {confirmLabel}
                        </Button>
                    ) : (
                        <DialogClose asChild>
                            <Button
                                type="button"
                                variant={confirmVariant}
                                onClick={onConfirm}
                            >
                                {confirmLabel}
                            </Button>
                        </DialogClose>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
