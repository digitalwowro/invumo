import { ArrowDown, ArrowUp, Search, Trash2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { TextareaField, TextField } from '@/components/app/form-field';
import { Grid } from '@/components/app/layout';
import { SelectField } from '@/components/app/select-field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { LineAmounts } from '@/lib/money/line-calculation';
import type {
    DocumentLineDraft,
    DocumentLineLabels,
    DocumentLineLimits,
    DocumentPeriodUnit,
    DocumentTaxDefault,
} from '@/types/document';

type Props = {
    line: DocumentLineDraft;
    amounts?: LineAmounts | null;
    index: number;
    count: number;
    limits: DocumentLineLimits;
    labels: DocumentLineLabels;
    errors: Record<string, string>;
    sourceNotice?: ReactNode;
    inheritedTax?: DocumentTaxDefault | null;
    onSelectProduct: () => void;
    onChange: (line: DocumentLineDraft) => void;
    onMove: (direction: -1 | 1) => void;
    onRemove: () => void;
};

export function DocumentLineCard(props: Props) {
    const field = (name: keyof DocumentLineDraft, value: string) =>
        props.onChange({ ...props.line, [name]: value });
    const error = (name: string) =>
        props.errors[`lines.${props.index}.${name}`];
    const options = (['NONE', 'MONTH', 'YEAR'] as DocumentPeriodUnit[]).map(
        (value) => ({ value, label: props.labels.periods[value] }),
    );

    return (
        <article
            className="flex flex-col gap-5 border-b border-divider px-5 py-5 sm:px-6"
            data-test={`document-line-${props.index}`}
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <span
                        className="font-data text-xs font-bold tracking-[0.09em] uppercase"
                        aria-label={`${props.labels.line} ${props.index + 1}`}
                    >
                        {props.labels.line} {props.index + 1}
                    </span>
                    {typeof props.line.isCustomized === 'boolean' && (
                        <Badge
                            variant={
                                props.line.isCustomized ? 'quiet' : 'muted'
                            }
                        >
                            {props.line.isCustomized
                                ? props.labels.provenance_customized
                                : props.labels.provenance_default}
                        </Badge>
                    )}
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        variant="secondary"
                        size="compact"
                        data-testid={`document-product-select-${props.index}`}
                        onClick={props.onSelectProduct}
                    >
                        <Search data-icon="inline-start" aria-hidden="true" />
                        {props.labels.select_product}
                    </Button>
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
                    <Button
                        type="button"
                        variant="destructive"
                        size="icon-xs"
                        aria-label={props.labels.remove_line}
                        onClick={props.onRemove}
                    >
                        <Trash2 aria-hidden="true" />
                    </Button>
                </div>
            </div>
            <TextField
                label={props.labels.product_or_service}
                input={{
                    readOnly: true,
                    value: props.line.productServiceName ?? '',
                }}
            />
            {props.sourceNotice}
            {props.line.taxMode && props.labels.tax_modes && (
                <SelectField
                    name={`tax-mode-${props.line.key}`}
                    label={props.labels.fields.tax_mode}
                    error={error('tax_mode')}
                    value={props.line.taxMode}
                    options={Object.entries(props.labels.tax_modes).map(
                        ([value, label]) => ({ value, label }),
                    )}
                    onValueChange={(value) =>
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
                        })
                    }
                />
            )}
            <TextareaField
                label={props.labels.fields.description}
                error={error('description')}
                textarea={{
                    value: props.line.description,
                    maxLength: props.limits.description,
                    rows: 2,
                    onChange: (event) =>
                        field('description', event.target.value),
                }}
            />
            <Grid columns={4} gap="lg">
                <TextField
                    label={props.labels.fields.item_price}
                    error={error('item_price')}
                    input={{
                        inputMode: 'decimal',
                        value: props.line.itemPrice,
                        onChange: (event) =>
                            field('itemPrice', event.target.value),
                    }}
                />
                <TextField
                    label={props.labels.fields.quantity}
                    error={error('quantity')}
                    input={{
                        inputMode: 'decimal',
                        value: props.line.quantity,
                        onChange: (event) =>
                            field('quantity', event.target.value),
                    }}
                />
                <TextField
                    label={props.labels.fields.unit}
                    error={error('unit')}
                    input={{
                        value: props.line.unit,
                        maxLength: props.limits.unit,
                        onChange: (event) => field('unit', event.target.value),
                    }}
                />
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
                                value === 'NONE'
                                    ? ''
                                    : props.line.periodQuantity,
                        })
                    }
                />
            </Grid>
            <Grid columns={4} gap="lg">
                <TextField
                    label={props.labels.fields.period_quantity}
                    error={error('period_quantity')}
                    input={{
                        inputMode: 'decimal',
                        disabled: props.line.periodUnit === 'NONE',
                        value: props.line.periodQuantity,
                        onChange: (event) =>
                            field('periodQuantity', event.target.value),
                    }}
                />
                <TextField
                    label={props.labels.fields.discount_percentage}
                    error={error('discount_percentage')}
                    input={{
                        inputMode: 'decimal',
                        value: props.line.discountPercentage,
                        onChange: (event) =>
                            field('discountPercentage', event.target.value),
                    }}
                />
                <TextField
                    label={props.labels.fields.tax_name}
                    error={error('tax_name')}
                    input={{
                        value: props.line.taxName,
                        disabled:
                            props.line.taxMode !== undefined &&
                            props.line.taxMode !== 'EXPLICIT',
                        maxLength: props.limits.taxName,
                        onChange: (event) =>
                            field('taxName', event.target.value),
                    }}
                />
                <TextField
                    label={props.labels.fields.tax_percentage}
                    error={error('tax_percentage')}
                    input={{
                        inputMode: 'decimal',
                        value: props.line.taxPercentage,
                        disabled:
                            props.line.taxMode !== undefined &&
                            props.line.taxMode !== 'EXPLICIT',
                        onChange: (event) =>
                            field('taxPercentage', event.target.value),
                    }}
                />
            </Grid>
            <div className="font-data flex flex-wrap items-center justify-end gap-x-4 gap-y-1 text-sm text-foreground-muted tabular-nums">
                {props.amounts && (
                    <span>
                        {props.labels.subtotal}: {props.amounts.grand_subtotal}{' '}
                        · {props.labels.tax_total}: {props.amounts.tax_amount}
                    </span>
                )}
                <span className="font-bold text-foreground">
                    {props.labels.line_total}:{' '}
                    {props.line.finalLineTotal ?? props.labels.incomplete}
                </span>
            </div>
        </article>
    );
}
