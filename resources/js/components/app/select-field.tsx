import { useId } from 'react';
import InputError from '@/components/app/input-error';
import { Field, FieldDescription, FieldLabel } from '@/components/ui/field';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type SelectOption = {
    value: string;
    label: string;
    disabled?: boolean;
    testId?: string;
};

type SelectFieldProps = {
    id?: string;
    name: string;
    form?: string;
    label: string;
    description?: string;
    error?: string;
    placeholder?: string;
    defaultValue?: string;
    value?: string;
    onValueChange?: (value: string) => void;
    required?: boolean;
    disabled?: boolean;
    testId?: string;
    options: SelectOption[];
};

export function SelectField({
    id: suppliedId,
    name,
    form,
    label,
    description,
    error,
    placeholder,
    defaultValue,
    value,
    onValueChange,
    required,
    disabled,
    testId,
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
                form={form}
                defaultValue={defaultValue}
                value={value}
                onValueChange={onValueChange}
                required={required}
                disabled={disabled}
            >
                <SelectTrigger
                    id={id}
                    className="w-full"
                    aria-invalid={Boolean(error)}
                    aria-describedby={describedBy || undefined}
                    data-testid={testId}
                >
                    <SelectValue placeholder={placeholder} />
                </SelectTrigger>
                <SelectContent align="start">
                    <SelectGroup>
                        {options.map((option) => (
                            <SelectItem
                                key={option.value}
                                value={option.value}
                                disabled={option.disabled}
                                data-testid={option.testId}
                            >
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectGroup>
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
