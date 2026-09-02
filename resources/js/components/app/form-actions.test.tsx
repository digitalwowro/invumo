import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { SaveButton } from '@/components/app/form-actions';

describe('SaveButton', () => {
    it('enables saving only after the form changes', () => {
        const { rerender } = render(
            <SaveButton dirty={false}>Save changes</SaveButton>,
        );

        expect(screen.getByRole('button')).toBeDisabled();

        rerender(<SaveButton dirty>Save changes</SaveButton>);

        expect(
            screen.getByRole('button', { name: 'Save changes' }),
        ).toBeEnabled();
    });

    it('remains disabled while a changed form is saving', () => {
        render(
            <SaveButton dirty processing>
                Save changes
            </SaveButton>,
        );

        expect(screen.getByRole('button')).toBeDisabled();
    });
});
