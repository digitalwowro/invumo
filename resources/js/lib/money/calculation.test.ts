import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import { moneyString, storedMoney } from './decimal';
import { calculateDocumentAmounts } from './document-calculation';
import { calculateLineAmounts } from './line-calculation';
import type { LineAmounts, LineCalculationInput } from './line-calculation';

type LineCase = {
    name: string;
    input: {
        unit_price: string;
        quantity: string;
        period_unit: LineCalculationInput['periodUnit'];
        period_quantity: string | null;
        discount_percentage: string;
        tax_percentage: string;
        currency_precision: number;
    };
    expected: LineAmounts;
};

type Fixture = {
    line_cases: LineCase[];
    document_cases: Array<{
        name: string;
        currency_precision: number;
        line_cases: string[];
        expected: ReturnType<typeof calculateDocumentAmounts>;
    }>;
    valid_transport_cases: Array<{
        name: string;
        value: unknown;
        currency_precision: number;
        expected: string;
    }>;
    invalid_transport_cases: Array<{
        name: string;
        value: unknown;
        currency_precision: number;
    }>;
    invalid_line_cases: Array<{ name: string; input: LineCase['input'] }>;
};

const fixture = JSON.parse(
    readFileSync(
        resolve(
            process.cwd(),
            'tests/Fixtures/Calculation/calculation-vectors.json',
        ),
        'utf8',
    ),
) as Fixture;

describe('shared exact-decimal calculation vectors', () => {
    it.each(fixture.line_cases)('$name', ({ input, expected }) => {
        expect(calculateLineAmounts(toLineInput(input))).toEqual(expected);
    });

    it.each(fixture.document_cases)(
        '$name',
        ({ currency_precision, line_cases, expected }) => {
            const calculatedLines = new Map(
                fixture.line_cases.map((lineCase) => [
                    lineCase.name,
                    calculateLineAmounts(toLineInput(lineCase.input)),
                ]),
            );

            expect(
                calculateDocumentAmounts(
                    line_cases.map((name) =>
                        requireLine(calculatedLines, name),
                    ),
                    currency_precision,
                ),
            ).toEqual(expected);
        },
    );

    it.each(fixture.valid_transport_cases)(
        'normalizes $name',
        ({ value, currency_precision, expected }) => {
            expect(
                moneyString(
                    storedMoney(value, currency_precision),
                    currency_precision,
                ),
            ).toBe(expected);
        },
    );

    it.each(fixture.invalid_transport_cases)(
        'rejects $name',
        ({ value, currency_precision }) => {
            expect(() => storedMoney(value, currency_precision)).toThrow();
        },
    );

    it.each(fixture.invalid_line_cases)('rejects $name', ({ input }) => {
        expect(() => calculateLineAmounts(toLineInput(input))).toThrow();
    });
});

function toLineInput(input: LineCase['input']): LineCalculationInput {
    return {
        unitPrice: input.unit_price,
        quantity: input.quantity,
        periodUnit: input.period_unit,
        periodQuantity: input.period_quantity,
        discountPercentage: input.discount_percentage,
        taxPercentage: input.tax_percentage,
        currencyPrecision: input.currency_precision,
    };
}

function requireLine(
    lines: Map<string, LineAmounts>,
    name: string,
): LineAmounts {
    const line = lines.get(name);

    if (!line) {
        throw new Error(`Missing calculation vector: ${name}`);
    }

    return line;
}
