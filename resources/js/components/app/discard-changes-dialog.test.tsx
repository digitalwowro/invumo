import { fireEvent, render, screen } from '@testing-library/react';
import type { FormEvent } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { DiscardChangesDialog } from '@/components/app/discard-changes-dialog';
import type { DocumentResetLabels } from '@/types/document';

const labels: DocumentResetLabels = {
    discard_changes: 'Discard changes',
    discard_changes_title: 'Discard unsaved changes?',
    discard_changes_description: 'Return to the last saved state.',
    clear_draft: 'Clear draft',
    clear_draft_title: 'Clear this draft?',
    clear_draft_description: 'Return to the initial values.',
    keep_editing: 'Keep editing',
};

describe('DiscardChangesDialog', () => {
    it('confirms a reset against the owning form', () => {
        const onReset = vi.fn((event: FormEvent) => event.preventDefault());

        render(
            <>
                <form id="document-form" onReset={onReset} />
                <DiscardChangesDialog
                    dirty
                    processing={false}
                    form="document-form"
                    mode="discard"
                    labels={labels}
                />
            </>,
        );

        fireEvent.click(
            screen.getByRole('button', { name: 'Discard changes' }),
        );
        expect(screen.getByText('Discard unsaved changes?')).toBeVisible();
        fireEvent.click(
            screen.getByRole('button', { name: 'Discard changes' }),
        );

        expect(onReset).toHaveBeenCalledOnce();
    });

    it('uses clear-draft copy and remains disabled while clean', () => {
        render(
            <DiscardChangesDialog
                dirty={false}
                processing={false}
                form="document-form"
                mode="clear"
                labels={labels}
            />,
        );

        expect(
            screen.getByRole('button', { name: 'Clear draft' }),
        ).toBeDisabled();
    });
});
