import { describe, expect, it } from 'vitest';
import {
    isOutwardBrandColor,
    resolveOutwardBrandTheme,
} from '@/domain/companies/outward-brand-theme';

describe('outward brand theme', () => {
    it('uses the chosen dark color with a white foreground', () => {
        expect(resolveOutwardBrandTheme('#14181C')).toEqual({
            accentColor: '#14181C',
            onAccentColor: '#FFFFFF',
            textColor: '#14181C',
            ruleColor: '#14181C',
        });
    });

    it('falls back to neutral ink when a bright color is unsafe on white', () => {
        expect(resolveOutwardBrandTheme('#FFFF00')).toEqual({
            accentColor: '#FFFF00',
            onAccentColor: '#000000',
            textColor: '#14181C',
            ruleColor: '#14181C',
        });
    });

    it('accepts only canonical uppercase hex colors', () => {
        expect(isOutwardBrandColor('#5B3A8E')).toBe(true);
        expect(isOutwardBrandColor('#5b3a8e')).toBe(false);
        expect(() => resolveOutwardBrandTheme('violet')).toThrow();
    });
});
