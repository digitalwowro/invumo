import { Form } from '@inertiajs/react';
import { RotateCcw } from 'lucide-react';
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

type RetryLabels = {
    retry: string;
    retry_title: string;
    retry_warning: string;
    retry_confirm: string;
    retry_cancel: string;
};

type Props = {
    url: string;
    labels: RetryLabels;
    closeLabel: string;
};

export function DocumentDeliveryRetryDialog({
    url,
    labels,
    closeLabel,
}: Props) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button
                    type="button"
                    size="sm"
                    variant="secondary"
                    data-testid="document-delivery-retry"
                >
                    <RotateCcw data-icon="inline-start" />
                    {labels.retry}
                </Button>
            </DialogTrigger>
            <DialogContent closeLabel={closeLabel}>
                <DialogHeader>
                    <DialogTitle>{labels.retry_title}</DialogTitle>
                    <DialogDescription>
                        {labels.retry_warning}
                    </DialogDescription>
                </DialogHeader>
                <Form action={url} method="post">
                    {({ processing }) => (
                        <DialogFooter>
                            <input type="hidden" name="confirmed" value="1" />
                            <DialogClose asChild>
                                <Button
                                    type="button"
                                    variant="secondary"
                                    data-testid="document-delivery-retry-cancel"
                                >
                                    {labels.retry_cancel}
                                </Button>
                            </DialogClose>
                            <Button type="submit" disabled={processing}>
                                {processing && <Spinner />}
                                {labels.retry_confirm}
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
