import { TextField } from '@/components/app/form-field';
import { Grid } from '@/components/app/layout';
import { Field, FieldDescription, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import {
    DEFAULT_OUTWARD_BRAND_COLOR,
    isOutwardBrandColor,
} from '@/domain/companies/outward-brand-theme';
import type {
    CompanyAppearanceTranslations,
    CompanyBrandColorPreset,
} from '@/types/company-appearance';

type Props = {
    value: string;
    presets: CompanyBrandColorPreset[];
    error?: string;
    labels: CompanyAppearanceTranslations;
    onChange: (value: string) => void;
};

export function BrandColorField({
    value,
    presets,
    error,
    labels,
    onChange,
}: Props) {
    const selectedPreset = presets.some((preset) => preset.value === value)
        ? value
        : '';
    const pickerValue = isOutwardBrandColor(value)
        ? value
        : DEFAULT_OUTWARD_BRAND_COLOR;

    return (
        <Field data-invalid={Boolean(error)}>
            <FieldLabel>{labels.color_label}</FieldLabel>
            <FieldDescription>{labels.color_description}</FieldDescription>
            <ToggleGroup
                type="single"
                value={selectedPreset}
                onValueChange={(next) => next && onChange(next)}
                variant="outline"
                spacing={2}
                aria-label={labels.preset_label}
                className="flex w-full flex-wrap justify-start"
            >
                {presets.map((preset) => (
                    <ToggleGroupItem
                        key={preset.value}
                        value={preset.value}
                        aria-label={preset.label}
                    >
                        <span
                            aria-hidden="true"
                            className="size-4 rounded-full border border-border-strong"
                            style={{ backgroundColor: preset.value }}
                        />
                        {preset.label}
                    </ToggleGroupItem>
                ))}
            </ToggleGroup>
            <Grid columns={2} gap="lg">
                <TextField
                    label={labels.custom_color_label}
                    error={error}
                    input={{
                        name: 'primary_brand_color',
                        value,
                        required: true,
                        maxLength: 7,
                        pattern: '#[0-9A-Fa-f]{6}',
                        autoCapitalize: 'characters',
                        spellCheck: false,
                        onChange: (event) =>
                            onChange(event.target.value.toUpperCase()),
                    }}
                />
                <Field>
                    <FieldLabel htmlFor="primary-brand-color-picker">
                        {labels.color_picker_label}
                    </FieldLabel>
                    <Input
                        id="primary-brand-color-picker"
                        type="color"
                        value={pickerValue}
                        onChange={(event) =>
                            onChange(event.target.value.toUpperCase())
                        }
                    />
                </Field>
            </Grid>
        </Field>
    );
}
