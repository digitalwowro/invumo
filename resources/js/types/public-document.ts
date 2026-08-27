export type PublicDocumentLinkStatus =
    'ACTIVE' | 'DISABLED' | 'EXPIRED' | 'NOT_CREATED';

export type PublicDocumentLink = {
    status: PublicDocumentLinkStatus;
    url: string | null;
    expiresAt: string | null;
    locale: string;
    timezone: string;
    createUrl: string;
    revokeUrl: string | null;
    regenerateUrl: string | null;
};

export type PublicDocumentTranslations = {
    management: {
        title: string;
        description: string;
        statuses: Record<PublicDocumentLinkStatus, string>;
        expires: string;
        copy: string;
        copied: string;
        copy_failed: string;
        create: string;
        re_enable: string;
        regenerate: string;
        revoke: string;
    };
    page: {
        head_title: string;
        description: string;
        download_pdf: string;
        provided_by: string;
    };
    decision: {
        title: string;
        description: string;
        customer_name: string;
        customer_email: string;
        accept: string;
        reject: string;
        accepted_title: string;
        accepted_description: string;
        rejected_title: string;
        rejected_description: string;
        unavailable_title: string;
        unavailable_description: string;
    };
    feedback: {
        created: string;
        regenerated: string;
        revoked: string;
    };
    errors: {
        unavailable: string;
        decision_unavailable: string;
        decision_conflict: string;
        idempotency_conflict: string;
    };
};

export type PublicQuoteDecisionState = {
    state: 'AVAILABLE' | 'ACCEPTED' | 'REJECTED' | 'UNAVAILABLE';
    submitUrl: string | null;
    idempotencyKey: string | null;
    locale: string;
    csrfToken: string;
    customerName: string;
    customerEmail: string;
};
