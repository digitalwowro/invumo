import {
    OperationalActiveFilters,
    OperationalFilterChoiceRow,
    OperationalFilterPanel,
    OperationalFilterSelectRow,
} from '@/components/app/operational-filter-panel';
import type {
    CustomerFilters,
    CustomerOption,
    CustomerTranslations,
} from '@/types/customer';
import type { OperationalListTranslations } from '@/types/localization';

export function CustomerFilterPanel(props: {
    values: CustomerFilters;
    countryOptions: CustomerOption[];
    labels: CustomerTranslations['index'];
    commonLabels: OperationalListTranslations;
    onChange: (changes: Partial<CustomerFilters>) => void;
}) {
    const active = [
        props.values.q
            ? filter(
                  'q',
                  `${props.commonLabels.search_label}: ${props.values.q}`,
                  () => props.onChange({ q: '' }),
              )
            : null,
        props.values.status !== 'active'
            ? filter(
                  'status',
                  props.labels.status_options[props.values.status],
                  () => props.onChange({ status: 'active' }),
              )
            : null,
        props.values.country
            ? filter(
                  'country',
                  `${props.labels.country_label}: ${props.values.country}`,
                  () => props.onChange({ country: null }),
              )
            : null,
    ].filter((value) => value !== null);

    return (
        <OperationalFilterPanel>
            <OperationalFilterChoiceRow
                label={props.labels.status_label}
                value={props.values.status}
                options={Object.entries(props.labels.status_options).map(
                    ([value, label]) => ({ value, label }),
                )}
                onChange={(status) =>
                    props.onChange({
                        status: status as CustomerFilters['status'],
                    })
                }
            />
            <OperationalFilterSelectRow
                label={props.labels.country_label}
                value={props.values.country ?? 'ALL'}
                options={[
                    { value: 'ALL', label: props.labels.all_countries },
                    ...props.countryOptions,
                ]}
                onChange={(country) =>
                    props.onChange({
                        country: country === 'ALL' ? null : country,
                    })
                }
            />
            <OperationalActiveFilters
                filters={active}
                labels={props.commonLabels}
                onClear={() =>
                    props.onChange({ q: '', status: 'active', country: null })
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
