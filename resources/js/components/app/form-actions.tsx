import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

type FormActionsProps = {
    children: ReactNode;
    align?: 'start' | 'end' | 'stretch';
    separated?: boolean;
};

const alignmentClasses = {
    start: 'justify-start',
    end: 'justify-end',
    stretch: '[&>*]:w-full',
} as const;

export function FormActions({
    children,
    align = 'end',
    separated = false,
}: FormActionsProps) {
    return (
        <div
            data-slot="form-actions"
            className={`flex flex-wrap items-center gap-2 ${alignmentClasses[align]} ${separated ? 'border-t border-divider pt-6' : ''}`}
        >
            {children}
        </div>
    );
}

type SubmitButtonProps = {
    children: ReactNode;
    processing?: boolean;
    disabled?: boolean;
    testId?: string;
    form?: string;
};

export function SubmitButton({
    children,
    processing = false,
    disabled = false,
    testId,
    form,
}: SubmitButtonProps) {
    return (
        <Button
            type="submit"
            form={form}
            disabled={disabled || processing}
            data-test={testId}
        >
            {processing && <Spinner />}
            {children}
        </Button>
    );
}

type SaveButtonProps = SubmitButtonProps & {
    dirty: boolean;
};

export function SaveButton({ dirty, disabled, ...props }: SaveButtonProps) {
    return <SubmitButton {...props} disabled={disabled || !dirty} />;
}
