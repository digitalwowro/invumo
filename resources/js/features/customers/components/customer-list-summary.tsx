import { OperationalListSummary } from '@/components/app/operational-list-summary';
import { customerListUrl } from '@/features/customers/lib/customer-list-query';
import type {
    CustomerFilters,
    CustomerListSummary,
    CustomerTranslations,
} from '@/types/customer';
import type { OperationalListTranslations } from '@/types/localization';

type Key = keyof CustomerListSummary;

const cardFilters: Record<Key, CustomerFilters['status']> = {
    all: 'all',
    active: 'active',
    archived: 'archived',
};
const cardOrder: Key[] = ['active', 'all', 'archived'];

export function CustomerListSummaryCards(props: {
    action: string;
    filters: CustomerFilters;
    summary: CustomerListSummary;
    labels: CustomerTranslations['index'];
    commonLabels: OperationalListTranslations;
}) {
    return (
        <OperationalListSummary
            ariaLabel={props.labels.summary.aria_label}
            totalLabel={props.commonLabels.total}
            cards={cardOrder.map((key) => ({
                key,
                label: props.labels.summary[key],
                href: customerListUrl(props.action, {
                    ...props.filters,
                    status: cardFilters[key],
                }),
                active: props.filters.status === cardFilters[key],
                tone: key === 'active' ? 'positive' : 'neutral',
                value: props.summary[key],
            }))}
        />
    );
}
