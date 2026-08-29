export type DeliveryRecipientRole = 'TO' | 'CC' | 'BCC';
export type DeliveryAttachmentMode = 'SECURE_LINK_ONLY' | 'ATTACH_PDF';
export type DeliveryState =
    'QUEUED' | 'RETRYING' | 'ACCEPTED' | 'REJECTED' | 'UNKNOWN';
export type DeliveryEventType =
    'QUOTE_SENT' | 'INVOICE_SENT' | 'PAYMENT_RECEIVED';
export type DeliveryProviderEventType =
    | 'DELIVERED'
    | 'SOFT_BOUNCED'
    | 'HARD_BOUNCED'
    | 'OPENED'
    | 'CLICKED'
    | 'FEEDBACK_LOOP';

export type DeliveryRecipient = {
    role: DeliveryRecipientRole;
    name: string | null;
    email: string;
};

export type DeliveryComposer = {
    deliveryKey: string;
    sendUrl: string;
    editVersion: number;
    language: string;
    attachmentMode: DeliveryAttachmentMode;
    recipients: DeliveryRecipient[];
    subject: string;
    body: string;
    buttonLabel: string;
    signature: string | null;
    requiresFinalStateConfirmation: boolean;
};

export type DeliveryHistoryItem = {
    id: string;
    eventType: DeliveryEventType;
    state: DeliveryState;
    subject: string | null;
    attachmentMode: DeliveryAttachmentMode | null;
    createdAt: string | null;
    acceptedAt: string | null;
    failureSummary: string | null;
    attemptCount: number;
    providerEvents: {
        type: DeliveryProviderEventType;
        occurredAt: string;
    }[];
    recipients: DeliveryRecipient[];
    retryUrl: string | null;
};

export type DocumentDelivery = {
    locale: string;
    timezone: string;
    composer: DeliveryComposer | null;
    history: DeliveryHistoryItem[];
    limits: {
        subject: number;
        body: number;
        buttonLabel: number;
        signature: number;
        recipients: number;
    };
};

export type DocumentDeliveryTranslations = {
    title: string;
    description: string;
    send: string;
    composer: {
        title: string;
        description: string;
        subject: string;
        body: string;
        button_label: string;
        signature: string;
        attachment_mode: string;
        modes: Record<DeliveryAttachmentMode, string>;
        recipients: string;
        recipient_name: string;
        recipient_email: string;
        recipient_role: string;
        roles: Record<DeliveryRecipientRole, string>;
        add_recipient: string;
        remove_recipient: string;
        final_state_warning: string;
        final_state_confirm: string;
        unsaved_warning: string;
        cancel: string;
        submit: string;
        close: string;
    };
    history: {
        title: string;
        empty: string;
        sent_at: string;
        attempts: string;
        recipients: string;
        attachment: string;
        provider_reported: string;
        provider_events: Record<DeliveryProviderEventType, string>;
        events: Record<DeliveryEventType, string>;
        statuses: Record<DeliveryState, string>;
        retry: string;
        retry_title: string;
        retry_warning: string;
        retry_confirm: string;
        retry_cancel: string;
    };
};
