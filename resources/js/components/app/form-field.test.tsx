import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { TextareaField } from '@/components/app/form-field';

describe('TextareaField', () => {
    it('associates localized guidance and validation with the textarea', () => {
        render(
            <TextareaField
                label="Terms & Conditions"
                description="Shown on future documents."
                error="Review this content."
                textarea={{
                    name: 'default_terms_and_conditions',
                    defaultValue: 'Payment is due on receipt.',
                    maxLength: 20_000,
                }}
            />,
        );

        const textarea = screen.getByRole('textbox', {
            name: 'Terms & Conditions',
        });

        expect(textarea).toHaveValue('Payment is due on receipt.');
        expect(textarea).toHaveAccessibleDescription(
            'Shown on future documents. Review this content.',
        );
        expect(textarea).toHaveAttribute('aria-invalid', 'true');
        expect(textarea).toHaveAttribute('maxlength', '20000');
    });
});
