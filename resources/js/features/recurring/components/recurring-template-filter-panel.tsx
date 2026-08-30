import {
    OperationalActiveFilters,
    OperationalFilterChoiceRow,
    OperationalFilterPanel,
} from '@/components/app/operational-filter-panel';
import type { OperationalListTranslations } from '@/types/localization';
import type { RecurringTemplateFilters } from '@/types/recurring';
import type { RecurringTranslations } from '@/types/recurring-translations';

export function RecurringTemplateFilterPanel(props: {
    values: RecurringTemplateFilters;
    labels: RecurringTranslations['index'];
    commonLabels: OperationalListTranslations;
    onChange: (changes: Partial<RecurringTemplateFilters>) => void;
}) {
    const active = [
        props.values.q
            ? filter(
                  'q',
                  `${props.commonLabels.search_label}: ${props.values.q}`,
                  () => props.onChange({ q: '' }),
              )
            : null,
        props.values.state !== 'all'
            ? filter(
                  'state',
                  props.labels.state_filter_options[props.values.state],
                  () => props.onChange({ state: 'all' }),
              )
            : null,
        props.values.outcome !== 'all'
            ? filter(
                  'outcome',
                  props.labels.outcome_filter_options[props.values.outcome],
                  () => props.onChange({ outcome: 'all' }),
              )
            : null,
    ].filter((value) => value !== null);

    return (
        <OperationalFilterPanel>
            <OperationalFilterChoiceRow
                label={props.labels.state_filter_label}
                value={props.values.state}
                options={Object.entries(props.labels.state_filter_options).map(
                    ([value, label]) => ({ value, label }),
                )}
                onChange={(state) =>
                    props.onChange({
                        state: state as RecurringTemplateFilters['state'],
                    })
                }
            />
            <OperationalFilterChoiceRow
                label={props.labels.outcome_filter_label}
                value={props.values.outcome}
                options={Object.entries(
                    props.labels.outcome_filter_options,
                ).map(([value, label]) => ({ value, label }))}
                onChange={(outcome) =>
                    props.onChange({
                        outcome: outcome as RecurringTemplateFilters['outcome'],
                    })
                }
            />
            <OperationalActiveFilters
                filters={active}
                labels={props.commonLabels}
                onClear={() =>
                    props.onChange({ q: '', state: 'all', outcome: 'all' })
                }
            />
        </OperationalFilterPanel>
    );
}

const filter = (key: string, label: string, onRemove: () => void) => ({
    key,
    label,
    onRemove,
});
