import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ImpersonationBanner } from '@/components/app/impersonation-banner';

const inertia = vi.hoisted(() => ({
    delete: vi.fn(),
}));

vi.mock('@inertiajs/react', () => ({
    router: { delete: inertia.delete },
    usePage: () => ({
        props: {
            impersonation: {
                active: true,
                user: {
                    name: 'Ana Popescu',
                    email: 'ana@example.com',
                },
                message: 'You are impersonating Ana Popescu (ana@example.com).',
                exitLabel: 'Exit impersonation',
                exitUrl: '/impersonation',
            },
        },
    }),
}));

describe('ImpersonationBanner', () => {
    beforeEach(() => {
        inertia.delete.mockReset();
    });

    it('keeps the selected identity visible and exits in one action', async () => {
        const user = userEvent.setup();

        render(<ImpersonationBanner />);

        expect(screen.getByRole('status')).toHaveTextContent(
            'You are impersonating Ana Popescu (ana@example.com).',
        );
        expect(screen.getByRole('status')).toHaveClass(
            'bg-background',
            'border-warning-fill',
        );

        await user.click(
            screen.getByRole('button', { name: 'Exit impersonation' }),
        );

        expect(inertia.delete).toHaveBeenCalledOnce();
        expect(inertia.delete).toHaveBeenCalledWith(
            '/impersonation',
            expect.any(Object),
        );
    });
});
