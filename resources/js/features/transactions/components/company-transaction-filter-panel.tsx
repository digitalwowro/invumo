import {
    OperationalActiveFilters,
    OperationalFilterChoiceRow,
    OperationalFilterDateRow,
    OperationalFilterPanel,
} from '@/components/app/operational-filter-panel';
import type {
    CompanyTransactionFilters,
    CompanyTransactionListDatePresets,
    CompanyTransactionTranslations,
} from '@/types/company-transaction';
import type { OperationalListTranslations } from '@/types/localization';

type Props = {
    values: CompanyTransactionFilters;
    presets: CompanyTransactionListDatePresets;
    labels: CompanyTransactionTranslations;
    commonLabels: OperationalListTranslations;
    onChange: (changes: Partial<CompanyTransactionFilters>) => void;
};

export function CompanyTransactionFilterPanel(props: Props) {
    const preset = datePreset(props.values, props.presets);
    const active = [
        props.values.q
            ? filter(
                  'q',
                  `${props.commonLabels.search_label}: ${props.values.q}`,
                  () => props.onChange({ q: '' }),
              )
            : null,
        props.values.kind !== 'all'
            ? filter('kind', props.labels.kind_options[props.values.kind], () =>
                  props.onChange({ kind: 'all' }),
              )
            : null,
        props.values.dateFrom
            ? filter(
                  'dateFrom',
                  `${props.labels.date_from}: ${props.values.dateFrom}`,
                  () => props.onChange({ dateFrom: '' }),
              )
            : null,
        props.values.dateTo
            ? filter(
                  'dateTo',
                  `${props.labels.date_to}: ${props.values.dateTo}`,
                  () => props.onChange({ dateTo: '' }),
              )
            : null,
    ].filter((value) => value !== null);

    return (
        <OperationalFilterPanel>
            <OperationalFilterChoiceRow
                label={props.labels.kind_label}
                value={props.values.kind}
                options={Object.entries(props.labels.kind_options).map(
                    ([value, label]) => ({ value, label }),
                )}
                onChange={(kind) =>
                    props.onChange({
                        kind: kind as CompanyTransactionFilters['kind'],
                    })
                }
            />
            <OperationalFilterDateRow
                label={props.labels.date_label}
                fromLabel={props.labels.date_from}
                toLabel={props.labels.date_to}
                from={props.values.dateFrom}
                to={props.values.dateTo}
                preset={preset}
                options={Object.entries(props.labels.date_presets).map(
                    ([value, label]) => ({ value, label }),
                )}
                onPreset={(value) => {
                    if (value === 'this_month') {
                        props.onChange({
                            dateFrom: props.presets.monthStart,
                            dateTo: props.presets.today,
                        });
                    } else if (value === 'last_ninety_days') {
                        props.onChange({
                            dateFrom: props.presets.ninetyDaysAgo,
                            dateTo: props.presets.today,
                        });
                    } else {
                        props.onChange({ dateFrom: '', dateTo: '' });
                    }
                }}
                onDates={(dateFrom, dateTo) =>
                    props.onChange({ dateFrom, dateTo })
                }
            />
            <OperationalActiveFilters
                filters={active}
                labels={props.commonLabels}
                onClear={() =>
                    props.onChange({
                        q: '',
                        kind: 'all',
                        dateFrom: '',
                        dateTo: '',
                    })
                }
            />
        </OperationalFilterPanel>
    );
}

function datePreset(
    values: CompanyTransactionFilters,
    presets: CompanyTransactionListDatePresets,
) {
    if (!values.dateFrom && !values.dateTo) {
        return 'any';
    }

    if (
        values.dateFrom === presets.monthStart &&
        values.dateTo === presets.today
    ) {
        return 'this_month';
    }

    if (
        values.dateFrom === presets.ninetyDaysAgo &&
        values.dateTo === presets.today
    ) {
        return 'last_ninety_days';
    }

    return '';
}

const filter = (key: string, label: string, onRemove: () => void) => ({
    key,
    label,
    onRemove,
});
