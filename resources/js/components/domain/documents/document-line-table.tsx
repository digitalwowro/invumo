import type { DocumentLineItemProps } from '@/components/domain/documents/document-line-card';
import {
    changeField,
    changeProductServiceName,
    CompactInput,
    CompactSelect,
    fieldRequestName,
    isCompleteDocumentLine,
    lineDescriptionLimit,
    lineError,
} from '@/components/domain/documents/document-line-control';
import { DocumentLineRemoveAction } from '@/components/domain/documents/document-line-form';
import { DocumentProductCombobox } from '@/components/domain/documents/document-product-combobox';
import {
    applyLineTaxSelection,
    lineTaxOptions,
    lineTaxSelection,
} from '@/components/domain/documents/document-tax-options';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import type {
    DocumentLineDraft,
    DocumentLineLabels,
    DocumentPeriodUnit,
} from '@/types/document';
type Props = {
    rows: DocumentLineItemProps[];
    labels: DocumentLineLabels;
    ariaLabel: string;
};
export function DocumentLineTable({ rows, labels, ariaLabel }: Props) {
    const headings = [
        [labels.line, 'px-2 text-center'],
        [labels.product_or_service, 'border-l border-rule px-2'],
        [labels.fields.item_price, 'border-l border-divider px-2 text-right'],
        [
            labels.fields.quantity_short ?? labels.fields.quantity,
            'border-l border-rule px-2 text-center',
        ],
        [labels.fields.unit, 'border-l border-rule px-2 text-center'],
        [labels.fields.period_unit, 'border-l border-rule px-2 text-center'],
        [
            labels.fields.period_quantity_short ??
                labels.fields.period_quantity,
            'border-l border-rule px-2 text-center',
        ],
        [
            labels.fields.discount_percentage_short ??
                labels.fields.discount_percentage,
            'border-l border-rule px-2 text-right',
        ],
        [labels.tax_total, 'border-l border-rule px-2'],
        [labels.line_total, 'border-l border-divider px-2 text-right'],
    ] as const;

    return (
        <Table aria-label={ariaLabel} className="min-w-[1120px] table-fixed">
            <colgroup>
                <col className="w-[44px]" />
                <col className="w-[268px]" />
                <col className="w-[110px]" />
                <col className="w-[60px]" />
                <col className="w-[68px]" />
                <col className="w-[108px]" />
                <col className="w-[72px]" />
                <col className="w-[80px]" />
                <col className="w-[122px]" />
                <col className="w-[148px]" />
                <col className="w-[40px]" />
            </colgroup>
            <TableHeader>
                <TableRow>
                    {headings.map(([label, className]) => (
                        <TableHead key={label} className={className}>
                            {label}
                        </TableHead>
                    ))}
                    <TableHead className="border-l border-rule px-2">
                        <span className="sr-only">{labels.remove_line}</span>
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {rows.map((row) => (
                    <DocumentLineTableRow key={row.line.key} {...row} />
                ))}
            </TableBody>
        </Table>
    );
}
function DocumentLineTableRow(props: DocumentLineItemProps) {
    const quiet = isCompleteDocumentLine(props.line);
    const periodOptions = (
        ['NONE', 'MONTH', 'YEAR'] as DocumentPeriodUnit[]
    ).map((value) => ({ value, label: props.labels.periods[value] }));

    return (
        <TableRow data-test={`document-line-${props.index}`}>
            <TableCell className="px-2 text-center align-top">
                <span className="font-data text-xs font-bold">
                    {props.index + 1}
                </span>
            </TableCell>
            <TableCell className="border-l border-rule px-2 align-top transition-colors focus-within:bg-surface-subtle hover:bg-surface-subtle">
                <div className="flex min-w-0 flex-col gap-1">
                    <label
                        htmlFor={`document-line-name-${props.line.key}`}
                        className="sr-only"
                    >
                        {props.labels.product_or_service}
                    </label>
                    <DocumentProductCombobox
                        id={`document-line-name-${props.line.key}`}
                        value={props.line.productServiceName ?? ''}
                        searchUrl={props.productSearchUrl}
                        currencyCode={props.currencyCode}
                        labels={props.labels}
                        testId={`document-line-product-service-${props.index}`}
                        maxLength={props.limits.description}
                        quiet={quiet}
                        onChange={(value) =>
                            changeProductServiceName(props, value)
                        }
                        onSelect={props.onProductSelect}
                    />
                    <label
                        htmlFor={`document-line-description-${props.line.key}`}
                        className="sr-only"
                    >
                        {props.labels.fields.description}
                    </label>
                    <Textarea
                        id={`document-line-description-${props.line.key}`}
                        name={props.labels.fields.description}
                        className={cn(
                            'min-h-12 resize-none px-2 py-1.5 text-xs text-foreground-muted',
                            quiet &&
                                'border-transparent bg-transparent shadow-none hover:border-input hover:bg-background',
                        )}
                        value={props.line.description}
                        maxLength={lineDescriptionLimit(props)}
                        rows={2}
                        aria-invalid={Boolean(lineError(props, 'description'))}
                        onChange={(event) =>
                            changeField(
                                props,
                                'description',
                                event.target.value,
                            )
                        }
                    />
                    {props.sourceNotice}
                </div>
            </TableCell>
            <LineInputCell
                props={props}
                name="itemPrice"
                inputMode="decimal"
                quiet={quiet}
                priority
            />
            <LineInputCell
                props={props}
                name="quantity"
                inputMode="decimal"
                quiet={quiet}
                center
            />
            <LineInputCell
                props={props}
                name="unit"
                maxLength={props.limits.unit}
                quiet={quiet}
                center
                placeholder={quiet ? '—' : undefined}
            />
            <TableCell className="border-l border-rule px-2 align-top transition-colors focus-within:bg-surface-subtle hover:bg-surface-subtle">
                <CompactSelect
                    label={props.labels.fields.period_unit}
                    value={props.line.periodUnit}
                    options={periodOptions}
                    quiet={quiet}
                    alignment="center"
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
            </TableCell>
            <LineInputCell
                props={props}
                name="periodQuantity"
                inputMode="decimal"
                disabled={props.line.periodUnit === 'NONE'}
                quiet={quiet}
                center
                placeholder={quiet ? '—' : undefined}
            />
            <LineInputCell
                props={props}
                name="discountPercentage"
                inputMode="decimal"
                quiet={quiet}
            />
            <TableCell className="border-l border-rule px-2 align-top transition-colors focus-within:bg-surface-subtle hover:bg-surface-subtle">
                <div className="flex flex-col">
                    <CompactSelect
                        label={props.labels.tax_total}
                        testId={`document-line-tax-${props.index}`}
                        value={lineTaxSelection(props.line)}
                        quiet={quiet}
                        options={lineTaxOptions(
                            props.line,
                            props.inheritedTax ?? null,
                            props.taxPresetOptions,
                            {
                                noTax: props.labels.no_tax,
                            },
                        )}
                        error={
                            lineError(props, 'tax_preset_id') ??
                            lineError(props, 'tax_percentage')
                        }
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
                </div>
            </TableCell>
            <TableCell className="font-data border-l border-divider px-2 text-right align-top font-bold tabular-nums">
                {props.line.finalLineTotal ?? props.labels.incomplete}
            </TableCell>
            <TableCell className="border-l border-rule px-2 align-top">
                <div className="flex flex-wrap justify-end gap-1">
                    <DocumentLineRemoveAction {...props} />
                </div>
            </TableCell>
        </TableRow>
    );
}
function LineInputCell(props: {
    props: DocumentLineItemProps;
    name: keyof DocumentLineDraft;
    inputMode?: 'decimal';
    maxLength?: number;
    disabled?: boolean;
    quiet?: boolean;
    priority?: boolean;
    center?: boolean;
    placeholder?: string;
}) {
    const fieldName = fieldRequestName(props.name);

    return (
        <TableCell
            className={cn(
                'border-l border-rule px-2 align-top transition-colors focus-within:bg-surface-subtle hover:bg-surface-subtle',
                props.priority && 'border-divider',
            )}
        >
            <CompactInput
                label={props.props.labels.fields[fieldName]}
                value={String(props.props.line[props.name] ?? '')}
                inputMode={props.inputMode}
                maxLength={props.maxLength}
                disabled={props.disabled}
                quiet={props.quiet}
                alignment={props.center ? 'center' : undefined}
                placeholder={props.placeholder}
                error={lineError(props.props, fieldName)}
                onChange={(value) =>
                    changeField(props.props, props.name, value)
                }
            />
        </TableCell>
    );
}
