import { OperationalListSummary } from '@/components/app/operational-list-summary';
import { quoteListUrl } from '@/features/quotes/lib/quote-list-query';
import type { OperationalListTranslations } from '@/types/localization';
import type {
    QuoteFilters,
    QuoteListSummary,
    QuoteTranslations,
} from '@/types/quote';

type Key = keyof QuoteListSummary;

const cardFilters: Record<Key, QuoteFilters['status']> = {
    all: 'all',
    sent: 'SENT',
    accepted: 'ACCEPTED',
    expired: 'EXPIRED',
};

export function QuoteListSummaryCards(props: {
    action: string;
    filters: QuoteFilters;
    summary: QuoteListSummary;
    labels: QuoteTranslations['index'];
    commonLabels: OperationalListTranslations;
}) {
    return (
        <OperationalListSummary
            ariaLabel={props.labels.summary.aria_label}
            totalLabel={props.commonLabels.total}
            cards={(Object.keys(props.summary) as Key[]).map((key) => ({
                key,
                label: props.labels.summary[key],
                href: quoteListUrl(props.action, {
                    ...props.filters,
                    status: cardFilters[key],
                }),
                active: props.filters.status === cardFilters[key],
                tone:
                    key === 'expired'
                        ? 'danger'
                        : key === 'sent'
                          ? 'warning'
                          : key === 'accepted'
                            ? 'positive'
                            : 'neutral',
                value: props.summary[key],
            }))}
        />
    );
}
