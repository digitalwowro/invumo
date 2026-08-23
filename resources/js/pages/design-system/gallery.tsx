import { Head } from '@inertiajs/react';
import { PageFrame } from '@/components/app/page-frame';
import { ComponentStateGallery } from '@/components/design-system/component-state-gallery';
import { useI18n } from '@/hooks/use-i18n';
import type { DesignSystemTranslations } from '@/types';

export default function DesignSystemGallery({
    gallery,
}: {
    gallery: DesignSystemTranslations;
}) {
    const { common } = useI18n();

    return (
        <>
            <Head title={gallery.page.title} />
            <PageFrame>
                <ComponentStateGallery
                    labels={gallery}
                    statusLabels={common.status}
                />
            </PageFrame>
        </>
    );
}
