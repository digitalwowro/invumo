import { describe, expect, it } from 'vitest';
import { interpolate, pluralize, translate } from './translations';

const translations = {
    navigation: {
        invoices: 'Invoices',
    },
    pagination: {
        showing: 'Showing :from–:to of :total',
    },
};

describe('translation helpers', () => {
    it('reads a typed nested key', () => {
        expect(translate(translations, 'navigation.invoices')).toBe('Invoices');
    });

    it('interpolates named placeholders without removing unknown ones', () => {
        expect(
            interpolate(translations.pagination.showing, {
                from: 1,
                to: 20,
                total: 48,
            }),
        ).toBe('Showing 1–20 of 48');

        expect(interpolate('Hello :name, :missing', { name: 'Razvan' })).toBe(
            'Hello Razvan, :missing',
        );
    });

    it('uses browser-native Romanian plural categories', () => {
        const messages = {
            one: ':count factură',
            few: ':count facturi',
            other: ':count de facturi',
        };

        expect(pluralize('ro', messages, 1)).toBe('1 factură');
        expect(pluralize('ro', messages, 2)).toBe('2 facturi');
        expect(pluralize('ro', messages, 20)).toBe('20 de facturi');
    });
});
