import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import { ChoiceField } from '@/components/app/choice-field';

describe('ChoiceField', () => {
    it('keeps one submitted value selected', async () => {
        const user = userEvent.setup();
        const { container } = render(
            <ChoiceField
                name="currency_display_style"
                label="Currency display style"
                defaultValue="CODE"
                required
                options={[
                    { value: 'CODE', label: 'ISO code' },
                    { value: 'SYMBOL', label: 'Currency symbol' },
                ]}
            />,
        );

        await user.click(
            screen.getByRole('radio', { name: 'Currency symbol' }),
        );

        expect(
            screen.getByRole('radio', { name: 'Currency symbol' }),
        ).toHaveAttribute('data-state', 'on');
        expect(
            container.querySelector<HTMLInputElement>(
                'input[name="currency_display_style"]',
            )?.value,
        ).toBe('SYMBOL');
    });

    it('does not clear a required selection', async () => {
        const user = userEvent.setup();
        render(
            <ChoiceField
                name="currency_display_style"
                label="Currency display style"
                defaultValue="CODE"
                required
                options={[
                    { value: 'CODE', label: 'ISO code' },
                    { value: 'SYMBOL', label: 'Currency symbol' },
                ]}
            />,
        );

        await user.click(screen.getByRole('radio', { name: 'ISO code' }));

        expect(screen.getByRole('radio', { name: 'ISO code' })).toHaveAttribute(
            'data-state',
            'on',
        );
    });
});
