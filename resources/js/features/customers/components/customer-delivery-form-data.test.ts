import { describe, expect, it } from 'vitest';
import {
    customerDeliveryPayload,
    customerDeliveryRecipientForms,
} from '@/features/customers/components/customer-delivery-form-data';

describe('customer delivery form data', () => {
    it('maps stored contact and explicit recipients into editable rows', () => {
        expect(
            customerDeliveryRecipientForms([
                {
                    id: 'contact-recipient',
                    role: 'TO',
                    contactId: 'contact-id',
                    explicitName: null,
                    explicitEmail: null,
                },
                {
                    id: 'explicit-recipient',
                    role: 'CC',
                    contactId: null,
                    explicitName: 'Accounts',
                    explicitEmail: 'accounts@example.com',
                },
            ]),
        ).toEqual([
            {
                key: 'contact-recipient',
                role: 'TO',
                source: 'contact',
                contact_id: 'contact-id',
                explicit_name: '',
                explicit_email: '',
            },
            {
                key: 'explicit-recipient',
                role: 'CC',
                source: 'explicit',
                contact_id: '',
                explicit_name: 'Accounts',
                explicit_email: 'accounts@example.com',
            },
        ]);
    });

    it('sends inheritance as null and only the selected recipient source', () => {
        expect(
            customerDeliveryPayload('INHERIT', [
                {
                    key: 'one',
                    role: 'TO',
                    source: 'contact',
                    contact_id: 'contact-id',
                    explicit_name: 'ignored',
                    explicit_email: 'ignored@example.com',
                },
                {
                    key: 'two',
                    role: 'BCC',
                    source: 'explicit',
                    contact_id: 'ignored-contact',
                    explicit_name: '',
                    explicit_email: 'bcc@example.com',
                },
            ]),
        ).toEqual({
            email_attachment_mode: null,
            recipients: [
                {
                    role: 'TO',
                    contact_id: 'contact-id',
                    explicit_name: null,
                    explicit_email: null,
                },
                {
                    role: 'BCC',
                    contact_id: null,
                    explicit_name: null,
                    explicit_email: 'bcc@example.com',
                },
            ],
        });
    });
});
