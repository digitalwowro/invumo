import type {
    CustomerDeliveryRecipient,
    CustomerDeliveryRecipientForm,
} from '@/types/customer';

export function customerDeliveryRecipientForms(
    recipients: CustomerDeliveryRecipient[],
): CustomerDeliveryRecipientForm[] {
    return recipients.map((recipient) => ({
        key: recipient.id,
        role: recipient.role,
        source: recipient.contactId === null ? 'explicit' : 'contact',
        contact_id: recipient.contactId ?? '',
        explicit_name: recipient.explicitName ?? '',
        explicit_email: recipient.explicitEmail ?? '',
    }));
}

export function customerDeliveryPayload(
    emailAttachmentMode: string,
    recipients: CustomerDeliveryRecipientForm[],
) {
    return {
        email_attachment_mode:
            emailAttachmentMode === 'INHERIT' ? null : emailAttachmentMode,
        recipients: recipients.map((recipient) => ({
            role: recipient.role,
            contact_id:
                recipient.source === 'contact'
                    ? recipient.contact_id || null
                    : null,
            explicit_name:
                recipient.source === 'explicit'
                    ? recipient.explicit_name || null
                    : null,
            explicit_email:
                recipient.source === 'explicit'
                    ? recipient.explicit_email || null
                    : null,
        })),
    };
}
