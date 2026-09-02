import { Head, usePage } from '@inertiajs/react';
import { Stack } from '@/components/app/layout';
import { ResourceWorkspace } from '@/components/app/resource-workspace';
import { SectionHeader } from '@/components/app/section-header';
import { SystemMessage } from '@/components/app/system-message';
import { CustomerContactCreateForm } from '@/features/customers/components/customer-contact-create-form';
import { CustomerContactTable } from '@/features/customers/components/customer-contact-table';
import { CustomerDeliveryForm } from '@/features/customers/components/customer-delivery-form';
import { CustomerWorkspaceNavigation } from '@/features/customers/components/customer-workspace-navigation';
import { interpolate } from '@/lib/translations';
import type {
    CustomerContact,
    CustomerDeliveryRecipient,
    CustomerFieldLimits,
    CustomerOption,
    CustomerTranslations,
} from '@/types/customer';

type Props = {
    customer: {
        id: string;
        displayName: string;
        archived: boolean;
        emailAttachmentMode: 'SECURE_LINK_ONLY' | 'ATTACH_PDF' | null;
    };
    contacts: CustomerContact[];
    recipients: CustomerDeliveryRecipient[];
    recipientContactOptions: CustomerOption[];
    emailAttachmentModeOptions: CustomerOption[];
    recipientRoleOptions: CustomerOption[];
    companyEmailAttachmentMode: 'SECURE_LINK_ONLY' | 'ATTACH_PDF';
    abilities: { manage: boolean; delete: boolean };
    overviewUrl: string;
    contactsUrl: string;
    defaultsUrl: string;
    indexUrl: string;
    storeContactUrl: string | null;
    updateDeliveryUrl: string | null;
    limits: CustomerFieldLimits;
    status?: string;
    translations: CustomerTranslations;
};

export default function CustomerContacts({
    customer,
    contacts,
    recipients,
    recipientContactOptions,
    emailAttachmentModeOptions,
    recipientRoleOptions,
    companyEmailAttachmentMode,
    overviewUrl,
    contactsUrl,
    defaultsUrl,
    indexUrl,
    storeContactUrl,
    updateDeliveryUrl,
    limits,
    status,
    translations,
}: Props) {
    const { i18n, errors } = usePage().props;
    const workspace = translations.workspace;
    const labels = translations.contacts;

    return (
        <>
            <Head
                title={interpolate(labels.head_title, {
                    name: customer.displayName,
                })}
            />
            <ResourceWorkspace>
                <Stack gap="xl">
                    <CustomerWorkspaceNavigation
                        active="contacts"
                        customerName={customer.displayName}
                        archived={customer.archived}
                        description={labels.description}
                        indexUrl={indexUrl}
                        indexLabel={translations.index.title}
                        overviewUrl={overviewUrl}
                        contactsUrl={contactsUrl}
                        defaultsUrl={defaultsUrl}
                        statusLabels={{
                            active: workspace.active,
                            archived: workspace.archived,
                        }}
                        label={workspace.navigation_label}
                        labels={workspace.navigation}
                    />
                    {status && <SystemMessage title={status} tone="money" />}
                    {errors.contact && (
                        <SystemMessage title={errors.contact} tone="error" />
                    )}
                    {customer.archived && (
                        <SystemMessage
                            title={workspace.archived_notice}
                            tone="warning"
                        />
                    )}
                    {storeContactUrl && (
                        <CustomerContactCreateForm
                            storeUrl={storeContactUrl}
                            limits={limits}
                            labels={labels}
                        />
                    )}
                    <Stack gap="lg">
                        <SectionHeader
                            title={labels.title}
                            description={labels.list_description}
                        />
                        <CustomerContactTable
                            contacts={contacts}
                            limits={limits}
                            labels={labels}
                            cancelLabel={i18n.common.actions.cancel}
                            closeLabel={
                                i18n.common.accessibility.close_navigation
                            }
                        />
                    </Stack>
                    <CustomerDeliveryForm
                        updateUrl={updateDeliveryUrl}
                        emailAttachmentMode={customer.emailAttachmentMode}
                        companyEmailAttachmentMode={companyEmailAttachmentMode}
                        recipients={recipients}
                        contactOptions={recipientContactOptions}
                        roleOptions={recipientRoleOptions}
                        modeOptions={emailAttachmentModeOptions}
                        limits={limits}
                        labels={translations.delivery}
                    />
                </Stack>
            </ResourceWorkspace>
        </>
    );
}
