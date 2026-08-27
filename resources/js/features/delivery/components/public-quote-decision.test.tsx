import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { PublicQuoteDecision } from '@/features/delivery/components/public-quote-decision';
import type { PublicDocumentTranslations } from '@/types/public-document';

const labels: PublicDocumentTranslations['decision'] = {
    title: 'Respond to this quote',
    description: 'Enter your details.',
    customer_name: 'Your name',
    customer_email: 'Your email',
    accept: 'Accept quote',
    reject: 'Reject quote',
    accepted_title: 'Quote accepted',
    accepted_description: 'Acceptance recorded.',
    rejected_title: 'Quote rejected',
    rejected_description: 'Rejection recorded.',
    unavailable_title: 'Response unavailable',
    unavailable_description: 'This quote cannot receive a response.',
};

describe('PublicQuoteDecision', () => {
    it('uses a native retry-safe form for either localized decision', () => {
        render(
            <PublicQuoteDecision
                decision={{
                    state: 'AVAILABLE',
                    submitUrl: '/q/redacted/decision',
                    idempotencyKey: 'decision-key',
                    locale: 'en',
                    csrfToken: 'csrf-token',
                    customerName: 'Existing Name',
                    customerEmail: 'existing@example.com',
                }}
                labels={labels}
                errors={{}}
            />,
        );

        expect(screen.getByLabelText('Your name')).toBeRequired();
        expect(screen.getByLabelText('Your name')).toHaveValue('Existing Name');
        expect(screen.getByLabelText('Your email')).toHaveAttribute(
            'type',
            'email',
        );
        const reject = screen.getByRole('button', { name: 'Reject quote' });
        expect(reject).toHaveAttribute('name', 'decision');
        expect(reject).toHaveAttribute('value', 'REJECTED');
        expect(reject.closest('form')).toHaveAttribute(
            'action',
            '/q/redacted/decision',
        );
        expect(reject.closest('form')).toHaveAttribute('method', 'post');
    });

    it.each([
        ['ACCEPTED', 'Quote accepted'],
        ['REJECTED', 'Quote rejected'],
        ['UNAVAILABLE', 'Response unavailable'],
    ] as const)(
        'renders the %s outcome without another form',
        (state, title) => {
            render(
                <PublicQuoteDecision
                    decision={{
                        state,
                        submitUrl: null,
                        idempotencyKey: null,
                        locale: 'en',
                        csrfToken: 'csrf-token',
                        customerName: '',
                        customerEmail: '',
                    }}
                    labels={labels}
                    errors={{}}
                />,
            );

            expect(screen.getByText(title)).toBeInTheDocument();
            expect(
                screen.queryByRole('button', { name: 'Accept quote' }),
            ).not.toBeInTheDocument();
        },
    );
});
