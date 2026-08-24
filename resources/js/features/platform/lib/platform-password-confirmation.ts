export type PlatformPasswordConfirmationFailure =
    'incorrect' | 'rate-limited' | 'unavailable';

export class PlatformPasswordConfirmationError extends Error {
    constructor(readonly failure: PlatformPasswordConfirmationFailure) {
        super(failure);
        this.name = 'PlatformPasswordConfirmationError';
    }
}

export async function hasRecentPlatformPasswordConfirmation(
    statusUrl: string,
): Promise<boolean> {
    const response = await fetch(statusUrl, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
    });

    if (!response.ok) {
        throw new PlatformPasswordConfirmationError('unavailable');
    }

    const payload: unknown = await response.json();

    if (
        typeof payload !== 'object' ||
        payload === null ||
        !('confirmed' in payload) ||
        typeof payload.confirmed !== 'boolean'
    ) {
        throw new PlatformPasswordConfirmationError('unavailable');
    }

    return payload.confirmed;
}

export async function confirmPlatformPassword(
    confirmUrl: string,
    password: string,
): Promise<void> {
    const token = xsrfToken();
    const response = await fetch(confirmUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            ...(token ? { 'X-XSRF-TOKEN': token } : {}),
        },
        body: JSON.stringify({ password }),
    });

    if (response.status === 422) {
        throw new PlatformPasswordConfirmationError('incorrect');
    }

    if (response.status === 429) {
        throw new PlatformPasswordConfirmationError('rate-limited');
    }

    if (!response.ok) {
        throw new PlatformPasswordConfirmationError('unavailable');
    }
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
