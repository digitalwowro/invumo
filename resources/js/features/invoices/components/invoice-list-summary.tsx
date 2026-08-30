import { OperationalListSummary } from '@/components/app/operational-list-summary';
import { invoiceListUrl } from '@/features/invoices/lib/invoice-list-query';
import type {
    InvoiceFilters,
    InvoiceListSummary,
    InvoiceTranslations,
} from '@/types/invoice';
import type { OperationalListTranslations } from '@/types/localization';

type SummaryKey = keyof InvoiceListSummary;

type Props = {
    action: string;
    filters: InvoiceFilters;
    summary: InvoiceListSummary;
    labels: InvoiceTranslations['index'];
    commonLabels: OperationalListTranslations;
};

const cardFilters: Record<
    SummaryKey,
    Pick<InvoiceFilters, 'lifecycle' | 'payment' | 'overdue'>
> = {
    all: { lifecycle: 'all', payment: 'all', overdue: 'all' },
    awaiting: {
        lifecycle: 'ISSUED',
        payment: 'OUTSTANDING',
        overdue: 'all',
    },
    overdue: {
        lifecycle: 'ISSUED',
        payment: 'OUTSTANDING',
        overdue: 'overdue',
    },
    drafts: { lifecycle: 'DRAFT', payment: 'all', overdue: 'all' },
};

export function InvoiceListSummaryCards({
    action,
    filters,
    summary,
    labels,
    commonLabels,
}: Props) {
    return (
        <OperationalListSummary
            ariaLabel={labels.summary.aria_label}
            totalLabel={commonLabels.total}
            cards={(Object.keys(summary) as SummaryKey[]).map((key) => {
                const target = { ...filters, ...cardFilters[key] };

                return {
                    key,
                    label: labels.summary[key],
                    href: invoiceListUrl(action, target),
                    active: matchesCard(filters, key),
                    tone:
                        key === 'overdue'
                            ? ('danger' as const)
                            : key === 'awaiting'
                              ? ('warning' as const)
                              : ('neutral' as const),
                    value: summary[key],
                };
            })}
        />
    );
}

function matchesCard(filters: InvoiceFilters, key: SummaryKey) {
    const target = cardFilters[key];

    return (
        filters.lifecycle === target.lifecycle &&
        filters.payment === target.payment &&
        filters.overdue === target.overdue
    );
}
