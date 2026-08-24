import {
    currencyPrecision,
    exactMoney,
    moneyString,
    storedMoney,
} from './decimal';
import type { LineAmounts } from './line-calculation';

export type DocumentAmounts = {
    items_subtotal: string;
    items_total: string;
    discount_amount: string;
    grand_subtotal: string;
    tax_amount: string;
    final_total: string;
};

export function calculateDocumentAmounts(
    lines: LineAmounts[],
    currencyPrecisionValue: number,
): DocumentAmounts {
    const precision = currencyPrecision(currencyPrecisionValue);
    const totals = Array.from({ length: 6 }, () => storedMoney('0', precision));

    for (const line of lines) {
        const values = [
            line.items_subtotal,
            line.items_total,
            line.discount_amount,
            line.grand_subtotal,
            line.tax_amount,
            line.final_line_total,
        ];

        values.forEach((value, index) => {
            totals[index] = exactMoney(
                totals[index].plus(storedMoney(value, precision)),
                precision,
            );
        });
    }

    return {
        items_subtotal: moneyString(totals[0], precision),
        items_total: moneyString(totals[1], precision),
        discount_amount: moneyString(totals[2], precision),
        grand_subtotal: moneyString(totals[3], precision),
        tax_amount: moneyString(totals[4], precision),
        final_total: moneyString(totals[5], precision),
    };
}
