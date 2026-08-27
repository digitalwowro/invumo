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
    feedback: {
        created: string;
        regenerated: string;
        revoked: string;
    };
    errors: { unavailable: string };
};
