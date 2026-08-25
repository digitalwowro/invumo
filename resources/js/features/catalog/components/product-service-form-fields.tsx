import { TextareaField, TextField } from '@/components/app/form-field';
import { Grid } from '@/components/app/layout';
import { SelectField } from '@/components/app/select-field';
import type {
    CatalogCurrencyOption,
    CatalogLimits,
    CatalogOption,
    CatalogTranslations,
    ProductServiceFormData,
} from '@/types/catalog';

const UNSET = '__UNSET__';

type Props = {
    data: ProductServiceFormData;
    errors: Partial<Record<keyof ProductServiceFormData, string>>;
    currencyOptions: CatalogCurrencyOption[];
    taxPresetOptions: CatalogOption[];
    periodOptions: CatalogOption[];
    limits: CatalogLimits;
    labels: CatalogTranslations['form'];
    onChange: <K extends keyof ProductServiceFormData>(
        field: K,
        value: ProductServiceFormData[K],
    ) => void;
};

export function ProductServiceFormFields({
    data,
    errors,
    currencyOptions,
    taxPresetOptions,
    periodOptions,
    limits,
    labels,
    onChange,
}: Props) {
    return (
        <div className="space-y-4">
            <Grid columns={2} gap="lg">
                <TextField
                    label={labels.fields.name}
                    error={errors.name}
                    input={{
                        value: data.name,
                        required: true,
                        maxLength: limits.name,
                        onChange: (event) =>
                            onChange('name', event.target.value),
                    }}
                />
                <TextField
                    label={labels.fields.internal_code}
                    error={errors.internal_code}
                    input={{
                        value: data.internal_code,
                        maxLength: limits.code,
                        onChange: (event) =>
                            onChange('internal_code', event.target.value),
                    }}
                />
            </Grid>
            <TextareaField
                label={labels.fields.description}
                description={labels.descriptions.description}
                error={errors.description}
                textarea={{
                    value: data.description,
                    maxLength: limits.description,
                    rows: 3,
                    onChange: (event) =>
                        onChange('description', event.target.value),
                }}
            />
            <Grid columns={2} gap="lg">
                <TextField
                    label={labels.fields.unit_price}
                    description={labels.descriptions.unit_price}
                    error={errors.unit_price}
                    input={{
                        value: data.unit_price,
                        inputMode: 'decimal',
                        onChange: (event) =>
                            onChange('unit_price', event.target.value),
                    }}
                />
                <SelectField
                    name="currency_id"
                    label={labels.fields.currency_id}
                    description={labels.descriptions.currency_id}
                    error={errors.currency_id}
                    value={data.currency_id || UNSET}
                    onValueChange={(value) =>
                        onChange('currency_id', value === UNSET ? '' : value)
                    }
                    options={[
                        { value: UNSET, label: labels.no_currency },
                        ...currencyOptions,
                    ]}
                />
                <TextField
                    label={labels.fields.unit}
                    error={errors.unit}
                    input={{
                        value: data.unit,
                        maxLength: limits.unit,
                        onChange: (event) =>
                            onChange('unit', event.target.value),
                    }}
                />
                <SelectField
                    name="period_unit"
                    label={labels.fields.period_unit}
                    error={errors.period_unit}
                    value={data.period_unit}
                    onValueChange={(value) =>
                        onChange(
                            'period_unit',
                            value as ProductServiceFormData['period_unit'],
                        )
                    }
                    options={periodOptions}
                />
            </Grid>
            <SelectField
                name="tax_preset_id"
                label={labels.fields.tax_preset_id}
                error={errors.tax_preset_id}
                value={data.tax_preset_id || UNSET}
                onValueChange={(value) =>
                    onChange('tax_preset_id', value === UNSET ? '' : value)
                }
                options={[
                    { value: UNSET, label: labels.no_tax },
                    ...taxPresetOptions,
                ]}
            />
        </div>
    );
}
