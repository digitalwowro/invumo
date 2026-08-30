import { OperationalListSummary } from '@/components/app/operational-list-summary';
import { recurringTemplateListUrl } from '@/features/recurring/lib/recurring-template-list-query';
import type { OperationalListTranslations } from '@/types/localization';
import type {
    RecurringTemplateFilters,
    RecurringTemplateListSummary,
} from '@/types/recurring';
import type { RecurringTranslations } from '@/types/recurring-translations';

type Key = keyof RecurringTemplateListSummary;

const cardFilters: Record<
    Key,
    Pick<RecurringTemplateFilters, 'state' | 'outcome'>
> = {
    all: { state: 'all', outcome: 'all' },
    active: { state: 'ACTIVE', outcome: 'all' },
    paused: { state: 'PAUSED', outcome: 'all' },
    attention: { state: 'all', outcome: 'failed' },
};

export function RecurringTemplateListSummaryCards(props: {
    action: string;
    filters: RecurringTemplateFilters;
    summary: RecurringTemplateListSummary;
    labels: RecurringTranslations['index'];
    commonLabels: OperationalListTranslations;
}) {
    return (
        <OperationalListSummary
            ariaLabel={props.labels.summary.aria_label}
            totalLabel={props.commonLabels.total}
            cards={(Object.keys(props.summary) as Key[]).map((key) => {
                const target = { ...props.filters, ...cardFilters[key] };

                return {
                    key,
                    label: props.labels.summary[key],
                    href: recurringTemplateListUrl(props.action, target),
                    active:
                        props.filters.state === target.state &&
                        props.filters.outcome === target.outcome,
                    tone: key === 'attention' ? 'danger' : 'neutral',
                    value: props.summary[key],
                };
            })}
        />
    );
}
