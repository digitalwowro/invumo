import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { EmailTemplatePreview } from '@/features/delivery/components/email-template-preview';

describe('EmailTemplatePreview', () => {
    it('renders authored values as text without interpreting markup', () => {
        const { container } = render(
            <EmailTemplatePreview
                title="Resolved preview"
                description="No email is sent."
                override
                overrideLabel="Company override"
                systemLabel="System default"
                preview={{
                    subject: '<script>subject()</script>',
                    body: '<img src=x onerror=body()>',
                    buttonLabel: '<b>Open</b>',
                    signature: '<svg onload=signature()>',
                    buttonUrl: 'https://example.test/private',
                }}
            />,
        );

        expect(
            screen.getByText('<script>subject()</script>'),
        ).toBeInTheDocument();
        expect(
            screen.getByText('<img src=x onerror=body()>'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: '<b>Open</b>' }),
        ).toBeDisabled();
        expect(container.querySelector('script')).toBeNull();
        expect(container.querySelector('img')).toBeNull();
        expect(container.querySelector('svg')).toBeNull();
        expect(container.innerHTML).not.toContain(
            'https://example.test/private',
        );
    });
});
