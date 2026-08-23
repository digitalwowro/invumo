import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import {
    StatusBadge,
    statusPresentations,
} from '@/components/domain/status-badge';
import type { Status } from '@/components/domain/status-badge';

const expectedStatuses: Status[] = [
    'paid',
    'accepted',
    'completed',
    'overdue',
    'rejected',
    'failed',
    'partial',
    'expired',
    'paused',
    'issued',
    'sent',
    'active',
    'unpaid',
    'draft',
    'cancelled',
    'archived',
];

describe('StatusBadge', () => {
    it('defines one presentation for every approved status', () => {
        expect(Object.keys(statusPresentations).sort()).toEqual(
            [...expectedStatuses].sort(),
        );
    });

    it('renders the localized label without deriving copy in React', () => {
        render(<StatusBadge status="partial" label="Parțial" />);

        expect(screen.getByText('Parțial')).toHaveAttribute(
            'data-status',
            'partial',
        );
    });

    it('uses the shared positive presentation', () => {
        render(<StatusBadge status="paid" label="Paid" />);

        const badge = screen.getByText('Paid');
        expect(badge).toHaveClass('bg-money-fill');
        expect(badge.querySelector('svg')).toBeInTheDocument();
    });
});
