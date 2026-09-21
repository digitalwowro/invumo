import { render, screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { OutwardDocument } from '@/components/domain/outward-document';
import type { OutwardDocument as OutwardDocumentData } from '@/types/outward-document';

const document: OutwardDocumentData = {
    kind: 'Quote',
    number: 'Q-2026-0001',
    status: 'Draft',
    language: 'en',
    issueDate: 'Sep 21, 2026',
    validUntil: 'Nov 20, 2026',
    dueDate: null,
    customerReference: null,
    theme: {
        accentColor: 'var(--foreground)',
        onAccentColor: 'var(--background)',
        textColor: 'var(--foreground)',
        ruleColor: 'var(--border)',
    },
    company: {
        displayName: 'ZeroPoint',
        legalName: 'ZeroPoint LLC',
        address: ['United Arab Emirates'],
        registrations: [],
        contacts: [],
    },
    customer: {
        displayName: 'Customer LLC',
        address: ['Qatar'],
        registrations: [],
        contacts: [],
    },
    lines: [],
    subtotal: '0.00 USD',
    taxTotal: '0.00 USD',
    total: '0.00 USD',
    bank: [],
    termsAndConditions: null,
    notes: null,
    logoUrl: 'data:image/png;base64,logo',
    labels: {
        from: 'From',
        bill_to: 'Bill to',
        issue_date: 'Issue date',
        valid_until: 'Valid until',
        due_date: 'Due date',
        customer_reference: 'Customer reference',
        description: 'Description',
        quantity: 'Quantity',
        unit_price: 'Unit price',
        tax: 'Tax',
        line_total: 'Line total',
        discount: 'Discount',
        not_set: 'Not set',
        no_lines: 'No lines',
        subtotal: 'Subtotal',
        tax_total: 'Tax',
        total: 'Total',
        bank_details: 'Bank details',
        notes: 'Notes',
        terms_and_conditions: 'Terms and conditions',
    },
};

describe('OutwardDocument', () => {
    it('uses a logo-only header and legal identity in From', () => {
        const { container } = render(<OutwardDocument document={document} />);

        expect(screen.getByRole('img', { name: 'ZeroPoint' })).toHaveAttribute(
            'src',
            document.logoUrl,
        );
        expect(screen.queryByText('ZeroPoint')).not.toBeInTheDocument();
        expect(container.querySelector('article')).toHaveStyle({
            '--outward-accent': document.theme.accentColor,
        });

        const from = screen.getByRole('heading', {
            name: 'From',
        }).parentElement;
        expect(from).not.toBeNull();
        expect(
            within(from as HTMLElement).getByText('ZeroPoint LLC'),
        ).toBeVisible();
        expect(screen.getByText('United Arab Emirates')).toBeVisible();
        expect(screen.getByText('Qatar')).toBeVisible();
    });

    it('does not duplicate Company identity when no logo is available', () => {
        render(<OutwardDocument document={{ ...document, logoUrl: null }} />);

        expect(screen.queryByText('ZeroPoint')).not.toBeInTheDocument();
        expect(screen.getByText('ZeroPoint LLC')).toBeVisible();
    });
});
