import {
    OperationalActiveFilters,
    OperationalFilterChoiceRow,
    OperationalFilterDateRow,
    OperationalFilterPanel,
} from '@/components/app/operational-filter-panel';
import type { OperationalListTranslations } from '@/types/localization';
import type {
    QuoteFilters,
    QuoteListDatePresets,
    QuoteTranslations,
} from '@/types/quote';

type Props = {
    values: QuoteFilters;
    presets: QuoteListDatePresets;
    labels: QuoteTranslations['index'];
    commonLabels: OperationalListTranslations;
    onChange: (changes: Partial<QuoteFilters>) => void;
};

const cleared: Partial<QuoteFilters> = {
    q: '',
    status: 'all',
    issueFrom: '',
    issueTo: '',
    validFrom: '',
    validTo: '',
};

export function QuoteFilterPanel(props: Props) {
    return (
        <OperationalFilterPanel>
            <OperationalFilterChoiceRow
                label={props.labels.status_label}
                value={props.values.status}
                options={entries(props.labels.status_options)}
                onChange={(status) =>
                    props.onChange({ status: status as QuoteFilters['status'] })
                }
            />
            <OperationalFilterDateRow
                label={props.labels.issue_date_label}
                fromLabel={props.labels.issue_from}
                toLabel={props.labels.issue_to}
                from={props.values.issueFrom}
                to={props.values.issueTo}
                preset={issuePreset(props.values, props.presets)}
                options={[
                    { value: 'any', label: props.labels.date_presets.any },
                    {
                        value: 'this_month',
                        label: props.labels.date_presets.this_month,
                    },
                    {
                        value: 'last_ninety_days',
                        label: props.labels.date_presets.last_ninety_days,
                    },
                ]}
                onPreset={(preset) => {
                    if (preset === 'this_month') {
                        props.onChange({
                            issueFrom: props.presets.monthStart,
                            issueTo: props.presets.today,
                        });
                    } else if (preset === 'last_ninety_days') {
                        props.onChange({
                            issueFrom: props.presets.ninetyDaysAgo,
                            issueTo: props.presets.today,
                        });
                    } else {
                        props.onChange({ issueFrom: '', issueTo: '' });
                    }
                }}
                onDates={(issueFrom, issueTo) =>
                    props.onChange({ issueFrom, issueTo })
                }
            />
            <OperationalFilterDateRow
                label={props.labels.deadline_date_label}
                fromLabel={props.labels.valid_from}
                toLabel={props.labels.valid_to}
                from={props.values.validFrom}
                to={props.values.validTo}
                preset={deadlinePreset(props.values, props.presets)}
                options={[
                    { value: 'any', label: props.labels.date_presets.any },
                    {
                        value: 'next_thirty_days',
                        label: props.labels.date_presets.next_thirty_days,
                    },
                    {
                        value: 'expired',
                        label: props.labels.date_presets.expired,
                    },
                ]}
                onPreset={(preset) => {
                    if (preset === 'next_thirty_days') {
                        props.onChange({
                            validFrom: props.presets.today,
                            validTo: props.presets.nextThirtyDays,
                        });
                    } else if (preset === 'expired') {
                        props.onChange({
                            validFrom: '',
                            validTo: props.presets.yesterday,
                        });
                    } else {
                        props.onChange({ validFrom: '', validTo: '' });
                    }
                }}
                onDates={(validFrom, validTo) =>
                    props.onChange({ validFrom, validTo })
                }
            />
            <OperationalActiveFilters
                filters={activeFilters(props)}
                labels={props.commonLabels}
                onClear={() => props.onChange(cleared)}
            />
        </OperationalFilterPanel>
    );
}

function activeFilters(props: Props) {
    const { values, labels, commonLabels, onChange } = props;

    return [
        values.q
            ? remove('q', `${commonLabels.search_label}: ${values.q}`, () =>
                  onChange({ q: '' }),
              )
            : null,
        values.status !== 'all'
            ? remove('status', labels.status_options[values.status], () =>
                  onChange({ status: 'all' }),
              )
            : null,
        values.issueFrom
            ? remove(
                  'issueFrom',
                  `${labels.issue_from}: ${values.issueFrom}`,
                  () => onChange({ issueFrom: '' }),
              )
            : null,
        values.issueTo
            ? remove('issueTo', `${labels.issue_to}: ${values.issueTo}`, () =>
                  onChange({ issueTo: '' }),
              )
            : null,
        values.validFrom
            ? remove(
                  'validFrom',
                  `${labels.valid_from}: ${values.validFrom}`,
                  () => onChange({ validFrom: '' }),
              )
            : null,
        values.validTo
            ? remove('validTo', `${labels.valid_to}: ${values.validTo}`, () =>
                  onChange({ validTo: '' }),
              )
            : null,
    ].filter((filter) => filter !== null);
}

function issuePreset(values: QuoteFilters, presets: QuoteListDatePresets) {
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

function deadlinePreset(values: QuoteFilters, presets: QuoteListDatePresets) {
    if (!values.validFrom && !values.validTo) {
return 'any';
}

    if (
        values.validFrom === presets.today &&
        values.validTo === presets.nextThirtyDays
    ) {
return 'next_thirty_days';
}

    if (!values.validFrom && values.validTo === presets.yesterday) {
return 'expired';
}

    return '';
}

const entries = (values: Record<string, string>) =>
    Object.entries(values).map(([value, label]) => ({ value, label }));
const remove = (key: string, label: string, onRemove: () => void) => ({
    key,
    label,
    onRemove,
});
