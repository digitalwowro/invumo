import type {
    CompanyEmailTemplateFormData,
    RenderedEmailTemplate,
} from '@/types/company-email-template';

export class EmailTemplatePreviewError extends Error {
    constructor(readonly errors: Record<string, string>) {
        super('Email template preview failed.');
        this.name = 'EmailTemplatePreviewError';
    }
}

export async function requestEmailTemplatePreview(
    url: string,
    data: CompanyEmailTemplateFormData,
): Promise<RenderedEmailTemplate> {
    const token = xsrfToken();
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            ...(token ? { 'X-XSRF-TOKEN': token } : {}),
        },
        body: JSON.stringify(data),
    });

    if (response.status === 422) {
        const payload: unknown = await response.json();
        const errors =
            typeof payload === 'object' &&
            payload !== null &&
            'errors' in payload &&
            typeof payload.errors === 'object' &&
            payload.errors !== null
                ? singleErrors(payload.errors)
                : {};

        throw new EmailTemplatePreviewError(errors);
    }

    if (!response.ok) {
        throw new EmailTemplatePreviewError({});
    }

    return (await response.json()) as RenderedEmailTemplate;
}

function singleErrors(errors: object): Record<string, string> {
    const result: Record<string, string> = {};

    for (const [field, messages] of Object.entries(errors)) {
        if (Array.isArray(messages) && typeof messages[0] === 'string') {
            result[field] = messages[0];
        }
    }

    return result;
}

function xsrfToken(): string | undefined {
    const prefix = 'XSRF-TOKEN=';
    const cookie = document.cookie
        .split('; ')
        .find((candidate) => candidate.startsWith(prefix));

    if (!cookie) {
        return undefined;
    }

    try {
        return decodeURIComponent(cookie.slice(prefix.length));
    } catch {
        return undefined;
    }
}
