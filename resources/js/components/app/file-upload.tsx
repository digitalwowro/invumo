import {
    CheckCircle2Icon,
    FileImageIcon,
    UploadCloudIcon,
    XIcon,
} from 'lucide-react';
import { useRef, useState } from 'react';
import type { ChangeEvent, DragEvent } from 'react';
import { Button } from '@/components/ui/button';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldLabel,
} from '@/components/ui/field';
import { Icon } from '@/components/ui/icon';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

type FileUploadLabels = {
    dropPrompt: string;
    choose: string;
    replace: string;
    remove: string;
    selected: string;
    uploading: string;
};

type FileUploadProps = {
    id: string;
    name: string;
    label: string;
    labels: FileUploadLabels;
    value: File | null;
    onChange: (file: File | null) => void;
    accept?: string;
    description?: string;
    error?: string;
    successMessage?: string;
    uploading?: boolean;
    disabled?: boolean;
};

export function FileUpload({
    id,
    name,
    label,
    labels,
    value,
    onChange,
    accept,
    description,
    error,
    successMessage,
    uploading = false,
    disabled = false,
}: FileUploadProps) {
    const input = useRef<HTMLInputElement>(null);
    const [dragging, setDragging] = useState(false);
    const unavailable = disabled || uploading;

    function selectFile(file: File | null) {
        if (!unavailable) {
            onChange(file);
        }
    }

    function handleInput(event: ChangeEvent<HTMLInputElement>) {
        selectFile(event.target.files?.item(0) ?? null);
    }

    function handleDrop(event: DragEvent<HTMLDivElement>) {
        event.preventDefault();
        setDragging(false);
        selectFile(event.dataTransfer.files.item(0));
    }

    function clearFile() {
        if (input.current) {
            input.current.value = '';
        }

        selectFile(null);
    }

    const state = uploading
        ? 'uploading'
        : error
          ? 'error'
          : successMessage
            ? 'success'
            : value
              ? 'selected'
              : 'idle';

    return (
        <Field data-invalid={Boolean(error)} data-disabled={unavailable}>
            <FieldLabel htmlFor={id}>{label}</FieldLabel>
            {description && (
                <FieldDescription id={`${id}-description`}>
                    {description}
                </FieldDescription>
            )}
            <div
                data-slot="file-upload"
                data-state={dragging ? 'dragging' : state}
                onDragEnter={(event) => {
                    event.preventDefault();

                    if (!unavailable) {
                        setDragging(true);
                    }
                }}
                onDragOver={(event) => event.preventDefault()}
                onDragLeave={(event) => {
                    if (
                        !event.currentTarget.contains(
                            event.relatedTarget as Node,
                        )
                    ) {
                        setDragging(false);
                    }
                }}
                onDrop={handleDrop}
                className={cn(
                    'flex min-h-40 flex-col items-center justify-center gap-3 rounded-lg border border-dashed border-border-strong bg-surface-subtle p-6 text-center transition-colors',
                    'data-[state=dragging]:border-primary data-[state=dragging]:bg-selection',
                    'data-[state=error]:border-danger-text',
                    unavailable && 'opacity-60',
                )}
            >
                <Input
                    ref={input}
                    id={id}
                    name={name}
                    type="file"
                    accept={accept}
                    disabled={unavailable}
                    aria-describedby={
                        description ? `${id}-description` : undefined
                    }
                    aria-invalid={Boolean(error)}
                    onChange={handleInput}
                    className="sr-only"
                />

                {uploading ? (
                    <Spinner className="size-6" />
                ) : value ? (
                    <Icon iconNode={FileImageIcon} className="size-6" />
                ) : (
                    <Icon iconNode={UploadCloudIcon} className="size-6" />
                )}

                <div className="space-y-1">
                    <p className="text-sm font-medium">
                        {uploading ? labels.uploading : labels.dropPrompt}
                    </p>
                    {value && (
                        <p className="text-sm text-muted-foreground">
                            {labels.selected}: {value.name}
                        </p>
                    )}
                    {successMessage && !uploading && (
                        <p
                            className="inline-flex items-center gap-1 text-sm text-money-text"
                            role="status"
                        >
                            <Icon iconNode={CheckCircle2Icon} />
                            {successMessage}
                        </p>
                    )}
                </div>

                <div className="flex flex-wrap justify-center gap-2">
                    <Button
                        type="button"
                        variant="secondary"
                        disabled={unavailable}
                        onClick={() => input.current?.click()}
                    >
                        {value ? labels.replace : labels.choose}
                    </Button>
                    {value && (
                        <Button
                            type="button"
                            variant="ghost"
                            disabled={unavailable}
                            onClick={clearFile}
                        >
                            <Icon iconNode={XIcon} />
                            {labels.remove}
                        </Button>
                    )}
                </div>
            </div>
            <FieldError>{error}</FieldError>
        </Field>
    );
}

export type { FileUploadLabels, FileUploadProps };
