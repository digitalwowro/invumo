import { compactDocumentLineDecimals } from '@/domain/documents/document-line-decimals';
import type { calculateDocumentAmounts } from '@/lib/money/document-calculation';
import { calculateLineAmounts } from '@/lib/money/line-calculation';
import type { LineAmounts } from '@/lib/money/line-calculation';
import { cn } from '@/lib/utils';
import type {
    DocumentEditorTranslations,
    DocumentLineDraft,
    DocumentProductDefaults,
    DocumentTaxDefault,
} from '@/types/document';

export const completeLine = (
    amounts: LineAmounts | null,
): amounts is LineAmounts => amounts !== null;

export function calculateDocumentLine(
    line: DocumentLineDraft,
    precision: number | null,
): LineAmounts | null {
    if (
        precision === null ||
        line.itemPrice === '' ||
        line.quantity === '' ||
        (line.periodUnit !== 'NONE' && line.periodQuantity === '')
    ) {
        return null;
    }

    try {
        return calculateLineAmounts({
            unitPrice: line.itemPrice,
            quantity: line.quantity,
            periodUnit: line.periodUnit,
            periodQuantity:
                line.periodUnit === 'NONE' ? null : line.periodQuantity,
            discountPercentage: line.discountPercentage,
            taxPercentage: line.taxPercentage,
            currencyPrecision: precision,
        });
    } catch {
        return null;
    }
}

export function normalizeEditedLine(
    previous: DocumentLineDraft,
    next: DocumentLineDraft,
): DocumentLineDraft {
    const customized = EDITABLE_LINE_FIELDS.some(
        (field) => previous[field] !== next[field],
    );

    return {
        ...next,
        isCustomized: customized ? true : next.isCustomized,
        sourceApplied: customized ? false : next.sourceApplied,
        taxPresetId:
            (previous.taxName !== next.taxName ||
                previous.taxPercentage !== next.taxPercentage) &&
            previous.taxPresetId === next.taxPresetId
                ? null
                : next.taxPresetId,
        priceStatus:
            previous.itemPrice !== next.itemPrice && next.itemPrice !== ''
                ? null
                : next.priceStatus,
    };
}

export function applyDocumentProductDefaults(
    line: DocumentLineDraft,
    product: DocumentProductDefaults,
    fallbackTax: DocumentTaxDefault | null,
    currencyPrecision: number | null,
): DocumentLineDraft {
    const recurring = line.taxMode !== undefined;
    const tax = product.tax ?? fallbackTax;

    return compactDocumentLineDecimals(
        {
            ...line,
            productServiceId: product.sourceProductServiceId,
            productServiceName: product.name ?? null,
            description: product.description,
            itemPrice: product.unitPrice ?? '',
            unit: product.unit ?? '',
            periodUnit: product.periodUnit,
            periodQuantity:
                product.periodUnit === 'NONE' ? '' : line.periodQuantity,
            taxName: tax?.name ?? '',
            taxPercentage: tax?.percentage ?? '0',
            taxPresetId: recurring
                ? (product.tax?.sourceTaxPresetId ?? null)
                : (product.tax?.sourceTaxPresetId ?? fallbackTax?.id ?? null),
            taxMode: recurring
                ? product.tax
                    ? 'EXPLICIT'
                    : 'INHERIT_CUSTOMER'
                : line.taxMode,
            usesDocumentTaxDefault: recurring
                ? line.usesDocumentTaxDefault
                : !product.tax,
            priceStatus: product.priceStatus,
            isCustomized: false,
            sourceApplied: true,
        },
        currencyPrecision,
    );
}

const EDITABLE_LINE_FIELDS = [
    'productServiceId',
    'productServiceName',
    'description',
    'itemPrice',
    'quantity',
    'unit',
    'periodUnit',
    'periodQuantity',
    'discountPercentage',
    'taxName',
    'taxPercentage',
    'taxPresetId',
    'taxMode',
    'usesDocumentTaxDefault',
] as const satisfies ReadonlyArray<keyof DocumentLineDraft>;

export function detachedLineDescription(
    description: string | null,
    productServiceName: string | null | undefined,
): string {
    const value = description ?? '';

    if (!productServiceName) {
        return value;
    }

    return value === productServiceName
        ? ''
        : value.startsWith(`${productServiceName}\n`)
          ? value.slice(productServiceName.length + 1)
          : value;
}

export function editableDocumentLineContent(
    storedDescription: string | null,
    sourceProductServiceName: string | null | undefined,
) {
    const value = storedDescription ?? '';

    if (sourceProductServiceName) {
        return {
            productServiceName: sourceProductServiceName,
            description: detachedLineDescription(
                value,
                sourceProductServiceName,
            ),
        };
    }

    const separator = value.indexOf('\n');

    return separator === -1
        ? { productServiceName: value, description: '' }
        : {
              productServiceName: value.slice(0, separator),
              description: value.slice(separator + 1),
          };
}

export function storedDocumentLineDescription(line: DocumentLineDraft) {
    const name = line.productServiceName ?? '';

    if (!name) {
        return line.description;
    }

    if (!line.description) {
        return name;
    }

    return `${name}\n${line.description}`;
}

export function moveDocumentLine(
    lines: DocumentLineDraft[],
    index: number,
    direction: -1 | 1,
): DocumentLineDraft[] {
    const destination = index + direction;

    if (destination < 0 || destination >= lines.length) {
        return lines;
    }

    const next = [...lines];
    [next[index], next[destination]] = [next[destination], next[index]];

    return next;
}

export function DocumentTotals({
    labels,
    totals,
    embedded = false,
}: {
    labels: DocumentEditorTranslations;
    totals: ReturnType<typeof calculateDocumentAmounts> | null;
    embedded?: boolean;
}) {
    return (
        <div
            className={cn(
                'bg-background px-5 py-5 sm:px-6',
                embedded && 'border-t border-divider',
            )}
        >
            <div className="ml-auto flex w-full max-w-sm flex-col gap-3">
                <Total label={labels.subtotal} value={totals?.grand_subtotal} />
                <Total label={labels.tax_total} value={totals?.tax_amount} />
                <Total
                    label={labels.total}
                    value={totals?.final_total}
                    strong
                />
            </div>
        </div>
    );
}

function Total({
    label,
    value,
    strong = false,
}: {
    label: string;
    value?: string;
    strong?: boolean;
}) {
    return (
        <div
            className={`font-data flex items-baseline justify-between gap-6 tabular-nums ${strong ? 'border-t border-divider pt-3' : ''}`}
        >
            <p
                className={
                    strong
                        ? 'text-sm font-bold'
                        : 'text-sm text-foreground-muted'
                }
            >
                {label}
            </p>
            <p
                className={
                    strong ? 'text-2xl font-bold' : 'text-base font-semibold'
                }
            >
                {value ?? '—'}
            </p>
        </div>
    );
}
