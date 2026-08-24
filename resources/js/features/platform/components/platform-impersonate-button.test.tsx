import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { PlatformImpersonateButton } from '@/features/platform/components/platform-impersonate-button';
import type { PlatformTranslations } from '@/types';

const inertia = vi.hoisted(() => ({
    post: vi.fn(),
}));

vi.mock('@inertiajs/react', () => ({
    router: { post: inertia.post },
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
        show_password: 'Show password',
        hide_password: 'Hide password',
    },
} as unknown as PlatformTranslations;

describe('PlatformImpersonateButton', () => {
    beforeEach(() => {
        inertia.post.mockReset();
    });

    it('confirms the password and starts impersonation in one submission', async () => {
        const user = userEvent.setup();

        render(
            <PlatformImpersonateButton
                url="/platform/users/user-id/impersonation"
                targetName="Normal User"
                translations={translations}
            />,
        );

        await user.click(screen.getByRole('button', { name: 'Impersonate' }));

        expect(
            screen.getByRole('dialog', { name: 'Confirm impersonation' }),
        ).toHaveTextContent(
            'Enter your current password to continue as Normal User.',
        );

        await user.type(screen.getByLabelText('Current password'), 'secret');
        await user.click(screen.getByRole('button', { name: 'Impersonate' }));

        expect(inertia.post).toHaveBeenCalledOnce();
        expect(inertia.post).toHaveBeenCalledWith(
            '/platform/users/user-id/impersonation',
            { password: 'secret' },
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('keeps a rejected password inside the dialog for correction', async () => {
        const user = userEvent.setup();
        inertia.post.mockImplementation((_url, _data, options) => {
            options.onError({ password: 'The password is incorrect.' });
            options.onFinish();
        });

        render(
            <PlatformImpersonateButton
                url="/platform/users/user-id/impersonation"
                targetName="Normal User"
                translations={translations}
            />,
        );

        await user.click(screen.getByRole('button', { name: 'Impersonate' }));
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
