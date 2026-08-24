import {
    currencyPrecision,
    exactMoney,
    moneySource,
    moneyString,
    percentage,
    quantity,
    roundMoney,
} from './decimal';
import type { ExactDecimal } from './decimal';

export type PeriodUnit = 'NONE' | 'MONTH' | 'YEAR';

export type LineCalculationInput = {
    unitPrice: string;
    quantity: string;
    periodUnit: PeriodUnit;
    periodQuantity: string | null;
    discountPercentage: string;
    taxPercentage: string;
    currencyPrecision: number;
};

export type LineAmounts = {
    items_subtotal: string;
    items_total: string;
    discount_amount: string;
    grand_subtotal: string;
    tax_amount: string;
    final_line_total: string;
};

export function calculateLineAmounts(input: LineCalculationInput): LineAmounts {
    const precision = currencyPrecision(input.currencyPrecision);
    const unitPrice = moneySource(input.unitPrice);
    const lineQuantity = quantity(input.quantity);
    const discountPercentage = percentage(input.discountPercentage, true);
    const taxPercentage = percentage(input.taxPercentage);
    const periodQuantity = resolvePeriodQuantity(input);

    const itemsSubtotal = roundMoney(unitPrice.times(lineQuantity), precision);
    const itemsTotal = periodQuantity
        ? roundMoney(itemsSubtotal.times(periodQuantity), precision)
        : itemsSubtotal;
    const discountAmount = roundMoney(
        itemsTotal.times(discountPercentage).dividedBy(100),
        precision,
    );
    const grandSubtotal = exactMoney(
        itemsTotal.minus(discountAmount),
        precision,
    );
    const taxAmount = roundMoney(
        grandSubtotal.times(taxPercentage).dividedBy(100),
        precision,
    );
    const finalLineTotal = exactMoney(grandSubtotal.plus(taxAmount), precision);

    return {
        items_subtotal: moneyString(itemsSubtotal, precision),
        items_total: moneyString(itemsTotal, precision),
        discount_amount: moneyString(discountAmount, precision),
        grand_subtotal: moneyString(grandSubtotal, precision),
        tax_amount: moneyString(taxAmount, precision),
        final_line_total: moneyString(finalLineTotal, precision),
    };
}

function resolvePeriodQuantity(
    input: LineCalculationInput,
): ExactDecimal | null {
    if (input.periodUnit === 'NONE') {
        if (input.periodQuantity !== null) {
            throw new Error(
                'Lines without a period cannot have a period quantity.',
            );
        }

        return null;
    }

    if (input.periodQuantity === null) {
        throw new Error('Periodic lines require a period quantity.');
    }

    return quantity(input.periodQuantity);
}
