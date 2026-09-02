import { useId } from 'react';
import InputError from '@/components/app/input-error';
import type { DocumentLineItemProps } from '@/components/domain/documents/document-line-card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import type { DocumentLineDraft } from '@/types/document';

export function changeTaxMode(props: DocumentLineItemProps, value: string) {
    props.onChange({
        ...props.line,
        taxMode: value as DocumentLineDraft['taxMode'],
        taxPresetId: null,
        taxName:
            value === 'INHERIT_CUSTOMER'
                ? (props.inheritedTax?.name ?? '')
                : value === 'NONE'
                  ? ''
                  : props.line.taxName,
        taxPercentage:
            value === 'INHERIT_CUSTOMER'
                ? (props.inheritedTax?.percentage ?? '0')
                : value === 'NONE'
                  ? '0'
                  : props.line.taxPercentage,
    });
}

export function lineError(props: DocumentLineItemProps, name: string) {
    return props.errors[`lines.${props.index}.${name}`];
}

export function changeField(
    props: DocumentLineItemProps,
    name: keyof DocumentLineDraft,
    value: string,
) {
    props.onChange({ ...props.line, [name]: value });
}

export function lineDescriptionLimit(props: DocumentLineItemProps) {
    const name = props.line.productServiceName ?? '';

    return Math.max(
        0,
        props.limits.description - name.length - (name.length > 0 ? 1 : 0),
    );
}

export function changeProductServiceName(
    props: DocumentLineItemProps,
    value: string,
) {
    const descriptionLimit = Math.max(
        0,
        props.limits.description - value.length - (value.length > 0 ? 1 : 0),
    );

    props.onChange({
        ...props.line,
        productServiceId: null,
        productServiceName: value,
        description: props.line.description.slice(0, descriptionLimit),
    });
}

export function taxFieldsDisabled(line: DocumentLineDraft) {
    return line.taxMode !== undefined && line.taxMode !== 'EXPLICIT';
}

export function fieldRequestName(value: string) {
    return value.replace(/[A-Z]/g, (letter) => `_${letter.toLowerCase()}`);
}

export function CompactInput(props: {
    label: string;
    value: string;
    inputMode?: 'decimal';
    maxLength?: number;
    disabled?: boolean;
    quiet?: boolean;
    error?: string;
    onChange: (value: string) => void;
}) {
    const id = useId();

    return (
        <div>
            <label htmlFor={id} className="sr-only">
                {props.label}
            </label>
            <Input
                id={id}
                name={props.label}
                className={cn(
                    'h-8 px-2 text-xs',
                    props.inputMode === 'decimal' &&
                        'font-data text-right tabular-nums',
                    props.quiet &&
                        'border-transparent bg-transparent shadow-none hover:border-input hover:bg-background disabled:bg-transparent',
                )}
                value={props.value}
                inputMode={props.inputMode}
                maxLength={props.maxLength}
                disabled={props.disabled}
                aria-invalid={Boolean(props.error)}
                onChange={(event) => props.onChange(event.target.value)}
            />
            <InputError message={props.error} className="mt-1 text-xs" />
        </div>
    );
}

export function CompactReadOnlyInput(props: { label: string; value: string }) {
    const id = useId();

    return (
        <div>
            <label htmlFor={id} className="sr-only">
                {props.label}
            </label>
            <Input
                id={id}
                name={props.label}
                className="h-8 px-2 text-xs"
                value={props.value}
                readOnly
            />
        </div>
    );
}

export function CompactTextarea(props: {
    label: string;
    value: string;
    maxLength: number;
    error?: string;
    onChange: (value: string) => void;
}) {
    const id = useId();

    return (
        <div>
            <label htmlFor={id} className="sr-only">
                {props.label}
            </label>
            <Textarea
                id={id}
                name={props.label}
                className="min-h-16 resize-none px-2 py-1.5 text-xs"
                value={props.value}
                maxLength={props.maxLength}
                rows={2}
                aria-invalid={Boolean(props.error)}
                onChange={(event) => props.onChange(event.target.value)}
            />
            <InputError message={props.error} className="mt-1 text-xs" />
        </div>
    );
}

export function CompactSelect(props: {
    label: string;
    value: string;
    options: Array<{ value: string; label: string }>;
    error?: string;
    testId?: string;
    quiet?: boolean;
    onValueChange: (value: string) => void;
}) {
    const id = useId();

    return (
        <div>
            <label htmlFor={id} className="sr-only">
                {props.label}
            </label>
            <Select
                name={props.label}
                value={props.value}
                onValueChange={props.onValueChange}
            >
                <SelectTrigger
                    id={id}
                    size="sm"
                    className={cn(
                        'w-full px-2 text-xs',
                        props.quiet &&
                            'border-transparent bg-transparent shadow-none hover:border-input hover:bg-background',
                    )}
                    aria-invalid={Boolean(props.error)}
                    data-testid={props.testId}
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent align="start">
                    <SelectGroup>
                        {props.options.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectGroup>
                </SelectContent>
            </Select>
            <InputError message={props.error} className="mt-1 text-xs" />
        </div>
    );
}
