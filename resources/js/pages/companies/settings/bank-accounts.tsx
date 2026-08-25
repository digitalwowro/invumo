import { Head, usePage } from '@inertiajs/react';
import { Stack } from '@/components/app/layout';
import { SectionHeader } from '@/components/app/section-header';
import { SystemMessage } from '@/components/app/system-message';
import { BankAccountCreateForm } from '@/features/companies/components/bank-account-create-form';
import { BankAccountTable } from '@/features/companies/components/bank-account-table';
import type { CompaniesUiTranslations, CompanyOption } from '@/types/company';
import type {
    BankAccount,
    BankRoutingField,
} from '@/types/company-bank-account';

type Props = {
    bankAccounts: BankAccount[];
    currencyOptions: CompanyOption[];
    routingFields: BankRoutingField[];
    storeUrl: string;
    status?: string;
    translations: CompaniesUiTranslations;
};

export default function CompanyBankAccounts({
    bankAccounts,
    currencyOptions,
    routingFields,
    storeUrl,
    status,
    translations,
}: Props) {
    const { i18n, errors } = usePage().props;
    const labels = translations.settings.bank_accounts;

    return (
        <>
            <Head title={labels.head_title} />
            <Stack gap="2xl">
                <SectionHeader
                    title={labels.title}
                    description={labels.description}
                />
                {status && <SystemMessage title={status} tone="money" />}
                {errors.bank_account && (
                    <SystemMessage title={errors.bank_account} tone="error" />
                )}
                <BankAccountCreateForm
                    storeUrl={storeUrl}
                    currencyOptions={currencyOptions}
                    routingFields={routingFields}
                    labels={labels}
                />
                <Stack gap="lg">
                    <SectionHeader
                        title={labels.list_title}
                        description={labels.list_description}
                    />
                    <BankAccountTable
                        accounts={bankAccounts}
                        currencyOptions={currencyOptions}
                        routingFields={routingFields}
                        labels={labels}
                        cancelLabel={i18n.common.actions.cancel}
                        closeLabel={i18n.common.accessibility.close_navigation}
                    />
                </Stack>
            </Stack>
        </>
    );
}
