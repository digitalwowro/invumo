import { describe, expect, it } from 'vitest';
import { renderDocumentNumberPreview } from './document-number-pattern';

describe('renderDocumentNumberPreview', () => {
    it('renders Company-configured literals, the current year, and padding', () => {
        expect(
            renderDocumentNumberPreview({
                pattern: 'INV-{YEAR}-{NUMBER}-RO',
                padding: '6',
                year: 2027,
                sequence: 42,
            }),
        ).toBe('INV-2027-000042-RO');
    });

    it.each([
        ['missing number', 'INV-{YEAR}', '4', 2026],
        ['duplicate number', '{NUMBER}-{NUMBER}', '4', 2026],
        ['duplicate year', '{YEAR}-{YEAR}-{NUMBER}', '4', 2026],
        ['unknown token', '{SERIES}-{NUMBER}', '4', 2026],
        ['control character', 'I-\n-{NUMBER}', '4', 2026],
        ['oversized pattern', `${'I'.repeat(113)}{NUMBER}`, '4', 2026],
        ['missing year context', 'I-{YEAR}-{NUMBER}', '4', null],
        ['invalid padding', 'I-{NUMBER}', '13', 2026],
    ])('rejects %s', (_case, pattern, padding, year) => {
        expect(
            renderDocumentNumberPreview({
                pattern,
                padding,
                year,
                sequence: 1,
            }),
        ).toBeNull();
    });
});
