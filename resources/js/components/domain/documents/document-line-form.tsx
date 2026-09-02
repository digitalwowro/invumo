import { ArrowDown, ArrowUp, Trash2 } from 'lucide-react';
import { TextField } from '@/components/app/form-field';
import { SelectField } from '@/components/app/select-field';
import type { DocumentLineItemProps } from '@/components/domain/documents/document-line-card';
import {
    changeField,
    fieldRequestName,
    lineError,
} from '@/components/domain/documents/document-line-control';
import {
    applyLineTaxSelection,
    lineTaxOptions,
    lineTaxSelection,
} from '@/components/domain/documents/document-tax-options';
import { Button } from '@/components/ui/button';
import type { DocumentLineDraft, DocumentPeriodUnit } from '@/types/document';

export function DocumentLineFields(props: DocumentLineItemProps) {
    const options = (['NONE', 'MONTH', 'YEAR'] as DocumentPeriodUnit[]).map(
        (value) => ({ value, label: props.labels.periods[value] }),
    );
    const fields: Array<{
        name: keyof DocumentLineDraft;
        inputMode?: 'decimal';
        disabled?: boolean;
        maxLength?: number;
    }> = [
        { name: 'itemPrice', inputMode: 'decimal' },
        { name: 'quantity', inputMode: 'decimal' },
        { name: 'unit', maxLength: props.limits.unit },
        {
            name: 'periodQuantity',
            inputMode: 'decimal',
            disabled: props.line.periodUnit === 'NONE',
        },
        { name: 'discountPercentage', inputMode: 'decimal' },
    ];

    return (
        <>
            {fields.slice(0, 3).map((field) => (
                <LineTextField key={field.name} {...props} {...field} />
            ))}
            <SelectField
                name={`period-${props.line.key}`}
                label={props.labels.fields.period_unit}
                value={props.line.periodUnit}
                options={options}
                onValueChange={(value) =>
                    props.onChange({
                        ...props.line,
                        periodUnit: value as DocumentPeriodUnit,
                        periodQuantity:
                            value === 'NONE' ? '' : props.line.periodQuantity,
                    })
                }
            />
            {fields.slice(3).map((field) => (
                <LineTextField key={field.name} {...props} {...field} />
            ))}
            <SelectField
                name={`tax-${props.line.key}`}
                testId={`document-line-tax-${props.index}`}
                label={props.labels.tax_total}
                error={
                    lineError(props, 'tax_preset_id') ??
                    lineError(props, 'tax_percentage')
                }
                value={lineTaxSelection(props.line)}
                options={lineTaxOptions(
                    props.line,
                    props.inheritedTax ?? null,
                    props.taxPresetOptions,
                    {
                        noTax: props.labels.no_tax,
                    },
                )}
                onValueChange={(value) =>
                    props.onChange(
                        applyLineTaxSelection(
                            props.line,
                            value,
                            props.inheritedTax ?? null,
                            props.taxPresetOptions,
                        ),
                    )
                }
            />
        </>
    );
}

function LineTextField(
    props: DocumentLineItemProps & {
        name: keyof DocumentLineDraft;
        inputMode?: 'decimal';
        disabled?: boolean;
        maxLength?: number;
    },
) {
    const fieldName = fieldRequestName(props.name);

    return (
        <TextField
            label={props.labels.fields[fieldName]}
            error={lineError(props, fieldName)}
            input={{
                inputMode: props.inputMode,
                disabled: props.disabled,
                maxLength: props.maxLength,
                value: String(props.line[props.name] ?? ''),
                onChange: (event) =>
                    changeField(props, props.name, event.target.value),
            }}
        />
    );
}

export function DocumentLineOrderActions(props: DocumentLineItemProps) {
    return (
        <>
            <Button
                type="button"
                variant="secondary"
                size="icon-xs"
                aria-label={props.labels.move_up}
                disabled={props.index === 0}
                onClick={() => props.onMove(-1)}
            >
                <ArrowUp aria-hidden="true" />
            </Button>
            <Button
                type="button"
                variant="secondary"
                size="icon-xs"
                aria-label={props.labels.move_down}
                disabled={props.index === props.count - 1}
                onClick={() => props.onMove(1)}
            >
                <ArrowDown aria-hidden="true" />
            </Button>
            <DocumentLineRemoveAction {...props} />
        </>
    );
}

export function DocumentLineRemoveAction(props: DocumentLineItemProps) {
    return (
        <Button
            type="button"
            variant="destructive"
            size="icon-xs"
            aria-label={props.labels.remove_line}
            onClick={props.onRemove}
        >
            <Trash2 aria-hidden="true" />
        </Button>
    );
}
