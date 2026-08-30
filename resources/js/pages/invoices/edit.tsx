import { Head, usePage } from '@inertiajs/react';
import { DocumentDeliveryPanel } from '@/features/delivery/components/document-delivery-panel';
import { InvoiceReminderPanel } from '@/features/delivery/components/invoice-reminder-panel';
import { InvoiceWorkspace } from '@/features/invoices/components/invoice-workspace';
import type { InvoiceEditPageProps } from '@/features/invoices/components/invoice-workspace-types';

export default function EditInvoice(props: InvoiceEditPageProps) {
    const { i18n } = usePage().props;

    return (
        <>
            <Head
                title={`${props.translations.edit.head_title} ${props.invoice.number}`}
            />
            <InvoiceWorkspace
                {...props}
                renderDeliveryPanel={(documentDirty) => (
                    <DocumentDeliveryPanel
                        delivery={props.directDelivery}
                        labels={props.deliveryTranslations}
                        documentDirty={documentDirty}
                    />
                )}
                reminderPanel={
                    <InvoiceReminderPanel
                        reminders={props.reminders}
                        editVersion={props.invoice.editVersion}
                        locale={props.directDelivery.locale}
                        timezone={props.directDelivery.timezone}
                        closeLabel={i18n.common.accessibility.close_navigation}
                        labels={props.translations.reminders}
                    />
                }
            />
        </>
    );
}
