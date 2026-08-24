import Decimal from 'decimal.js';

const FinancialDecimal = Decimal.clone({
    precision: 128,
    rounding: Decimal.ROUND_HALF_UP,
    toExpNeg: -1000,
    toExpPos: 1000,
});

const MONEY_INTEGER_DIGITS = 22;
const MONEY_SCALE = 8;
const PERCENTAGE_INTEGER_DIGITS = 6;
const PERCENTAGE_SCALE = 6;
const QUANTITY_INTEGER_DIGITS = 14;
const QUANTITY_SCALE = 6;

export const MAX_CURRENCY_PRECISION = 8;

export type ExactDecimal = Decimal;

export function currencyPrecision(precision: number): number {
    if (
        !Number.isInteger(precision) ||
        precision < 0 ||
        precision > MAX_CURRENCY_PRECISION
    ) {
        throw new Error(
            'Currency precision must be an integer between zero and eight.',
        );
    }

    return precision;
}

export function moneySource(value: unknown): ExactDecimal {
    return parseDecimal(value, MONEY_SCALE, MONEY_INTEGER_DIGITS);
}

export function quantity(value: unknown): ExactDecimal {
    const parsed = parseDecimal(value, QUANTITY_SCALE, QUANTITY_INTEGER_DIGITS);

    if (parsed.isZero()) {
        throw new Error('Quantity must be greater than zero.');
    }

    return parsed;
}

export function percentage(
    value: unknown,
    maximumOneHundred = false,
): ExactDecimal {
    const parsed = parseDecimal(
        value,
        PERCENTAGE_SCALE,
        PERCENTAGE_INTEGER_DIGITS,
    );

    if (maximumOneHundred && parsed.greaterThan(100)) {
        throw new Error('Percentage must not exceed one hundred.');
    }

    return parsed;
}

export function roundMoney(
    value: ExactDecimal,
    precision: number,
): ExactDecimal {
    const rounded = value.toDecimalPlaces(
        currencyPrecision(precision),
        Decimal.ROUND_HALF_UP,
    );
    ensureEnvelope(rounded, MONEY_INTEGER_DIGITS);

    return rounded;
}

export function exactMoney(
    value: ExactDecimal,
    precision: number,
): ExactDecimal {
    const resolvedPrecision = currencyPrecision(precision);

    if (value.decimalPlaces() > resolvedPrecision) {
        throw new Error('Money value exceeds the currency precision.');
    }

    ensureEnvelope(value, MONEY_INTEGER_DIGITS);

    return value;
}

export function storedMoney(value: unknown, precision: number): ExactDecimal {
    return exactMoney(moneySource(value), precision);
}

export function moneyString(value: ExactDecimal, precision: number): string {
    return exactMoney(value, precision).toFixed(precision);
}

function parseDecimal(
    value: unknown,
    maximumScale: number,
    maximumIntegerDigits: number,
): ExactDecimal {
    if (typeof value !== 'string' || !/^\d+(?:\.\d+)?$/.test(value)) {
        throw new Error('Decimal values must be plain non-negative strings.');
    }

    const fraction = value.split('.', 2)[1] ?? '';

    if (fraction.length > maximumScale) {
        throw new Error('Decimal value exceeds its storage scale.');
    }

    const decimal = new FinancialDecimal(value);
    ensureEnvelope(decimal, maximumIntegerDigits);

    return decimal;
}

function ensureEnvelope(
    value: ExactDecimal,
    maximumIntegerDigits: number,
): void {
    if (value.isNegative()) {
        throw new Error('Decimal values must not be negative.');
    }

    const integral = value.trunc().toFixed(0).replace(/^0+/, '');
    const integerDigits = integral === '' ? 1 : integral.length;

    if (integerDigits > maximumIntegerDigits) {
        throw new Error('Decimal value exceeds its storage precision.');
    }
}
