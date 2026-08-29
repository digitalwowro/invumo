import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { GuardedActionDialog } from '@/components/app/guarded-action-dialog';

vi.stubGlobal(
    'ResizeObserver',
    class {
        observe() {}
        unobserve() {}
        disconnect() {}
    },
);

const copy = {
    triggerLabel: 'Delete permanently',
    title: 'Delete this record?',
    description: 'This cannot be undone.',
    confirmLabel: 'Delete record',
    cancelLabel: 'Cancel',
    closeLabel: 'Close',
    warningTitle: 'Deletion is blocked',
};

describe('GuardedActionDialog', () => {
    it('shows the resolved dependency warning and disables confirmation', async () => {
        const user = userEvent.setup();
        const confirm = vi.fn();
        render(
            <GuardedActionDialog
                {...copy}
                guard={{
                    blocked: true,
                    description: 'Document references — 2.',
                }}
                onConfirm={confirm}
            />,
        );

        await user.click(
            screen.getByRole('button', { name: copy.triggerLabel }),
        );
        expect(screen.getByText(copy.warningTitle)).toBeInTheDocument();
        expect(
            screen.getByText('Document references — 2.'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: copy.confirmLabel }),
        ).toBeDisabled();
        expect(confirm).not.toHaveBeenCalled();
    });

    it('runs the action when no dependency blocks it', async () => {
        const user = userEvent.setup();
        const confirm = vi.fn();
        render(
            <GuardedActionDialog
                {...copy}
                guard={{ blocked: false, description: null }}
                onConfirm={confirm}
            />,
        );

        await user.click(
            screen.getByRole('button', { name: copy.triggerLabel }),
        );
        await user.click(
            screen.getByRole('button', { name: copy.confirmLabel }),
        );
        expect(confirm).toHaveBeenCalledOnce();
    });
});
