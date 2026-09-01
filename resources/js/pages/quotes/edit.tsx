import { Head } from '@inertiajs/react';
import { DocumentDeliveryPanel } from '@/features/delivery/components/document-delivery-panel';
import { QuoteWorkspace } from '@/features/quotes/components/quote-workspace';
import type { QuoteEditPageProps } from '@/features/quotes/components/quote-workspace-types';

export default function EditQuote(props: QuoteEditPageProps) {
    return (
        <>
            <Head
                title={`${props.translations.edit.head_title} ${props.quote.number}`}
            />
            <QuoteWorkspace
                {...props}
                renderDeliveryPanel={(documentDirty) => (
                    <DocumentDeliveryPanel
                        delivery={props.directDelivery}
                        labels={props.deliveryTranslations}
                        documentDirty={documentDirty}
                    />
                )}
            />
        </>
    );
}
