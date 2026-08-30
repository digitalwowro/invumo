import { OperationalListSummary } from '@/components/app/operational-list-summary';
import { companyTransactionListUrl } from '@/features/transactions/lib/company-transaction-list-query';
import type {
    CompanyTransactionFilters,
    CompanyTransactionListSummary,
    CompanyTransactionTranslations,
} from '@/types/company-transaction';
import type { OperationalListTranslations } from '@/types/localization';

type Key = keyof CompanyTransactionListSummary;

const cardFilters: Record<Key, CompanyTransactionFilters['kind']> = {
    all: 'all',
    payments: 'PAYMENT',
    refunds: 'REFUND',
    adjustments: 'ADJUSTMENT',
};

export function CompanyTransactionListSummaryCards(props: {
    action: string;
    filters: CompanyTransactionFilters;
    summary: CompanyTransactionListSummary;
    labels: CompanyTransactionTranslations;
    commonLabels: OperationalListTranslations;
}) {
    return (
        <OperationalListSummary
            ariaLabel={props.labels.summary.aria_label}
            totalLabel={props.commonLabels.total}
            cards={(Object.keys(props.summary) as Key[]).map((key) => ({
                key,
                label: props.labels.summary[key],
                href: companyTransactionListUrl(props.action, {
                    ...props.filters,
                    kind: cardFilters[key],
                }),
                active: props.filters.kind === cardFilters[key],
                tone:
                    key === 'refunds'
                        ? 'warning'
                        : key === 'payments'
                          ? 'positive'
                          : 'neutral',
                value: props.summary[key],
            }))}
        />
    );
}
