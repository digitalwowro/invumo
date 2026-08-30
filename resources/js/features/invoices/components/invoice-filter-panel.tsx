import {
    OperationalFilterChoiceRow,
    OperationalFilterDateRow,
    OperationalFilterPanel,
} from '@/components/app/operational-filter-panel';
import { InvoiceActiveFilters } from '@/features/invoices/components/invoice-active-filters';
import type {
    InvoiceFilters,
    InvoiceListDatePresets,
    InvoiceTranslations,
} from '@/types/invoice';
import type { OperationalListTranslations } from '@/types/localization';

type Props = {
    values: InvoiceFilters;
    presets: InvoiceListDatePresets;
    labels: InvoiceTranslations['index'];
    commonLabels: OperationalListTranslations;
    onChange: (changes: Partial<InvoiceFilters>) => void;
};

type Choice = { value: string; label: string };

export function InvoiceFilterPanel({
    values,
    presets,
    labels,
    commonLabels,
    onChange,
}: Props) {
    return (
        <OperationalFilterPanel>
            <OperationalFilterChoiceRow
                label={labels.lifecycle_label}
                value={values.lifecycle}
                options={entries(labels.lifecycle_options)}
                onChange={(lifecycle) =>
                    onChange({
                        lifecycle: lifecycle as InvoiceFilters['lifecycle'],
                    })
                }
            />
            <OperationalFilterChoiceRow
                label={labels.payment_label}
                value={values.payment}
                options={entries(labels.payment_options)}
                onChange={(payment) =>
                    onChange({
                        payment: payment as InvoiceFilters['payment'],
                    })
                }
            />
            <OperationalFilterChoiceRow
                label={labels.due_status_label}
                value={values.overdue}
                options={entries(labels.overdue_options)}
                onChange={(overdue) =>
                    onChange({
                        overdue: overdue as InvoiceFilters['overdue'],
                    })
                }
            />
            <OperationalFilterDateRow
                label={labels.issue_date_label}
                fromLabel={labels.issue_from}
                toLabel={labels.issue_to}
                from={values.issueFrom}
                to={values.issueTo}
                preset={issuePreset(values, presets)}
                options={[
                    { value: 'any', label: labels.date_presets.any },
                    {
                        value: 'this_month',
                        label: labels.date_presets.this_month,
                    },
                    {
                        value: 'last_ninety_days',
                        label: labels.date_presets.last_ninety_days,
                    },
                ]}
                onPreset={(preset) => {
                    if (preset === 'this_month') {
                        onChange({
                            issueFrom: presets.monthStart,
                            issueTo: presets.today,
                        });
                    } else if (preset === 'last_ninety_days') {
                        onChange({
                            issueFrom: presets.ninetyDaysAgo,
                            issueTo: presets.today,
                        });
                    } else {
                        onChange({ issueFrom: '', issueTo: '' });
                    }
                }}
                onDates={(issueFrom, issueTo) =>
                    onChange({ issueFrom, issueTo })
                }
            />
            <OperationalFilterDateRow
                label={labels.due_date_label}
                fromLabel={labels.due_from}
                toLabel={labels.due_to}
                from={values.dueFrom}
                to={values.dueTo}
                preset={duePreset(values, presets)}
                options={[
                    { value: 'any', label: labels.date_presets.any },
                    {
                        value: 'next_thirty_days',
                        label: labels.date_presets.next_thirty_days,
                    },
                    {
                        value: 'past_due',
                        label: labels.date_presets.past_due,
                    },
                ]}
                onPreset={(preset) => {
                    if (preset === 'next_thirty_days') {
                        onChange({
                            dueFrom: presets.today,
                            dueTo: presets.nextThirtyDays,
                        });
                    } else if (preset === 'past_due') {
                        onChange({ dueFrom: '', dueTo: presets.yesterday });
                    } else {
                        onChange({ dueFrom: '', dueTo: '' });
                    }
                }}
                onDates={(dueFrom, dueTo) => onChange({ dueFrom, dueTo })}
            />
            <InvoiceActiveFilters
                values={values}
                labels={labels}
                commonLabels={commonLabels}
                onChange={onChange}
            />
        </OperationalFilterPanel>
    );
}

function issuePreset(values: InvoiceFilters, presets: InvoiceListDatePresets) {
    if (!values.issueFrom && !values.issueTo) {
        return 'any';
    }

    if (
        values.issueFrom === presets.monthStart &&
        values.issueTo === presets.today
    ) {
        return 'this_month';
    }

    if (
        values.issueFrom === presets.ninetyDaysAgo &&
        values.issueTo === presets.today
    ) {
        return 'last_ninety_days';
    }

    return '';
}

function duePreset(values: InvoiceFilters, presets: InvoiceListDatePresets) {
    if (!values.dueFrom && !values.dueTo) {
        return 'any';
    }

    if (
        values.dueFrom === presets.today &&
        values.dueTo === presets.nextThirtyDays
    ) {
        return 'next_thirty_days';
    }

    if (!values.dueFrom && values.dueTo === presets.yesterday) {
        return 'past_due';
    }

    return '';
}

function entries(values: Record<string, string>): Choice[] {
    return Object.entries(values).map(([value, label]) => ({ value, label }));
}
