import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { PlatformImpersonateButton } from '@/features/platform/components/platform-impersonate-button';
import type { PlatformTranslations } from '@/types';

const inertia = vi.hoisted(() => ({
    post: vi.fn(),
}));
const reauthentication = vi.hoisted(() => {
    class ConfirmationError extends Error {
        constructor(readonly failure: string) {
            super(failure);
        }
    }

    return {
        status: vi.fn(),
        confirm: vi.fn(),
        ConfirmationError,
    };
});

vi.mock('@inertiajs/react', () => ({
    router: { post: inertia.post },
}));
vi.mock('@/features/platform/lib/platform-password-confirmation', () => ({
    hasRecentPlatformPasswordConfirmation: reauthentication.status,
    confirmPlatformPassword: reauthentication.confirm,
    PlatformPasswordConfirmationError: reauthentication.ConfirmationError,
}));

const translations = {
    common: {
        cancel: 'Cancel',
        close: 'Close',
    },
    users: {
        impersonate: 'Impersonate',
        impersonate_title: 'Confirm impersonation',
        impersonate_description:
            'Enter your current password to continue as :user.',
        password: 'Current password',
        password_placeholder: 'Password',
        password_incorrect: 'The password is incorrect.',
        password_rate_limited: 'Too many attempts.',
        password_unavailable: 'Confirmation unavailable.',
        show_password: 'Show password',
        hide_password: 'Hide password',
    },
} as unknown as PlatformTranslations;

describe('PlatformImpersonateButton', () => {
    beforeEach(() => {
        inertia.post.mockReset();
        reauthentication.status.mockReset().mockResolvedValue(false);
        reauthentication.confirm.mockReset().mockResolvedValue(undefined);
    });

    it('confirms the password and starts impersonation in one submission', async () => {
        const user = userEvent.setup();

        render(
            <PlatformImpersonateButton
                url="/platform/users/user-id/impersonation"
                targetName="Normal User"
                confirmationStatusUrl="/platform/password-confirmation"
                confirmationStoreUrl="/platform/password-confirmation"
                translations={translations}
            />,
        );

        await user.click(screen.getByRole('button', { name: 'Impersonate' }));

        expect(
            await screen.findByRole('dialog', {
                name: 'Confirm impersonation',
            }),
        ).toHaveTextContent(
            'Enter your current password to continue as Normal User.',
        );

        await user.type(screen.getByLabelText('Current password'), 'secret');
        await user.click(screen.getByRole('button', { name: 'Impersonate' }));

        await waitFor(() =>
            expect(reauthentication.confirm).toHaveBeenCalledWith(
                '/platform/password-confirmation',
                'secret',
            ),
        );
        expect(inertia.post).toHaveBeenCalledWith(
            '/platform/users/user-id/impersonation',
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('reuses an active confirmation window without asking again', async () => {
        const user = userEvent.setup();
        reauthentication.status.mockResolvedValue(true);

        render(
            <PlatformImpersonateButton
                url="/platform/users/user-id/impersonation"
                targetName="Normal User"
                confirmationStatusUrl="/platform/password-confirmation"
                confirmationStoreUrl="/platform/password-confirmation"
                translations={translations}
            />,
        );

        await user.click(screen.getByRole('button', { name: 'Impersonate' }));

        await waitFor(() => expect(inertia.post).toHaveBeenCalledOnce());
        expect(reauthentication.confirm).not.toHaveBeenCalled();
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });

    it('keeps a rejected password inside the dialog for correction', async () => {
        const user = userEvent.setup();
        reauthentication.confirm.mockRejectedValue(
            new reauthentication.ConfirmationError('incorrect'),
        );

        render(
            <PlatformImpersonateButton
                url="/platform/users/user-id/impersonation"
                targetName="Normal User"
                confirmationStatusUrl="/platform/password-confirmation"
                confirmationStoreUrl="/platform/password-confirmation"
                translations={translations}
            />,
        );

        await user.click(screen.getByRole('button', { name: 'Impersonate' }));
        await screen.findByRole('dialog', { name: 'Confirm impersonation' });
        await user.type(screen.getByLabelText('Current password'), 'wrong');
        await user.click(screen.getByRole('button', { name: 'Impersonate' }));

        expect(
            screen.getByText('The password is incorrect.'),
        ).toBeInTheDocument();
        expect(screen.getByLabelText('Current password')).toHaveValue('');
        expect(
            screen.getByRole('dialog', { name: 'Confirm impersonation' }),
        ).toBeInTheDocument();
    });
});
