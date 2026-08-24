import { useState } from 'react';
import { FileUpload } from '@/components/app/file-upload';
import { Grid, Stack } from '@/components/app/layout';
import { SectionHeader } from '@/components/app/section-header';
import { Surface } from '@/components/app/surface';
import type { DesignSystemTranslations } from '@/types';

export function GalleryUploads({
    labels,
}: {
    labels: DesignSystemTranslations;
}) {
    const [selected, setSelected] = useState<File | null>(null);
    const shared = {
        name: 'logo',
        label: labels.upload.label,
        description: labels.upload.description,
        labels: labels.upload.controls,
        accept: 'image/png,image/jpeg,image/webp',
    } as const;

    return (
        <Stack gap="lg">
            <SectionHeader title={labels.sections.fileUpload} />
            <Surface>
                <Grid columns={2} gap="xl">
                    <FileUpload
                        {...shared}
                        id="gallery-upload-interactive"
                        value={selected}
                        onChange={setSelected}
                    />
                    <FileUpload
                        {...shared}
                        id="gallery-upload-progress"
                        value={null}
                        onChange={() => undefined}
                        uploading
                    />
                    <FileUpload
                        {...shared}
                        id="gallery-upload-error"
                        value={null}
                        onChange={() => undefined}
                        error={labels.upload.error}
                    />
                    <FileUpload
                        {...shared}
                        id="gallery-upload-success"
                        value={null}
                        onChange={() => undefined}
                        successMessage={labels.upload.success}
                    />
                </Grid>
            </Surface>
        </Stack>
    );
}
