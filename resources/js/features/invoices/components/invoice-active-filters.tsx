import { OperationalActiveFilters } from '@/components/app/operational-filter-panel';
import type { InvoiceFilters, InvoiceTranslations } from '@/types/invoice';
import type { OperationalListTranslations } from '@/types/localization';

type Props = {
    values: InvoiceFilters;
    labels: InvoiceTranslations['index'];
    commonLabels: OperationalListTranslations;
    onChange: (changes: Partial<InvoiceFilters>) => void;
};

const clearFilters: Partial<InvoiceFilters> = {
    q: '',
    issueFrom: '',
    issueTo: '',
    dueFrom: '',
    dueTo: '',
    lifecycle: 'all',
    payment: 'all',
    overdue: 'all',
};

export function InvoiceActiveFilters({
    values,
    labels,
    commonLabels,
    onChange,
}: Props) {
    const active = activeFilters(values, labels, commonLabels);

    return (
        <OperationalActiveFilters
            filters={active.map((filter) => ({
                key: filter.key,
                label: filter.label,
                onRemove: () => onChange(filter.clear),
            }))}
            labels={commonLabels}
            onClear={() => onChange(clearFilters)}
        />
    );
}

function activeFilters(
    values: InvoiceFilters,
    labels: InvoiceTranslations['index'],
    commonLabels: OperationalListTranslations,
) {
    return [
        values.q
            ? {
                  key: 'q',
                  label: `${commonLabels.search_label}: ${values.q}`,
                  clear: { q: '' },
              }
            : null,
        values.lifecycle !== 'all'
            ? {
                  key: 'lifecycle',
                  label: labels.lifecycle_options[values.lifecycle],
                  clear: { lifecycle: 'all' as const },
              }
            : null,
        values.payment !== 'all'
            ? {
                  key: 'payment',
                  label: labels.payment_options[values.payment],
                  clear: { payment: 'all' as const },
              }
            : null,
        values.overdue !== 'all'
            ? {
                  key: 'overdue',
                  label: labels.overdue_options[values.overdue],
                  clear: { overdue: 'all' as const },
              }
            : null,
        values.issueFrom
            ? {
                  key: 'issueFrom',
                  label: `${labels.issue_from}: ${values.issueFrom}`,
                  clear: { issueFrom: '' },
              }
            : null,
        values.issueTo
            ? {
                  key: 'issueTo',
                  label: `${labels.issue_to}: ${values.issueTo}`,
                  clear: { issueTo: '' },
              }
            : null,
        values.dueFrom
            ? {
                  key: 'dueFrom',
                  label: `${labels.due_from}: ${values.dueFrom}`,
                  clear: { dueFrom: '' },
              }
            : null,
        values.dueTo
            ? {
                  key: 'dueTo',
                  label: `${labels.due_to}: ${values.dueTo}`,
                  clear: { dueTo: '' },
              }
            : null,
    ].filter((filter) => filter !== null);
}
