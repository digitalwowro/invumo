import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import type { DocumentResetLabels } from '@/types/document';

type Props = {
    dirty: boolean;
    processing: boolean;
    form: string;
    mode: 'discard' | 'clear';
    labels: DocumentResetLabels;
};

export function DiscardChangesDialog(props: Props) {
    const clear = props.mode === 'clear';
    const triggerLabel = clear
        ? props.labels.clear_draft
        : props.labels.discard_changes;

    return (
        <AlertDialog>
            <AlertDialogTrigger asChild>
                <Button
                    type="button"
                    variant="secondary"
                    disabled={!props.dirty || props.processing}
                    data-test={
                        clear
                            ? 'clear-document-draft'
                            : 'discard-document-changes'
                    }
                >
                    {triggerLabel}
                </Button>
            </AlertDialogTrigger>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>
                        {clear
                            ? props.labels.clear_draft_title
                            : props.labels.discard_changes_title}
                    </AlertDialogTitle>
                    <AlertDialogDescription>
                        {clear
                            ? props.labels.clear_draft_description
                            : props.labels.discard_changes_description}
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>
                        {props.labels.keep_editing}
                    </AlertDialogCancel>
                    <AlertDialogAction
                        type="reset"
                        form={props.form}
                        data-test="confirm-document-reset"
                    >
                        {triggerLabel}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
