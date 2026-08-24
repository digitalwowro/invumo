import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { PageFrame } from '@/components/app/page-frame';

describe('PageFrame', () => {
    it('uses the dense application width by default', () => {
        render(
            <PageFrame>
                <p>Content</p>
            </PageFrame>,
        );

        expect(screen.getByText('Content').parentElement).toHaveClass(
            'max-w-7xl',
        );
    });

    it('keeps the explicit full-width boundary available', () => {
        render(
            <PageFrame width="full">
                <p>Content</p>
            </PageFrame>,
        );

        expect(screen.getByText('Content').parentElement).toHaveClass(
            'max-w-none',
        );
    });
});
