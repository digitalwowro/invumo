import { fireEvent, render, screen, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { DocumentDeliveryPanel } from '@/features/delivery/components/document-delivery-panel';
import type {
    DocumentDelivery,
    DocumentDeliveryTranslations,
} from '@/types/document-delivery';

const post = vi.fn();

vi.stubGlobal(
    'ResizeObserver',
    class {
        observe() {}
        unobserve() {}
        disconnect() {}
    },
);

vi.mock('@inertiajs/react', () => ({
    useForm: (initial: Record<string, unknown>) => ({
        data: initial,
        setData: vi.fn(),
        post,
        processing: false,
        errors: {},
    }),
}));

const labels: DocumentDeliveryTranslations = {
    title: 'Email delivery',
    description: 'Send this document.',
    send: 'Send email',
    composer: {
        title: 'Send document email',
        description: 'Review exact content.',
        subject: 'Subject',
        body: 'Message',
        button_label: 'Button label',
        signature: 'Signature',
        attachment_mode: 'Delivery mode',
        modes: {
            SECURE_LINK_ONLY: 'Secure link only',
            ATTACH_PDF: 'Secure link and PDF',
        },
        recipients: 'Recipients',
        recipient_name: 'Name',
        recipient_email: 'Email',
        recipient_role: 'Role',
        roles: { TO: 'To', CC: 'Cc', BCC: 'Bcc' },
        add_recipient: 'Add recipient',
        remove_recipient: 'Remove recipient',
        final_state_warning: 'Lifecycle stays final.',
        final_state_confirm: 'I understand.',
        unsaved_warning: 'Save changes before sending.',
        cancel: 'Cancel',
        submit: 'Send email',
        close: 'Close',
    },
    history: {
        title: 'Delivery history',
        empty: 'No deliveries.',
        sent_at: 'Queued',
        attempts: 'Attempts',
        recipients: 'Recipients',
        attachment: 'PDF attached',
        statuses: {
            QUEUED: 'Queued',
            RETRYING: 'Retrying',
            ACCEPTED: 'Accepted',
            REJECTED: 'Failed',
            UNKNOWN: 'Outcome unknown',
        },
        retry: 'Retry',
        retry_title: 'Retry delivery?',
        retry_warning: 'A duplicate may be delivered.',
        retry_confirm: 'Retry now',
        retry_cancel: 'Cancel',
    },
};

const delivery: DocumentDelivery = {
    locale: 'en',
    timezone: 'Europe/Bucharest',
    composer: {
        deliveryKey: '0198f45d-9e53-7b65-a631-7d98f6065f63',
        sendUrl: '/deliveries',
        editVersion: 2,
        language: 'en',
        attachmentMode: 'SECURE_LINK_ONLY',
        recipients: [{ role: 'TO', name: 'Ana', email: 'ana@example.com' }],
        subject: 'Quote Q-2026-0001',
        body: 'Please review {{public_url}}.',
        buttonLabel: 'View securely',
        signature: 'Invumo',
        requiresFinalStateConfirmation: true,
    },
    history: [],
    limits: {
        subject: 500,
        body: 20_000,
        buttonLabel: 80,
        signature: 5_000,
        recipients: 100,
    },
};

describe('DocumentDeliveryPanel', () => {
    it('opens the localized editable composer with final-state warning', () => {
        render(<DocumentDeliveryPanel delivery={delivery} labels={labels} />);

        expect(screen.getByText('No deliveries.')).toBeInTheDocument();
        fireEvent.click(screen.getByRole('button', { name: 'Send email' }));
        const dialog = screen.getByRole('dialog');
        expect(within(dialog).getByLabelText('Subject')).toHaveValue(
            'Quote Q-2026-0001',
        );
        expect(within(dialog).getByLabelText('Email')).toHaveValue(
            'ana@example.com',
        );
        expect(
            within(dialog).getByText('Lifecycle stays final.'),
        ).toBeVisible();
        expect(
            within(dialog).getByLabelText('I understand.'),
        ).not.toBeChecked();
    });

    it('shows bounded localized history without overflowing long values', () => {
        render(
            <DocumentDeliveryPanel
                labels={labels}
                delivery={{
                    ...delivery,
                    composer: null,
                    history: [
                        {
                            id: 'delivery',
                            state: 'REJECTED',
                            subject: 'A very long immutable delivery subject',
                            attachmentMode: 'ATTACH_PDF',
                            createdAt: '2026-08-28T09:00:00Z',
                            acceptedAt: null,
                            failureSummary: 'The provider rejected the email.',
                            attemptCount: 2,
                            recipients: [
                                {
                                    role: 'TO',
                                    name: null,
                                    email: 'long-recipient-address@example.com',
                                },
                            ],
                            retryUrl: null,
                        },
                    ],
                }}
            />,
        );

        expect(screen.getByText('Failed')).toBeInTheDocument();
        expect(screen.getByText('PDF attached')).toBeInTheDocument();
        expect(screen.getByText('Attempts: 2')).toBeInTheDocument();
        expect(
            screen.getByText('The provider rejected the email.'),
        ).toBeInTheDocument();
        expect(screen.getByText(/long-recipient-address/)).toHaveClass(
            'break-words',
        );
    });

    it('blocks sending while the document editor has unsaved changes', () => {
        render(
            <DocumentDeliveryPanel
                delivery={delivery}
                labels={labels}
                documentDirty
            />,
        );

        expect(
            screen.getByRole('button', { name: 'Send email' }),
        ).toBeDisabled();
        expect(screen.getByText('Save changes before sending.')).toBeVisible();
    });
});
