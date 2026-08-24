import { Stack } from '@/components/app/layout';
import { PageHeader } from '@/components/app/page-header';
import { GalleryFoundations } from '@/components/design-system/gallery-foundations';
import { GalleryStates } from '@/components/design-system/gallery-states';
import { GalleryTable } from '@/components/design-system/gallery-table';
import { GalleryUploads } from '@/components/design-system/gallery-uploads';
import type {
    DesignSystemStatusLabels,
    DesignSystemTranslations,
} from '@/types';

type ComponentStateGalleryProps = {
    labels: DesignSystemTranslations;
    statusLabels: DesignSystemStatusLabels;
};

export function ComponentStateGallery({
    labels,
    statusLabels,
}: ComponentStateGalleryProps) {
    return (
        <Stack gap="2xl">
            <PageHeader
                title={labels.page.title}
                subtitle={labels.page.subtitle}
            />
            <GalleryFoundations labels={labels} />
            <GalleryUploads labels={labels} />
            <GalleryStates labels={labels} statusLabels={statusLabels} />
            <GalleryTable labels={labels} statusLabels={statusLabels} />
        </Stack>
    );
}

export type { ComponentStateGalleryProps };
