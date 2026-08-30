import type { DocumentLineDraft } from '@/types/document';

export function compactDecimal(
    value: string,
    minimumFractionDigits = 0,
): string {
    if (!/^\d+(?:\.\d+)?$/.test(value)) {
        return value;
    }

    const [integer, fraction = ''] = value.split('.', 2);
    const significantFraction = fraction.replace(/0+$/, '');
    const displayedFraction = significantFraction.padEnd(
        Math.max(minimumFractionDigits, significantFraction.length),
        '0',
    );

    return displayedFraction === ''
        ? integer
        : `${integer}.${displayedFraction}`;
}

export function compactDocumentLineDecimals<T extends DocumentLineDraft>(
    line: T,
    currencyPrecision: number | null,
): T {
    return {
        ...line,
        itemPrice: compactDecimal(line.itemPrice, currencyPrecision ?? 0),
        quantity: compactDecimal(line.quantity),
        periodQuantity: compactDecimal(line.periodQuantity),
        discountPercentage: compactDecimal(line.discountPercentage),
        taxPercentage: compactDecimal(line.taxPercentage),
    };
}
