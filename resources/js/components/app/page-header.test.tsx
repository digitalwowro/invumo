import { render } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { PageHeader } from '@/components/app/page-header';

describe('PageHeader', () => {
    it('places collection actions in the responsive upper-right position', () => {
        const { container } = render(
            <PageHeader
                title="Invoices"
                actionsPlacement="top-right"
                actions={<button type="button">New invoice</button>}
            />,
        );

        expect(
            container.querySelector('[data-slot="page-header"]'),
        ).toHaveClass('sm:flex-row', 'sm:justify-between');
        expect(
            container.querySelector('[data-slot="page-header-actions"]'),
        ).toHaveClass('shrink-0', 'sm:justify-end');
    });

    it('keeps workspace-style actions below the title by default', () => {
        const { container } = render(
            <PageHeader
                title="Customer"
                actions={<button type="button">Save customer</button>}
            />,
        );

        expect(
            container.querySelector('[data-slot="page-header"]'),
        ).not.toHaveClass('sm:flex-row');
    });
});
