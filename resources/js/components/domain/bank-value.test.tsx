import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { BankValue } from '@/components/domain/bank-value';

const writeText = vi.fn<() => Promise<void>>();

describe('BankValue', () => {
    beforeEach(() => {
        writeText.mockReset();
        writeText.mockResolvedValue();
        Object.defineProperty(navigator, 'clipboard', {
            configurable: true,
            value: { writeText },
        });
    });

    it('wraps the exact value safely and copies only after a user action', async () => {
        render(
            <BankValue
                value="RO49AAAA1B31007593840000"
                copyLabel="Copy banking value"
                copiedLabel="Banking value copied"
            />,
        );

        expect(screen.getByText('RO49AAAA1B31007593840000')).toHaveClass(
            'break-all',
        );
        expect(writeText).not.toHaveBeenCalled();
        fireEvent.click(
            screen.getByRole('button', { name: 'Copy banking value' }),
        );
        await waitFor(() =>
            expect(writeText).toHaveBeenCalledWith('RO49AAAA1B31007593840000'),
        );
        expect(
            screen.getByRole('button', { name: 'Banking value copied' }),
        ).toBeInTheDocument();
    });
});
