import { Grid } from '@/components/app/layout';
import type { calculateDocumentAmounts } from '@/lib/money/document-calculation';
import { calculateLineAmounts } from '@/lib/money/line-calculation';
import type { LineAmounts } from '@/lib/money/line-calculation';
import { cn } from '@/lib/utils';
import type {
    DocumentEditorTranslations,
    DocumentLineDraft,
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
            previous.taxName !== next.taxName ||
            previous.taxPercentage !== next.taxPercentage
                ? null
                : next.taxPresetId,
        priceStatus:
            previous.itemPrice !== next.itemPrice && next.itemPrice !== ''
                ? null
                : next.priceStatus,
    };
}

const EDITABLE_LINE_FIELDS = [
    'productServiceId',
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
            <Grid columns={3} gap="lg">
                <Total label={labels.subtotal} value={totals?.grand_subtotal} />
                <Total label={labels.tax_total} value={totals?.tax_amount} />
                <Total
                    label={labels.total}
                    value={totals?.final_total}
                    strong
                />
            </Grid>
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
        <div className="flex flex-col gap-1">
            <p className="font-data text-[11px] font-bold tracking-[0.09em] text-foreground-muted uppercase">
                {label}
            </p>
            <p
                className={`font-mono tabular-nums ${strong ? 'text-xl font-semibold' : 'text-base'}`}
            >
                {value ?? '—'}
            </p>
        </div>
    );
}
