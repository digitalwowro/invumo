import { Head, usePage } from '@inertiajs/react';
import { FormActions } from '@/components/app/form-actions';
import { Stack } from '@/components/app/layout';
import { PageFrame } from '@/components/app/page-frame';
import { SystemMessage } from '@/components/app/system-message';
import { CustomerForm } from '@/components/domain/customers/customer-form';
import { CustomerLifecycleActions } from '@/features/customers/components/customer-lifecycle-actions';
import { CustomerWorkspaceNavigation } from '@/features/customers/components/customer-workspace-navigation';
import { interpolate } from '@/lib/translations';
import type {
    CustomerDeleteGuard,
    CustomerFieldLimits,
    CustomerOption,
    CustomerRecord,
    CustomerTranslations,
} from '@/types/customer';

type Props = {
    customer: CustomerRecord & {
        id: string;
        displayName: string;
        archived: boolean;
    };
    abilities: { update: boolean; delete: boolean };
    indexUrl: string;
    overviewUrl: string;
    contactsUrl: string;
    defaultsUrl: string;
    updateUrl: string | null;
    archiveUrl: string | null;
    restoreUrl: string | null;
    deleteUrl: string | null;
    deleteGuard: CustomerDeleteGuard;
    publicDecisionIdentity: { count: number; eraseUrl: string | null };
    countryOptions: CustomerOption[];
    customerTypeOptions: CustomerOption[];
    limits: CustomerFieldLimits;
    status?: string;
    translations: CustomerTranslations;
};

export default function CustomerWorkspace({
    customer,
    indexUrl,
    overviewUrl,
    contactsUrl,
    defaultsUrl,
    updateUrl,
    archiveUrl,
    restoreUrl,
    deleteUrl,
    deleteGuard,
    publicDecisionIdentity,
    countryOptions,
    customerTypeOptions,
    limits,
    status,
    translations,
}: Props) {
    const { i18n, errors } = usePage().props;
    const labels = translations.workspace;

    return (
        <>
            <Head
                title={interpolate(labels.head_title, {
                    name: customer.displayName,
                })}
            />
            <PageFrame>
                <Stack gap="xl">
                    <CustomerWorkspaceNavigation
                        active="overview"
                        customerName={customer.displayName}
                        archived={customer.archived}
                        description={labels.description}
                        indexUrl={indexUrl}
                        indexLabel={translations.index.title}
                        overviewUrl={overviewUrl}
                        contactsUrl={contactsUrl}
                        defaultsUrl={defaultsUrl}
                        backLabel={labels.back}
                        statusLabels={{
                            active: labels.active,
                            archived: labels.archived,
                        }}
                        label={labels.navigation_label}
                        labels={labels.navigation}
                    />
                    {status && <SystemMessage title={status} tone="money" />}
                    {errors.customer && (
                        <SystemMessage title={errors.customer} tone="error" />
                    )}
                    {customer.archived && (
                        <SystemMessage
                            title={labels.archived_notice}
                            tone="warning"
                        />
                    )}
                    <CustomerForm
                        customer={customer}
                        actionUrl={updateUrl ?? indexUrl}
                        method="patch"
                        submitLabel={labels.save}
                        countryOptions={countryOptions}
                        customerTypeOptions={customerTypeOptions}
                        limits={limits}
                        labels={translations.form}
                        disabled={!updateUrl}
                        unsavedWarning={translations.form.unsaved_warning}
                    />
                    <FormActions separated>
                        <CustomerLifecycleActions
                            archiveUrl={archiveUrl}
                            restoreUrl={restoreUrl}
                            deleteUrl={deleteUrl}
                            deleteGuard={deleteGuard}
                            publicDecisionIdentity={publicDecisionIdentity}
                            labels={labels}
                            cancelLabel={i18n.common.actions.cancel}
                            closeLabel={
                                i18n.common.accessibility.close_navigation
                            }
                        />
                    </FormActions>
                </Stack>
            </PageFrame>
        </>
    );
}
