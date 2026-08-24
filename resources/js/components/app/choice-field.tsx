import { useId, useState } from 'react';
import InputError from '@/components/app/input-error';
import { Field, FieldDescription, FieldLabel } from '@/components/ui/field';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';

type ChoiceOption = {
    value: string;
    label: string;
};

type ChoiceFieldProps = {
    id?: string;
    name: string;
    label: string;
    description?: string;
    error?: string;
    defaultValue?: string;
    required?: boolean;
    options: ChoiceOption[];
};

export function ChoiceField({
    id: suppliedId,
    name,
    label,
    description,
    error,
    defaultValue = '',
    required,
    options,
}: ChoiceFieldProps) {
    const generatedId = useId();
    const id = suppliedId ?? generatedId;
    const labelId = `${id}-label`;
    const descriptionId = description ? `${id}-description` : undefined;
    const errorId = error ? `${id}-error` : undefined;
    const describedBy = [descriptionId, errorId].filter(Boolean).join(' ');
    const [value, setValue] = useState(defaultValue);

    return (
        <Field data-invalid={Boolean(error)}>
            <FieldLabel id={labelId}>{label}</FieldLabel>
            <ToggleGroup
                type="single"
                variant="outline"
                value={value}
                onValueChange={(nextValue) => {
                    if (nextValue || !required) {
                        setValue(nextValue);
                    }
                }}
                aria-labelledby={labelId}
                aria-describedby={describedBy || undefined}
                aria-invalid={Boolean(error)}
            >
                {options.map((option) => (
                    <ToggleGroupItem key={option.value} value={option.value}>
                        {option.label}
                    </ToggleGroupItem>
                ))}
            </ToggleGroup>
            <input type="hidden" name={name} value={value} />
            {description && (
                <FieldDescription id={descriptionId}>
                    {description}
                </FieldDescription>
            )}
            <InputError id={errorId} message={error} />
        </Field>
    );
}

export type { ChoiceOption };
