import { ContentSection } from '@/components/app/content-section';
import { FactStrip } from '@/components/app/fact-strip';
import { DocumentDeliveryComposer } from '@/features/delivery/components/document-delivery-composer';
import { DocumentDeliveryHistory } from '@/features/delivery/components/document-delivery-history';
import type {
    DocumentDelivery,
    DocumentDeliveryTranslations,
} from '@/types/document-delivery';

type Props = {
    delivery: DocumentDelivery;
    labels: DocumentDeliveryTranslations;
    documentDirty?: boolean;
};

export function DocumentDeliveryPanel({
    delivery,
    labels,
    documentDirty = false,
}: Props) {
    const composer = delivery.composer;

    return (
        <ContentSection
            title={labels.title}
            description={labels.description}
            headerActions={
                composer ? (
                    <DocumentDeliveryComposer
                        composer={composer}
                        limits={delivery.limits}
                        labels={labels.composer}
                        disabled={documentDirty}
                    />
                ) : undefined
            }
        >
            {composer && (
                <FactStrip
                    tone="subtle"
                    className="border-b border-divider"
                    facts={[
                        {
                            label: labels.composer.recipients,
                            value: String(composer.recipients.length),
                        },
                        {
                            label: labels.composer.attachment_mode,
                            value: labels.composer.modes[
                                composer.attachmentMode
                            ],
                        },
                        {
                            label: labels.composer.language,
                            value: composer.language.toUpperCase(),
                        },
                    ]}
                />
            )}
            <section className="flex flex-col gap-3 p-5 sm:p-6">
                <h3 className="font-semibold text-foreground">
                    {labels.history.title}
                </h3>
                <DocumentDeliveryHistory
                    items={delivery.history}
                    locale={delivery.locale}
                    timezone={delivery.timezone}
                    labels={labels}
                    embedded
                />
            </section>
        </ContentSection>
    );
}
