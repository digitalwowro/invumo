import { router } from '@inertiajs/react';
import { GuardedActionDialog } from '@/components/app/guarded-action-dialog';
import { Cluster } from '@/components/app/layout';
import { OperationalTable } from '@/components/app/operational-table';
import type { OperationalTableStateCopy } from '@/components/app/operational-table';
import { ConfirmationDialog } from '@/components/app/responsive-dialog';
import { BodyStrong, TableValue } from '@/components/app/typography';
import { Badge } from '@/components/ui/badge';
import { CompanyCurrencyEditDialog } from '@/features/companies/components/company-currency-edit-dialog';
import { interpolate } from '@/lib/translations';
import type {
    CompanyCurrency,
    CompanyCurrencyTranslations,
} from '@/types/company-currency';

type Props = {
    currencies: CompanyCurrency[];
    labels: CompanyCurrencyTranslations;
    cancelLabel: string;
    closeLabel: string;
};

function stateCopy(labels: CompanyCurrencyTranslations) {
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

export function CompanyCurrencyTable({
    currencies,
    labels,
    cancelLabel,
    closeLabel,
}: Props) {
    return (
        <OperationalTable
            ariaLabel={labels.list_title}
            rows={currencies}
            rowKey={(currency) => currency.id}
            state={currencies.length === 0 ? 'empty' : 'ready'}
            stateCopy={stateCopy(labels)}
            columns={[
                {
                    key: 'code',
                    label: labels.code_column,
                    kind: 'identity',
                    render: (currency) => (
                        <BodyStrong>{currency.code}</BodyStrong>
                    ),
                },
                {
                    key: 'precision',
                    label: labels.precision_column,
                    kind: 'data',
                    render: (currency) => (
                        <TableValue>{currency.precision}</TableValue>
                    ),
                },
                {
                    key: 'default',
                    label: labels.default_column,
                    kind: 'status',
                    render: (currency) => (
                        <Badge
                            variant={currency.isDefault ? 'positive' : 'muted'}
                        >
                            {currency.isDefault
                                ? labels.default
                                : labels.not_default}
                        </Badge>
                    ),
                },
                {
                    key: 'status',
                    label: labels.status_column,
                    kind: 'status',
                    render: (currency) => (
                        <Badge variant={currency.active ? 'quiet' : 'muted'}>
                            {currency.active ? labels.active : labels.inactive}
                        </Badge>
                    ),
                },
                {
                    key: 'actions',
                    label: labels.actions_column,
                    kind: 'actions',
                    render: (currency) => (
                        <Cluster gap="sm">
                            {currency.updateUrl && (
                                <CompanyCurrencyEditDialog
                                    currency={currency}
                                    labels={labels}
                                    cancelLabel={cancelLabel}
                                    closeLabel={closeLabel}
                                />
                            )}
                            {currency.defaultUrl && (
                                <ConfirmationDialog
                                    tone="default"
                                    triggerLabel={labels.set_default}
                                    title={labels.set_default_title}
                                    description={interpolate(
                                        labels.set_default_description,
                                        { code: currency.code },
                                    )}
                                    confirmLabel={labels.confirm_default}
                                    cancelLabel={cancelLabel}
                                    closeLabel={closeLabel}
                                    onConfirm={() =>
                                        router.patch(
                                            currency.defaultUrl as string,
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                />
                            )}
                            {currency.deactivateUrl && (
                                <GuardedActionDialog
                                    tone="default"
                                    triggerLabel={labels.deactivate}
                                    title={labels.deactivate_title}
                                    description={interpolate(
                                        labels.deactivate_description,
                                        { code: currency.code },
                                    )}
                                    confirmLabel={labels.confirm_deactivate}
                                    cancelLabel={cancelLabel}
                                    closeLabel={closeLabel}
                                    warningTitle={
                                        labels.dependency_warning_title
                                    }
                                    guard={currency.deactivateGuard}
                                    onConfirm={() =>
                                        router.patch(
                                            currency.deactivateUrl as string,
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                />
                            )}
                            {currency.restoreUrl && (
                                <ConfirmationDialog
                                    tone="default"
                                    triggerLabel={labels.restore}
                                    title={labels.restore_title}
                                    description={interpolate(
                                        labels.restore_description,
                                        { code: currency.code },
                                    )}
                                    confirmLabel={labels.confirm_restore}
                                    cancelLabel={cancelLabel}
                                    closeLabel={closeLabel}
                                    onConfirm={() =>
                                        router.patch(
                                            currency.restoreUrl as string,
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
