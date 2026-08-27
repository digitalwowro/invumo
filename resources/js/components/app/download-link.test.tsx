import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { DownloadLink } from '@/components/app/download-link';

describe('DownloadLink', () => {
    it('uses a native download anchor for binary responses', () => {
        render(
            <DownloadLink href="/documents/1/pdf">Download PDF</DownloadLink>,
        );

        expect(
            screen.getByRole('link', { name: 'Download PDF' }),
        ).toHaveAttribute('download');
    });
});
