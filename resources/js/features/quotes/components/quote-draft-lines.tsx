import { Grid } from '@/components/app/layout';
import { Surface } from '@/components/app/surface';
import type { calculateDocumentAmounts } from '@/lib/money/document-calculation';
import { calculateLineAmounts } from '@/lib/money/line-calculation';
import type { LineAmounts } from '@/lib/money/line-calculation';
import type { QuoteLine, QuoteTranslations } from '@/types/quote';

export const completeLine = (
    amounts: LineAmounts | null,
): amounts is LineAmounts => amounts !== null;

export function calculateQuoteLine(
    line: QuoteLine,
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
    previous: QuoteLine,
    next: QuoteLine,
): QuoteLine {
    return {
        ...next,
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

export function moveQuoteLine(
    lines: QuoteLine[],
    index: number,
    direction: -1 | 1,
): QuoteLine[] {
    const destination = index + direction;

    if (destination < 0 || destination >= lines.length) {
        return lines;
    }

    const next = [...lines];
    [next[index], next[destination]] = [next[destination], next[index]];

    return next;
}

export function QuoteTotals({
    labels,
    totals,
}: {
    labels: QuoteTranslations['edit'];
    totals: ReturnType<typeof calculateDocumentAmounts> | null;
}) {
    return (
        <Surface>
            <Grid columns={3} gap="lg">
                <Total label={labels.subtotal} value={totals?.grand_subtotal} />
                <Total label={labels.tax_total} value={totals?.tax_amount} />
                <Total
                    label={labels.total}
                    value={totals?.final_total}
                    strong
                />
            </Grid>
        </Surface>
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
        <div className="space-y-1">
            <p className="text-sm text-foreground-muted">{label}</p>
            <p
                className={`font-mono tabular-nums ${strong ? 'text-xl font-semibold' : 'text-base'}`}
            >
                {value ?? '—'}
            </p>
        </div>
    );
}
