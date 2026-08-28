import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
    return (
        <Card>
            <CardHeader>
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="min-w-0 space-y-1">
                        <CardTitle>{labels.title}</CardTitle>
                        <CardDescription>{labels.description}</CardDescription>
                    </div>
                    {delivery.composer && (
                        <DocumentDeliveryComposer
                            composer={delivery.composer}
                            limits={delivery.limits}
                            labels={labels.composer}
                            disabled={documentDirty}
                        />
                    )}
                </div>
            </CardHeader>
            <CardContent className="space-y-3">
                <h3 className="font-semibold text-foreground">
                    {labels.history.title}
                </h3>
                <DocumentDeliveryHistory
                    items={delivery.history}
                    locale={delivery.locale}
                    timezone={delivery.timezone}
                    labels={labels}
                />
            </CardContent>
        </Card>
    );
}
