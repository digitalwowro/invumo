import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { DestructiveActionDialog } from '@/components/app/destructive-action-dialog';

vi.stubGlobal(
    'ResizeObserver',
    class {
        observe() {}
        unobserve() {}
        disconnect() {}
    },
);

describe('DestructiveActionDialog', () => {
    it('shows a dependency and disables ordinary confirmation', () => {
        const confirm = vi.fn();
        render(
            <DestructiveActionDialog
                open
                onOpenChange={vi.fn()}
                triggerLabel="Delete invoice"
                title="Delete permanently?"
                description="This cannot be undone."
                cancelLabel="Cancel"
                confirmLabel="Delete permanently"
                closeLabel="Close"
                guard={{
                    blocked: true,
                    description: 'Transactions: 1.',
                }}
                warningTitle="Resolve dependencies"
                processing={false}
                onConfirm={confirm}
            />,
        );

        expect(screen.getByText('Resolve dependencies')).toBeInTheDocument();
        expect(screen.getByText('Transactions: 1.')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Delete permanently' }),
        ).toBeDisabled();
        expect(confirm).not.toHaveBeenCalled();
    });

    it('requires the exact value and acknowledgment in strong mode', async () => {
        const user = userEvent.setup();
        const confirm = vi.fn();
        const view = render(
            <DestructiveActionDialog
                open
                onOpenChange={vi.fn()}
                triggerLabel="Delete invoice"
                title="Delete permanently?"
                description="This cannot be undone."
                cancelLabel="Cancel"
                confirmLabel="Delete permanently"
                closeLabel="Close"
                processing={false}
                onConfirm={confirm}
                strongConfirmation={{
                    expectedValue: 'I-2026-0001',
                    value: '',
                    valueLabel: 'Invoice number',
                    valueDescription: 'Type the exact number.',
                    acknowledged: false,
                    acknowledgmentLabel: 'I understand.',
                    onValueChange: vi.fn(),
                    onAcknowledgmentChange: vi.fn(),
                }}
            />,
        );

        expect(
            screen.getByRole('button', { name: 'Delete permanently' }),
        ).toBeDisabled();
        view.rerender(
            <DestructiveActionDialog
                open
                onOpenChange={vi.fn()}
                triggerLabel="Delete invoice"
                title="Delete permanently?"
                description="This cannot be undone."
                cancelLabel="Cancel"
                confirmLabel="Delete permanently"
                closeLabel="Close"
                processing={false}
                onConfirm={confirm}
                strongConfirmation={{
                    expectedValue: 'I-2026-0001',
                    value: 'I-2026-0001',
                    valueLabel: 'Invoice number',
                    valueDescription: 'Type the exact number.',
                    acknowledged: true,
                    acknowledgmentLabel: 'I understand.',
                    onValueChange: vi.fn(),
                    onAcknowledgmentChange: vi.fn(),
                }}
            />,
        );
        await user.click(
            screen.getByRole('button', { name: 'Delete permanently' }),
        );
        expect(confirm).toHaveBeenCalledOnce();
    });
});
