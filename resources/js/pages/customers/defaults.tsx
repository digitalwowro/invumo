import { Head, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { ActionLink } from '@/components/app/action-link';
import { Cluster, Stack } from '@/components/app/layout';
import { PageFrame } from '@/components/app/page-frame';
import { PageHeader } from '@/components/app/page-header';
import { SystemMessage } from '@/components/app/system-message';
import { StatusBadge } from '@/components/domain/status-badge';
import { CustomerDefaultsForm } from '@/features/customers/components/customer-defaults-form';
import { CustomerResolvedDefaults } from '@/features/customers/components/customer-resolved-defaults';
import { CustomerWorkspaceNavigation } from '@/features/customers/components/customer-workspace-navigation';
import { interpolate } from '@/lib/translations';
import type { CustomerTranslations } from '@/types/customer';
import type {
    CustomerDefaultOption,
    CustomerDefaultsRecord,
    CustomerResolvedDefaults as ResolvedDefaults,
} from '@/types/customer-defaults';

type Props = {
    customer: { id: string; displayName: string; archived: boolean };
    defaults: CustomerDefaultsRecord;
    resolvedDefaults: ResolvedDefaults;
    currencyOptions: CustomerDefaultOption[];
    languageOptions: CustomerDefaultOption[];
    taxPresetOptions: CustomerDefaultOption[];
    companyPaymentTermDays: string | null;
    maxPaymentTermDays: number;
    updateUrl: string | null;
    indexUrl: string;
    overviewUrl: string;
    contactsUrl: string;
    defaultsUrl: string;
    status?: string;
    translations: CustomerTranslations;
};

export default function CustomerDefaults({
    customer,
    defaults,
    resolvedDefaults,
    currencyOptions,
    languageOptions,
    taxPresetOptions,
    companyPaymentTermDays,
    maxPaymentTermDays,
    updateUrl,
    indexUrl,
    overviewUrl,
    contactsUrl,
    defaultsUrl,
    status,
    translations,
}: Props) {
    const { errors } = usePage().props;
    const labels = translations.defaults;
    const workspace = translations.workspace;

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
                                            ? workspace.archived
                                            : workspace.active
                                    }
                                />
                                <ActionLink href={indexUrl} variant="secondary">
                                    <ArrowLeft aria-hidden="true" />
                                    {workspace.back}
                                </ActionLink>
                            </Cluster>
                        }
                    />
                    <CustomerWorkspaceNavigation
                        active="defaults"
                        overviewUrl={overviewUrl}
                        contactsUrl={contactsUrl}
                        defaultsUrl={defaultsUrl}
                        label={workspace.navigation_label}
                        labels={workspace.navigation}
                    />
                    {status && <SystemMessage title={status} tone="money" />}
                    {errors.defaults && (
                        <SystemMessage title={errors.defaults} tone="error" />
                    )}
                    {customer.archived && (
                        <SystemMessage
                            title={workspace.archived_notice}
                            tone="warning"
                        />
                    )}
                    <CustomerDefaultsForm
                        defaults={defaults}
                        currencyOptions={currencyOptions}
                        languageOptions={languageOptions}
                        taxPresetOptions={taxPresetOptions}
                        companyPaymentTermDays={companyPaymentTermDays}
                        maxPaymentTermDays={maxPaymentTermDays}
                        updateUrl={updateUrl}
                        labels={labels}
                    />
                    <CustomerResolvedDefaults
                        resolved={resolvedDefaults}
                        labels={labels}
                    />
                </Stack>
            </PageFrame>
        </>
    );
}
