import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import PublicLayout from '@/layouts/public-layout';

describe('PublicLayout', () => {
    it('aligns the public brand with the full-width page frame', () => {
        render(
            <PublicLayout>
                <p>Public document</p>
            </PublicLayout>,
        );

        const brandContainer = screen.getByRole('img', {
            name: 'Invumo',
        }).parentElement;

        expect(brandContainer).toHaveClass('w-full', 'px-4', 'lg:px-8');
        expect(brandContainer).not.toHaveClass('mx-auto', 'max-w-screen-2xl');
    });
});
