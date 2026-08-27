import { Head, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { ActionLink } from '@/components/app/action-link';
import { FormActions } from '@/components/app/form-actions';
import { Cluster, Stack } from '@/components/app/layout';
import { PageFrame } from '@/components/app/page-frame';
import { PageHeader } from '@/components/app/page-header';
import { SystemMessage } from '@/components/app/system-message';
import { CustomerForm } from '@/components/domain/customers/customer-form';
import { StatusBadge } from '@/components/domain/status-badge';
import { CustomerLifecycleActions } from '@/features/customers/components/customer-lifecycle-actions';
import { CustomerWorkspaceNavigation } from '@/features/customers/components/customer-workspace-navigation';
import { interpolate } from '@/lib/translations';
import type {
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
                <Stack gap="2xl">
                    <PageHeader
                        title={customer.displayName}
                        subtitle={labels.description}
                        actions={
                            <Cluster gap="sm">
                                <StatusBadge
                                    status={
                                        customer.archived
                                            ? 'archived'
                                            : 'active'
                                    }
                                    label={
                                        customer.archived
                                            ? labels.archived
                                            : labels.active
                                    }
                                />
                                <ActionLink href={indexUrl} variant="secondary">
                                    <ArrowLeft aria-hidden="true" />
                                    {labels.back}
                                </ActionLink>
                            </Cluster>
                        }
                    />
                    <CustomerWorkspaceNavigation
                        active="overview"
                        overviewUrl={overviewUrl}
                        contactsUrl={contactsUrl}
                        defaultsUrl={defaultsUrl}
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
