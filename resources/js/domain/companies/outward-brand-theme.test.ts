import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import {
    DEFAULT_OUTWARD_BRAND_COLOR,
    isOutwardBrandColor,
    OUTWARD_BRAND_RULE_CONTRAST_MINIMUM,
    OUTWARD_BRAND_TEXT_CONTRAST_MINIMUM,
    resolveOutwardBrandTheme,
} from '@/domain/companies/outward-brand-theme';

type Fixture = {
    contract: {
        default_color: string;
        text_contrast_minimum: number;
        rule_contrast_minimum: number;
    };
    valid_cases: Array<{
        name: string;
        input: string;
        expected: {
            accent_color: string;
            on_accent_color: string;
            text_color: string;
            rule_color: string;
        };
    }>;
    invalid_cases: Array<{ name: string; input: string }>;
};

const fixture = JSON.parse(
    readFileSync(
        resolve(
            process.cwd(),
            'tests/Fixtures/Branding/outward-brand-theme-vectors.json',
        ),
        'utf8',
    ),
) as Fixture;

describe('shared outward brand theme vectors', () => {
    it('keeps the browser constants aligned with the shared contract', () => {
        expect(DEFAULT_OUTWARD_BRAND_COLOR).toBe(
            fixture.contract.default_color,
        );
        expect(OUTWARD_BRAND_TEXT_CONTRAST_MINIMUM).toBe(
            fixture.contract.text_contrast_minimum,
        );
        expect(OUTWARD_BRAND_RULE_CONTRAST_MINIMUM).toBe(
            fixture.contract.rule_contrast_minimum,
        );
    });

    it.each(fixture.valid_cases)('$name', ({ input, expected }) => {
        expect(isOutwardBrandColor(input)).toBe(true);

        const theme = resolveOutwardBrandTheme(input);

        expect({
            accent_color: theme.accentColor,
            on_accent_color: theme.onAccentColor,
            text_color: theme.textColor,
            rule_color: theme.ruleColor,
        }).toEqual(expected);
    });

    it.each(fixture.invalid_cases)('rejects $name', ({ input }) => {
        expect(isOutwardBrandColor(input)).toBe(false);
        expect(() => resolveOutwardBrandTheme(input)).toThrow();
    });
});
