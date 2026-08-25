import { router } from '@inertiajs/react';
import { Cluster, Stack } from '@/components/app/layout';
import { OperationalTable } from '@/components/app/operational-table';
import type { OperationalTableStateCopy } from '@/components/app/operational-table';
import { ConfirmationDialog } from '@/components/app/responsive-dialog';
import {
    BodyStrong,
    SecondaryText,
    TableValue,
} from '@/components/app/typography';
import { BankValue } from '@/components/domain/bank-value';
import { Badge } from '@/components/ui/badge';
import { BankAccountEditDialog } from '@/features/companies/components/bank-account-edit-dialog';
import { interpolate } from '@/lib/translations';
import type { CompanyOption } from '@/types/company';
import type {
    BankAccount,
    BankRoutingField,
    CompanyBankAccountTranslations,
} from '@/types/company-bank-account';

type Props = {
    accounts: BankAccount[];
    currencyOptions: CompanyOption[];
    routingFields: BankRoutingField[];
    labels: CompanyBankAccountTranslations;
    cancelLabel: string;
    closeLabel: string;
};

function stateCopy(labels: CompanyBankAccountTranslations) {
    return {
        loading: '',
        emptyTitle: labels.empty_title,
        emptyDescription: labels.empty_description,
        noResultsTitle: '',
        noResultsDescription: '',
        errorTitle: '',
        errorDescription: '',
    } satisfies OperationalTableStateCopy;
}

export function BankAccountTable({
    accounts,
    currencyOptions,
    routingFields,
    labels,
    cancelLabel,
    closeLabel,
}: Props) {
    return (
        <OperationalTable
            ariaLabel={labels.list_title}
            rows={accounts}
            rowKey={(account) => account.id}
            state={accounts.length === 0 ? 'empty' : 'ready'}
            stateCopy={stateCopy(labels)}
            columns={[
                {
                    key: 'label',
                    label: labels.label_column,
                    kind: 'identity',
                    render: (account) => (
                        <BodyStrong>{account.label}</BodyStrong>
                    ),
                },
                {
                    key: 'bank',
                    label: labels.bank_column,
                    render: (account) => (
                        <Stack gap="xs">
                            <BodyStrong>{account.bankName}</BodyStrong>
                            <SecondaryText>
                                {account.accountHolder}
                            </SecondaryText>
                        </Stack>
                    ),
                },
                {
                    key: 'account',
                    label: labels.account_column,
                    kind: 'data',
                    render: (account) => (
                        <Stack gap="xs">
                            <BankValue
                                value={account.accountNumber}
                                copyLabel={labels.copy_value}
                                copiedLabel={labels.value_copied}
                            />
                            <BankValue
                                value={account.swiftBic}
                                copyLabel={labels.copy_value}
                                copiedLabel={labels.value_copied}
                            />
                        </Stack>
                    ),
                },
                {
                    key: 'currency',
                    label: labels.currency_column,
                    kind: 'data',
                    render: (account) => (
                        <TableValue>
                            {account.currencyCode ?? labels.no_currency}
                        </TableValue>
                    ),
                },
                {
                    key: 'default',
                    label: labels.default_column,
                    kind: 'status',
                    render: (account) => (
                        <Badge
                            variant={account.isDefault ? 'positive' : 'muted'}
                        >
                            {account.isDefault
                                ? labels.default
                                : labels.not_default}
                        </Badge>
                    ),
                },
                {
                    key: 'status',
                    label: labels.status_column,
                    kind: 'status',
                    render: (account) => (
                        <Badge variant={account.archived ? 'muted' : 'quiet'}>
                            {account.archived ? labels.archived : labels.active}
                        </Badge>
                    ),
                },
                {
                    key: 'actions',
                    label: labels.actions_column,
                    kind: 'actions',
                    render: (account) => (
                        <Cluster gap="sm">
                            {account.updateUrl && (
                                <BankAccountEditDialog
                                    account={account}
                                    currencyOptions={currencyOptions}
                                    routingFields={routingFields}
                                    labels={labels}
                                    cancelLabel={cancelLabel}
                                    closeLabel={closeLabel}
                                />
                            )}
                            {account.archiveUrl && (
                                <ConfirmationDialog
                                    triggerLabel={labels.archive}
                                    title={labels.archive_title}
                                    description={interpolate(
                                        labels.archive_description,
                                        { label: account.label },
                                    )}
                                    confirmLabel={labels.confirm_archive}
                                    cancelLabel={cancelLabel}
                                    closeLabel={closeLabel}
                                    onConfirm={() =>
                                        router.patch(
                                            account.archiveUrl as string,
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                />
                            )}
                        </Cluster>
                    ),
                },
            ]}
        />
    );
}
