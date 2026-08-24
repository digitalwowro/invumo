import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { FileUpload } from '@/components/app/file-upload';

const labels = {
    dropPrompt: 'Drop a logo here or choose a file',
    choose: 'Choose image',
    replace: 'Replace image',
    remove: 'Remove image',
    selected: 'Selected',
    uploading: 'Uploading image',
};

function Harness() {
    const [file, setFile] = useState<File | null>(null);

    return (
        <FileUpload
            id="company-logo"
            name="logo"
            label="Company logo"
            description="PNG, JPEG, or WebP up to 5 MiB."
            labels={labels}
            value={file}
            onChange={setFile}
            accept="image/png,image/jpeg,image/webp"
        />
    );
}

describe('FileUpload', () => {
    it('selects, replaces, and removes a file through one shared control', async () => {
        const user = userEvent.setup();
        render(<Harness />);

        const input = screen.getByLabelText('Company logo');
        const first = new File(['logo'], 'logo.png', { type: 'image/png' });

        await user.upload(input, first);

        expect(screen.getByText('Selected: logo.png')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Replace image' }),
        ).toBeEnabled();

        await user.click(screen.getByRole('button', { name: 'Remove image' }));

        expect(
            screen.queryByText('Selected: logo.png'),
        ).not.toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Choose image' }),
        ).toBeEnabled();
    });

    it('exposes uploading, validation-error, and success states', () => {
        const onChange = vi.fn();
        const file = new File(['logo'], 'logo.webp', { type: 'image/webp' });
        const { rerender } = render(
            <FileUpload
                id="company-logo"
                name="logo"
                label="Company logo"
                labels={labels}
                value={file}
                onChange={onChange}
                uploading
            />,
        );

        expect(screen.getByText('Uploading image')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Replace image' }),
        ).toBeDisabled();

        rerender(
            <FileUpload
                id="company-logo"
                name="logo"
                label="Company logo"
                labels={labels}
                value={file}
                onChange={onChange}
                error="The image is too large."
            />,
        );

        expect(screen.getByRole('alert')).toHaveTextContent(
            'The image is too large.',
        );

        rerender(
            <FileUpload
                id="company-logo"
                name="logo"
                label="Company logo"
                labels={labels}
                value={file}
                onChange={onChange}
                successMessage="Image ready"
            />,
        );

        expect(screen.getByRole('status')).toHaveTextContent('Image ready');
    });
});
