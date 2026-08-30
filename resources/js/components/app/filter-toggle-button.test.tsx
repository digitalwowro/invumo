import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { FilterToggleButton } from '@/components/app/filter-toggle-button';

describe('FilterToggleButton', () => {
    it('uses an ink count badge while collapsed', () => {
        render(
            <FilterToggleButton expanded={false} count={2} label="Filters" />,
        );

        expect(screen.getByRole('button')).toHaveAttribute(
            'data-variant',
            'secondary',
        );
        expect(screen.getByText('2')).toHaveClass('bg-primary');
    });

    it('uses a lime count badge on the expanded ink button', () => {
        render(<FilterToggleButton expanded count={2} label="Filters" />);

        expect(screen.getByRole('button')).toHaveAttribute(
            'data-variant',
            'primary',
        );
        expect(screen.getByText('2')).toHaveClass('bg-product-mark-fill');
    });
});
