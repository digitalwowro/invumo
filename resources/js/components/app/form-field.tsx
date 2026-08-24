import { useId } from 'react';
import type { ComponentProps, ReactNode } from 'react';
import InputError from '@/components/app/input-error';
import PasswordInput from '@/components/app/password-input';
import { Checkbox } from '@/components/ui/checkbox';
import { Field, FieldDescription, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';

type SafeInputProps = Omit<
    ComponentProps<typeof Input>,
    'className' | 'style' | 'id'
>;

type BaseFieldProps = {
    id?: string;
    label: string;
    description?: string;
    error?: string;
    inheritedCaption?: string;
    labelAction?: ReactNode;
};

type TextFieldProps = BaseFieldProps & {
    input: SafeInputProps;
};

export function TextField({
    id: suppliedId,
    label,
    description,
    error,
    inheritedCaption,
    labelAction,
    input,
}: TextFieldProps) {
    const generatedId = useId();
    const id = suppliedId ?? generatedId;
    const descriptionId = description ? `${id}-description` : undefined;
    const inheritedId = inheritedCaption ? `${id}-inherited` : undefined;
    const errorId = error ? `${id}-error` : undefined;
    const describedBy = [descriptionId, inheritedId, errorId]
        .filter(Boolean)
        .join(' ');

    return (
        <Field data-invalid={Boolean(error)} data-disabled={input.disabled}>
            <div className="flex items-center justify-between gap-3">
                <FieldLabel htmlFor={id}>{label}</FieldLabel>
                {labelAction}
            </div>
            <Input
                {...input}
                id={id}
                className={inheritedCaption ? 'bg-surface-inset' : undefined}
                aria-invalid={Boolean(error)}
                aria-describedby={describedBy || undefined}
            />
            {description && (
                <FieldDescription id={descriptionId}>
                    {description}
                </FieldDescription>
            )}
            {inheritedCaption && (
                <FieldDescription id={inheritedId}>
                    {inheritedCaption}
                </FieldDescription>
            )}
            <InputError id={errorId} message={error} />
        </Field>
    );
}

type PasswordFieldProps = BaseFieldProps & {
    input: Omit<
        ComponentProps<typeof PasswordInput>,
        'className' | 'style' | 'id'
    >;
};

export function PasswordField({
    id: suppliedId,
    label,
    description,
    error,
    labelAction,
    input,
}: PasswordFieldProps) {
    const generatedId = useId();
    const id = suppliedId ?? generatedId;
    const descriptionId = description ? `${id}-description` : undefined;
    const errorId = error ? `${id}-error` : undefined;
    const describedBy = [descriptionId, errorId].filter(Boolean).join(' ');

    return (
        <Field data-invalid={Boolean(error)} data-disabled={input.disabled}>
            <div className="flex items-center justify-between gap-3">
                <FieldLabel htmlFor={id}>{label}</FieldLabel>
                {labelAction}
            </div>
            <PasswordInput
                {...input}
                id={id}
                aria-invalid={Boolean(error)}
                aria-describedby={describedBy || undefined}
            />
            {description && (
                <FieldDescription id={descriptionId}>
                    {description}
                </FieldDescription>
            )}
            <InputError id={errorId} message={error} />
        </Field>
    );
}

type CheckboxFieldProps = {
    id?: string;
    label: string;
    description?: string;
    error?: string;
    checkbox: Omit<
        ComponentProps<typeof Checkbox>,
        'className' | 'style' | 'id'
    >;
};

export function CheckboxField({
    id: suppliedId,
    label,
    description,
    error,
    checkbox,
}: CheckboxFieldProps) {
    const generatedId = useId();
    const id = suppliedId ?? generatedId;
    const descriptionId = description ? `${id}-description` : undefined;
    const errorId = error ? `${id}-error` : undefined;
    const describedBy = [descriptionId, errorId].filter(Boolean).join(' ');

    return (
        <Field
            orientation="horizontal"
            data-disabled={checkbox.disabled}
            data-invalid={Boolean(error)}
        >
            <Checkbox
                {...checkbox}
                id={id}
                aria-invalid={Boolean(error)}
                aria-describedby={describedBy || undefined}
            />
            <div className="grid gap-1">
                <FieldLabel htmlFor={id}>{label}</FieldLabel>
                {description && (
                    <FieldDescription id={descriptionId}>
                        {description}
                    </FieldDescription>
                )}
                <InputError id={errorId} message={error} />
            </div>
        </Field>
    );
}
