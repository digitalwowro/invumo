import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { MoneyValue } from '@/components/domain/money-value';

describe('MoneyValue', () => {
    it('preserves the server-formatted decimal string', () => {
        render(<MoneyValue value="1.234,50 RON" emphasis="strong" />);

        expect(screen.getByText('1.234,50 RON')).toHaveClass(
            'font-data',
            'font-bold',
        );
    });

    it('applies semantic tone without changing the value', () => {
        render(<MoneyValue value="€ 780,00" tone="danger" />);

        expect(screen.getByText('€ 780,00')).toHaveClass('text-danger-text');
    });
});
