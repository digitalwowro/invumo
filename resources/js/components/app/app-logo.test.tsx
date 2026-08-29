import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import AppLogo from '@/components/app/app-logo';

describe('AppLogo', () => {
    it('renders the approved accessible product mark at each supported size', () => {
        const { rerender } = render(<AppLogo size="sidebar" />);

        expect(screen.getByRole('img', { name: 'Invumo' })).toHaveTextContent(
            'INVUMO',
        );

        rerender(<AppLogo size="header" />);
        expect(screen.getByRole('img', { name: 'Invumo' })).toBeVisible();

        rerender(<AppLogo size="hero" />);
        expect(screen.getByRole('img', { name: 'Invumo' })).toBeVisible();
    });
});
