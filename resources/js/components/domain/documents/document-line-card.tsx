import { ChevronDown, ChevronUp } from 'lucide-react';
import type { ReactNode } from 'react';
import { TextareaField } from '@/components/app/form-field';
import {
    changeField,
    changeProductServiceName,
    lineDescriptionLimit,
    lineError,
} from '@/components/domain/documents/document-line-control';
import {
    DocumentLineFields,
    DocumentLineOrderActions,
} from '@/components/domain/documents/document-line-form';
import { DocumentProductCombobox } from '@/components/domain/documents/document-product-combobox';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Field, FieldLabel } from '@/components/ui/field';
import type { LineAmounts } from '@/lib/money/line-calculation';
import type { CatalogTaxOption } from '@/types/catalog';
import type {
    DocumentLineDraft,
    DocumentLineLabels,
    DocumentLineLimits,
    DocumentProductDefaults,
    DocumentTaxDefault,
} from '@/types/document';

export type DocumentLineItemProps = {
    line: DocumentLineDraft;
    amounts?: LineAmounts | null;
    index: number;
    count: number;
    limits: DocumentLineLimits;
    labels: DocumentLineLabels;
    errors: Record<string, string>;
    sourceNotice?: ReactNode;
    inheritedTax?: DocumentTaxDefault | null;
    taxPresetOptions: CatalogTaxOption[];
    productSearchUrl: string;
    currencyCode: string | null;
    currencyPrecision: number | null;
    expanded?: boolean;
    onExpandedChange?: (expanded: boolean) => void;
    onChange: (line: DocumentLineDraft) => void;
    onProductSelect: (defaults: DocumentProductDefaults) => void;
    onMove: (direction: -1 | 1) => void;
    onRemove: () => void;
};

export function DocumentLineCard(props: DocumentLineItemProps) {
    const expanded = props.expanded ?? true;

    return (
        <Collapsible
            asChild
            open={expanded}
            onOpenChange={props.onExpandedChange}
        >
            <article
                className="border-b border-divider bg-background"
                data-test={`document-line-${props.index}`}
            >
                <div className="flex items-start justify-between gap-4 px-5 py-4 sm:px-6">
                    <LineSummary {...props} />
                    <CollapsibleTrigger asChild>
                        <Button
                            type="button"
                            variant="secondary"
                            size="compact"
                        >
                            {expanded ? (
                                <ChevronUp aria-hidden="true" />
                            ) : (
                                <ChevronDown aria-hidden="true" />
                            )}
                            {expanded
                                ? props.labels.close_line
                                : props.labels.edit_line}
                        </Button>
                    </CollapsibleTrigger>
                </div>
                <CollapsibleContent>
                    <div className="grid grid-cols-2 gap-4 border-t border-divider bg-surface-subtle px-5 py-5 sm:px-6">
                        <div className="col-span-2 flex justify-end gap-2">
                            <DocumentLineOrderActions {...props} />
                        </div>
                        <div className="col-span-2">
                            <Field>
                                <FieldLabel
                                    htmlFor={`document-line-product-${props.line.key}`}
                                >
                                    {props.labels.product_or_service}
                                </FieldLabel>
                                <DocumentProductCombobox
                                    id={`document-line-product-${props.line.key}`}
                                    value={props.line.productServiceName ?? ''}
                                    searchUrl={props.productSearchUrl}
                                    currencyCode={props.currencyCode}
                                    labels={props.labels}
                                    testId={`document-line-product-service-${props.index}`}
                                    maxLength={props.limits.description}
                                    onChange={(value) =>
                                        changeProductServiceName(props, value)
                                    }
                                    onSelect={props.onProductSelect}
                                />
                            </Field>
                        </div>
                        {props.sourceNotice && (
                            <div className="col-span-2">
                                {props.sourceNotice}
                            </div>
                        )}
                        <div className="col-span-2">
                            <TextareaField
                                label={props.labels.fields.description}
                                error={lineError(props, 'description')}
                                textarea={{
                                    value: props.line.description,
                                    maxLength: lineDescriptionLimit(props),
                                    rows: 2,
                                    onChange: (event) =>
                                        changeField(
                                            props,
                                            'description',
                                            event.target.value,
                                        ),
                                }}
                            />
                        </div>
                        <DocumentLineFields {...props} />
                    </div>
                </CollapsibleContent>
            </article>
        </Collapsible>
    );
}

function LineSummary(props: DocumentLineItemProps) {
    return (
        <div className="min-w-0 flex-1">
            <div className="mb-2 flex flex-wrap items-center gap-2">
                <span
                    className="font-data text-[11px] font-bold tracking-[0.09em] uppercase"
                    aria-label={`${props.labels.line} ${props.index + 1}`}
                >
                    {props.labels.line} {props.index + 1}
                </span>
                {typeof props.line.isCustomized === 'boolean' && (
                    <Badge
                        variant={props.line.isCustomized ? 'quiet' : 'muted'}
                    >
                        {props.line.isCustomized
                            ? props.labels.provenance_customized
                            : props.labels.provenance_default}
                    </Badge>
                )}
            </div>
            <p className="truncate text-sm font-semibold">
                {props.line.productServiceName ||
                    props.line.description ||
                    props.labels.product_or_service}
            </p>
            {props.line.productServiceName && props.line.description && (
                <p className="mt-0.5 line-clamp-1 text-xs text-foreground-muted">
                    {props.line.description}
                </p>
            )}
            <div className="font-data mt-3 grid grid-cols-3 gap-3 text-xs tabular-nums">
                <SummaryValue
                    label={`${props.labels.fields.quantity_short ?? props.labels.fields.quantity} × ${props.labels.fields.item_price}`}
                    value={`${props.line.quantity || '—'} × ${props.line.itemPrice || '—'}`}
                />
                <SummaryValue
                    label={props.labels.tax_total}
                    value={`${props.line.taxPercentage || '0'}%`}
                />
                <SummaryValue
                    label={props.labels.line_total}
                    value={props.line.finalLineTotal ?? props.labels.incomplete}
                    strong
                />
            </div>
        </div>
    );
}

function SummaryValue(props: {
    label: string;
    value: string;
    strong?: boolean;
}) {
    return (
        <span className="min-w-0">
            <span className="block truncate text-foreground-muted">
                {props.label}
            </span>
            <span className={props.strong ? 'font-bold text-foreground' : ''}>
                {props.value}
            </span>
        </span>
    );
}
