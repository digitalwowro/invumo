import { afterEach, describe, expect, it, vi } from 'vitest';
import {
    confirmPlatformPassword,
    hasRecentPlatformPasswordConfirmation,
} from '@/features/platform/lib/platform-password-confirmation';
import type { PlatformPasswordConfirmationError } from '@/features/platform/lib/platform-password-confirmation';

describe('platform password confirmation', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
        document.cookie = 'XSRF-TOKEN=; Max-Age=0; path=/';
    });

    it('reads the server-owned confirmation window', async () => {
        const request = vi.fn().mockResolvedValue(
            new Response(JSON.stringify({ confirmed: true }), {
                status: 200,
                headers: { 'Content-Type': 'application/json' },
            }),
        );
        vi.stubGlobal('fetch', request);

        await expect(
            hasRecentPlatformPasswordConfirmation(
                '/platform/password-confirmation',
            ),
        ).resolves.toBe(true);
        expect(request).toHaveBeenCalledWith(
            '/platform/password-confirmation',
            expect.objectContaining({ credentials: 'same-origin' }),
        );
    });

    it('confirms through the CSRF-protected platform endpoint', async () => {
        document.cookie = 'XSRF-TOKEN=encoded%20token; path=/';
        const request = vi
            .fn()
            .mockResolvedValue(new Response('', { status: 201 }));
        vi.stubGlobal('fetch', request);

        await confirmPlatformPassword(
            '/platform/password-confirmation',
            'secret',
        );

        expect(request).toHaveBeenCalledWith(
            '/platform/password-confirmation',
            expect.objectContaining({
                method: 'POST',
                credentials: 'same-origin',
                headers: expect.objectContaining({
                    'X-XSRF-TOKEN': 'encoded token',
                }),
                body: JSON.stringify({ password: 'secret' }),
            }),
        );
    });

    it.each([
        [422, 'incorrect'],
        [429, 'rate-limited'],
        [503, 'unavailable'],
    ] as const)('maps a %s response to %s', async (status, failure) => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue(new Response('', { status })),
        );

        await expect(
            confirmPlatformPassword(
                '/platform/password-confirmation',
                'secret',
            ),
        ).rejects.toEqual(
            expect.objectContaining<Partial<PlatformPasswordConfirmationError>>(
                {
                    failure,
                },
            ),
        );
    });
});
