import { TextField } from '@/components/app/form-field';
import { FormSection } from '@/components/app/form-section';
import { Grid } from '@/components/app/layout';
import { SelectField } from '@/components/app/select-field';
import { DocumentNumberPreview } from '@/components/domain/document-number-preview';
import { renderDocumentNumberPreview } from '@/lib/documents/document-number-pattern';
import type { CompanyOption } from '@/types/company';
import type {
    CompanyNumberPreviewContext,
    CompanyNumberSeries,
    CompanyNumberSeriesLimits,
    CompanyNumberSeriesTranslations,
} from '@/types/company-number-series';

type SeriesKey = 'quote' | 'invoice';

type Props = {
    seriesKey: SeriesKey;
    value: CompanyNumberSeries;
    onChange: (value: CompanyNumberSeries) => void;
    limits: CompanyNumberSeriesLimits;
    previewContext: CompanyNumberPreviewContext;
    resetPolicyOptions: CompanyOption[];
    errors: Record<string, string>;
    labels: CompanyNumberSeriesTranslations;
};

export function CompanyNumberSeriesFields({
    seriesKey,
    value,
    onChange,
    limits,
    previewContext,
    resetPolicyOptions,
    errors,
    labels,
}: Props) {
    const fieldPrefix = seriesKey === 'quote' ? 'quote' : 'invoice';
    const preview = renderDocumentNumberPreview({
        pattern: value.pattern,
        padding: value.padding,
        year: previewContext.year,
        sequence: previewContext.sequence,
    });
    const timezoneMissing =
        value.pattern.includes('{YEAR}') && previewContext.year === null;

    return (
        <FormSection
            title={
                seriesKey === 'quote'
                    ? labels.quote_title
                    : labels.invoice_title
            }
            description={
                seriesKey === 'quote'
                    ? labels.quote_description
                    : labels.invoice_description
            }
        >
            <Grid columns={3} gap="lg">
                <TextField
                    id={`${seriesKey}_pattern`}
                    label={labels.fields[`${fieldPrefix}_pattern`]}
                    description={labels.pattern_description}
                    error={errors[`${seriesKey}.pattern`]}
                    input={{
                        name: `${seriesKey}[pattern]`,
                        value: value.pattern,
                        maxLength: limits.patternCharacters,
                        onChange: (event) =>
                            onChange({ ...value, pattern: event.target.value }),
                        required: true,
                        autoComplete: 'off',
                    }}
                />
                <TextField
                    id={`${seriesKey}_padding`}
                    label={labels.fields[`${fieldPrefix}_padding`]}
                    description={labels.padding_description}
                    error={errors[`${seriesKey}.padding`]}
                    input={{
                        type: 'number',
                        name: `${seriesKey}[padding]`,
                        value: value.padding,
                        min: limits.minimumPadding,
                        max: limits.maximumPadding,
                        step: 1,
                        inputMode: 'numeric',
                        onChange: (event) =>
                            onChange({ ...value, padding: event.target.value }),
                        required: true,
                    }}
                />
                <SelectField
                    id={`${seriesKey}_reset_policy`}
                    name={`${seriesKey}[reset_policy]`}
                    label={labels.fields[`${fieldPrefix}_reset_policy`]}
                    description={labels.reset_policy_description}
                    error={errors[`${seriesKey}.reset_policy`]}
                    value={value.resetPolicy}
                    onValueChange={(resetPolicy) =>
                        onChange({
                            ...value,
                            resetPolicy:
                                resetPolicy as CompanyNumberSeries['resetPolicy'],
                        })
                    }
                    required
                    options={resetPolicyOptions}
                />
            </Grid>
            <DocumentNumberPreview
                label={labels.preview_label}
                value={preview}
                unavailableMessage={
                    timezoneMissing
                        ? labels.preview_timezone_required
                        : labels.preview_invalid
                }
            />
        </FormSection>
    );
}
