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
    existingFile?: { name: string; previewUrl?: string } | null;
    selectedPreviewUrl?: string;
    previewAlt?: string;
    onRemoveExisting?: () => void;
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
    existingFile,
    selectedPreviewUrl,
    previewAlt,
    onRemoveExisting,
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
    const displayedFile = value ?? existingFile;
    const previewUrl = value ? selectedPreviewUrl : existingFile?.previewUrl;

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

        if (value) {
            selectFile(null);
        } else {
            onRemoveExisting?.();
        }
    }

    const state = uploading
        ? 'uploading'
        : error
          ? 'error'
          : successMessage
            ? 'success'
            : displayedFile
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
                ) : displayedFile ? (
                    <Icon iconNode={FileImageIcon} className="size-6" />
                ) : (
                    <Icon iconNode={UploadCloudIcon} className="size-6" />
                )}

                <div className="space-y-1">
                    <p className="text-sm font-medium">
                        {uploading ? labels.uploading : labels.dropPrompt}
                    </p>
                    {displayedFile && (
                        <p className="text-sm text-muted-foreground">
                            {labels.selected}: {displayedFile.name}
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

                {previewUrl && previewAlt && (
                    <img
                        src={previewUrl}
                        alt={previewAlt}
                        className="max-h-24 max-w-full rounded-md border border-border bg-background object-contain p-2"
                    />
                )}

                <div className="flex flex-wrap justify-center gap-2">
                    <Button
                        type="button"
                        variant="secondary"
                        disabled={unavailable}
                        onClick={() => input.current?.click()}
                    >
                        {displayedFile ? labels.replace : labels.choose}
                    </Button>
                    {displayedFile && (value || onRemoveExisting) && (
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
