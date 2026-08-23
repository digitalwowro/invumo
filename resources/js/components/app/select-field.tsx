import { useId } from 'react';
import InputError from '@/components/app/input-error';
import { Field, FieldDescription, FieldLabel } from '@/components/ui/field';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type SelectOption = {
    value: string;
    label: string;
    disabled?: boolean;
};

type SelectFieldProps = {
    id?: string;
    name: string;
    label: string;
    description?: string;
    error?: string;
    placeholder?: string;
    defaultValue?: string;
    required?: boolean;
    disabled?: boolean;
    options: SelectOption[];
};

export function SelectField({
    id: suppliedId,
    name,
    label,
    description,
    error,
    placeholder,
    defaultValue,
    required,
    disabled,
    options,
}: SelectFieldProps) {
    const generatedId = useId();
    const id = suppliedId ?? generatedId;
    const descriptionId = description ? `${id}-description` : undefined;
    const errorId = error ? `${id}-error` : undefined;
    const describedBy = [descriptionId, errorId].filter(Boolean).join(' ');

    return (
        <Field data-invalid={Boolean(error)} data-disabled={disabled}>
            <FieldLabel htmlFor={id}>{label}</FieldLabel>
            <Select
                name={name}
                defaultValue={defaultValue}
                required={required}
                disabled={disabled}
            >
                <SelectTrigger
                    id={id}
                    className="w-full"
                    aria-invalid={Boolean(error)}
                    aria-describedby={describedBy || undefined}
                >
                    <SelectValue placeholder={placeholder} />
                </SelectTrigger>
                <SelectContent align="start">
                    {options.map((option) => (
                        <SelectItem
                            key={option.value}
                            value={option.value}
                            disabled={option.disabled}
                        >
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            {description && (
                <FieldDescription id={descriptionId}>
                    {description}
                </FieldDescription>
            )}
            <InputError id={errorId} message={error} />
        </Field>
    );
}

export type { SelectOption };
