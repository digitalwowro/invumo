import { render, screen } from '@testing-library/react';
import type { PropsWithChildren } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { CompanyAuditHistory } from '@/features/audit/components/company-audit-history';
import type { CompanyAuditTranslations } from '@/types/company-audit';

vi.mock('@inertiajs/react', () => ({
    Link: ({ href, children }: PropsWithChildren<{ href: string }>) => (
        <a href={href}>{children}</a>
    ),
}));

vi.mock('@/features/audit/components/company-audit-list-tools', () => ({
    CompanyAuditListTools: () => <div>Filters</div>,
}));

const labels: CompanyAuditTranslations = {
    head_title: 'Company audit history',
    title: 'Audit history',
    description: 'Significant activity',
    search_label: 'Search',
    search_placeholder: 'Search activity',
    actor_type_label: 'Actor type',
    target_type_label: 'Target type',
    all_targets: 'All targets',
    date_from: 'From',
    date_to: 'To',
    sort_label: 'Order',
    per_page_label: 'Rows',
    clear: 'Clear',
    previous: 'Previous',
    next: 'Next',
    not_available: 'Not available',
    target_context: ':type · :id',
    support_access: 'Performed through Invumo support access',
    reason: 'Reason',
    changes: 'View changes',
    changes_title: 'Recorded changes',
    changes_description: 'Approved fields only.',
    before: 'Before',
    after: 'After',
    empty_title: 'No audit events yet',
    empty_description: 'Activity will appear here.',
    no_results_title: 'No matching audit events',
    no_results_description: 'Change the filters.',
    actor_types: {
        all: 'All actors',
        USER: 'Company user',
        PUBLIC_CUSTOMER: 'Public customer',
        PROVIDER_WEBHOOK: 'Provider webhook',
        SCHEDULED_JOB: 'Scheduled automation',
        SYSTEM: 'Invumo system',
    },
    target_types: { Customer: 'Customer' },
    actions: { 'company.customer.updated': 'Customer updated' },
    sort_options: { newest: 'Newest first', oldest: 'Oldest first' },
};

const filters = {
    q: '',
    dateFrom: '',
    dateTo: '',
    actorType: 'all' as const,
    targetType: 'all',
    sort: 'newest' as const,
    perPage: 25,
};

describe('CompanyAuditHistory', () => {
    it('uses the shared activity presentation for localized privacy-safe facts', () => {
        render(
            <CompanyAuditHistory
                page={{
                    items: [
                        {
                            id: 'event-id',
                            actorType: 'USER',
                            actorName: 'Audit Admin',
                            actorReference: null,
                            supportAccess: true,
                            action: 'company.customer.updated',
                            targetType: 'Customer',
                            targetId: 'customer-id',
                            occurredAt: '2026-08-29T12:00:00.000Z',
                            reason: 'Approved correction',
                            before: { status: 'DRAFT' },
                            after: { status: 'ACTIVE' },
                        },
                    ],
                    previousUrl: null,
                    nextUrl: '/audit?cursor=next',
                }}
                filters={filters}
                targetOptions={['Customer']}
                indexUrl="/audit"
                timezone="Europe/Bucharest"
                locale="en"
                closeLabel="Close"
                labels={labels}
            />,
        );

        const timeline = screen.getByLabelText('Audit history');
        expect(timeline).toHaveAttribute('data-slot', 'activity-timeline');
        expect(timeline).toHaveClass('max-w-full', 'overflow-hidden');
        expect(screen.getByText('Customer updated')).toBeInTheDocument();
        expect(screen.getByText(/Audit Admin/)).toBeInTheDocument();
        expect(screen.getByText('Customer · customer-id')).toBeInTheDocument();
        expect(
            screen.getByText('Reason: Approved correction'),
        ).toBeInTheDocument();
        expect(
            screen.getByText('Performed through Invumo support access'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'View changes' }),
        ).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Next' })).toHaveAttribute(
            'href',
            '/audit?cursor=next',
        );
    });

    it('distinguishes an empty result from a filtered result', () => {
        const { rerender } = render(
            <CompanyAuditHistory
                page={{ items: [], previousUrl: null, nextUrl: null }}
                filters={filters}
                targetOptions={[]}
                indexUrl="/audit"
                timezone="UTC"
                locale="en"
                closeLabel="Close"
                labels={labels}
            />,
        );
        expect(screen.getByText('No audit events yet')).toBeInTheDocument();

        rerender(
            <CompanyAuditHistory
                page={{ items: [], previousUrl: null, nextUrl: null }}
                filters={{ ...filters, q: 'missing' }}
                targetOptions={[]}
                indexUrl="/audit"
                timezone="UTC"
                locale="en"
                closeLabel="Close"
                labels={labels}
            />,
        );
        expect(
            screen.getByText('No matching audit events'),
        ).toBeInTheDocument();
    });
});
