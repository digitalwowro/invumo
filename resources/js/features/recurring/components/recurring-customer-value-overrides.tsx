import { TextField } from '@/components/app/form-field';
import { FormSection } from '@/components/app/form-section';
import { Grid } from '@/components/app/layout';
import { SelectField } from '@/components/app/select-field';
import type {
    RecurringInheritance,
    RecurringInheritanceProps,
    RecurringInheritanceTranslations,
    RecurringValueMode,
} from '@/types/recurring';

const NONE = '__NONE__';

type Props = Pick<
    RecurringInheritanceProps,
    'currencyOptions' | 'languageOptions' | 'taxPresetOptions'
> & {
    value: RecurringInheritance;
    labels: RecurringInheritanceTranslations;
    deliveryLabels: Record<'SECURE_LINK_ONLY' | 'ATTACH_PDF', string>;
    maxDayOffset: number;
    errors: Record<string, string>;
    onChange: (value: RecurringInheritance) => void;
};

export function RecurringCustomerValueOverrides(props: Props) {
    return (
        <FormSection
            title={props.labels.values_title}
            description={props.labels.values_description}
        >
            <Grid columns={2} gap="lg">
                <OverrideSelect
                    mode={props.value.currencyMode}
                    modeName="currency_mode"
                    modeLabel={props.labels.currency_mode}
                    value={props.value.currencyCode ?? ''}
                    valueName="currency_code"
                    valueLabel={props.labels.currency_mode}
                    options={props.currencyOptions}
                    labels={props.labels}
                    errors={props.errors}
                    onMode={(currencyMode) =>
                        props.onChange({ ...props.value, currencyMode })
                    }
                    onValue={(currencyCode) => {
                        const currency = props.currencyOptions.find(
                            (option) => option.value === currencyCode,
                        );
                        props.onChange({
                            ...props.value,
                            currencyCode,
                            currencyPrecision: currency?.precision ?? null,
                        });
                    }}
                />
                <OverrideSelect
                    mode={props.value.languageMode}
                    modeName="language_mode"
                    modeLabel={props.labels.language_mode}
                    value={props.value.documentLanguage ?? ''}
                    valueName="document_language"
                    valueLabel={props.labels.language_mode}
                    options={props.languageOptions}
                    labels={props.labels}
                    errors={props.errors}
                    onMode={(languageMode) =>
                        props.onChange({ ...props.value, languageMode })
                    }
                    onValue={(documentLanguage) =>
                        props.onChange({
                            ...props.value,
                            documentLanguage,
                        })
                    }
                />
                <div className="grid gap-3">
                    <ModeSelect
                        name="payment_term_mode"
                        label={props.labels.payment_term_mode}
                        value={props.value.paymentTermMode}
                        labels={props.labels}
                        error={props.errors['inheritance.payment_term_mode']}
                        onChange={(paymentTermMode) =>
                            props.onChange({
                                ...props.value,
                                paymentTermMode,
                            })
                        }
                    />
                    <TextField
                        label={props.labels.payment_term_days}
                        error={props.errors['inheritance.payment_term_days']}
                        input={{
                            type: 'number',
                            min: 0,
                            max: props.maxDayOffset,
                            disabled:
                                props.value.paymentTermMode !== 'EXPLICIT',
                            value: props.value.paymentTermDays ?? '',
                            onChange: (event) =>
                                props.onChange({
                                    ...props.value,
                                    paymentTermDays:
                                        event.target.value === ''
                                            ? null
                                            : Number(event.target.value),
                                }),
                        }}
                    />
                </div>
                <OverrideSelect
                    mode={props.value.taxMode}
                    modeName="tax_mode"
                    modeLabel={props.labels.tax_mode}
                    value={props.value.taxPresetId ?? NONE}
                    valueName="tax_preset_id"
                    valueLabel={props.labels.tax_mode}
                    options={[
                        { value: NONE, label: props.labels.none },
                        ...props.taxPresetOptions,
                    ]}
                    labels={props.labels}
                    errors={props.errors}
                    onMode={(taxMode) =>
                        props.onChange({ ...props.value, taxMode })
                    }
                    onValue={(value) =>
                        props.onChange({
                            ...props.value,
                            taxPresetId: value === NONE ? null : value,
                        })
                    }
                />
                <OverrideSelect
                    mode={props.value.deliveryMode}
                    modeName="delivery_mode"
                    modeLabel={props.labels.delivery_mode}
                    value={props.value.emailAttachmentMode}
                    valueName="email_attachment_mode"
                    valueLabel={props.labels.delivery_mode}
                    options={Object.entries(props.deliveryLabels).map(
                        ([value, label]) => ({ value, label }),
                    )}
                    labels={props.labels}
                    errors={props.errors}
                    onMode={(deliveryMode) =>
                        props.onChange({ ...props.value, deliveryMode })
                    }
                    onValue={(value) =>
                        props.onChange({
                            ...props.value,
                            emailAttachmentMode: value as
                                'SECURE_LINK_ONLY' | 'ATTACH_PDF',
                        })
                    }
                />
            </Grid>
        </FormSection>
    );
}

type OverrideSelectProps = {
    mode: RecurringValueMode;
    modeName: string;
    modeLabel: string;
    value: string;
    valueName: string;
    valueLabel: string;
    options: Array<{ value: string; label: string }>;
    labels: RecurringInheritanceTranslations;
    errors: Record<string, string>;
    onMode: (mode: RecurringValueMode) => void;
    onValue: (value: string) => void;
};

function OverrideSelect(props: OverrideSelectProps) {
    return (
        <div className="grid gap-3">
            <ModeSelect
                name={props.modeName}
                label={props.modeLabel}
                value={props.mode}
                labels={props.labels}
                error={props.errors[`inheritance.${props.modeName}`]}
                onChange={props.onMode}
            />
            <SelectField
                name={`inheritance.${props.valueName}`}
                label={props.valueLabel}
                value={props.value}
                disabled={props.mode !== 'EXPLICIT'}
                options={props.options}
                error={props.errors[`inheritance.${props.valueName}`]}
                onValueChange={props.onValue}
            />
        </div>
    );
}

function ModeSelect(props: {
    name: string;
    label: string;
    value: RecurringValueMode;
    labels: RecurringInheritanceTranslations;
    error?: string;
    onChange: (mode: RecurringValueMode) => void;
}) {
    return (
        <SelectField
            name={`inheritance.${props.name}`}
            label={props.label}
            value={props.value}
            options={[
                { value: 'INHERIT', label: props.labels.inherit },
                { value: 'EXPLICIT', label: props.labels.explicit },
            ]}
            error={props.error}
            onValueChange={(value) =>
                props.onChange(value as RecurringValueMode)
            }
        />
    );
}
