import { TextareaField } from '@/components/app/form-field';
import { FormSection } from '@/components/app/form-section';
import { Grid } from '@/components/app/layout';
import { SelectField } from '@/components/app/select-field';
import { RecurringReminderOverrides } from '@/features/recurring/components/recurring-reminder-overrides';
import type {
    RecurringInheritance,
    RecurringInheritanceProps,
    RecurringInheritanceTranslations,
    RecurringValueMode,
} from '@/types/recurring';

const NONE = '__NONE__';

type Props = Pick<
    RecurringInheritanceProps,
    'bankAccountOptions' | 'reminderRelationOptions'
> & {
    value: RecurringInheritance;
    labels: RecurringInheritanceTranslations;
    termsLabel: string;
    notesLabel: string;
    bankLabel: string;
    termsLimit: number;
    notesLimit: number;
    maxDayOffset: number;
    errors: Record<string, string>;
    onChange: (value: RecurringInheritance) => void;
};

export function RecurringCompanyOverrides(props: Props) {
    return (
        <>
            <FormSection
                title={props.labels.content_title}
                description={props.labels.content_description}
            >
                <Grid columns={2} gap="lg">
                    <ModeSelect
                        name="terms_mode"
                        label={props.labels.terms_mode}
                        value={props.value.termsMode}
                        labels={props.labels}
                        onChange={(termsMode) =>
                            props.onChange({ ...props.value, termsMode })
                        }
                    />
                    <ModeSelect
                        name="notes_mode"
                        label={props.labels.notes_mode}
                        value={props.value.notesMode}
                        labels={props.labels}
                        onChange={(notesMode) =>
                            props.onChange({ ...props.value, notesMode })
                        }
                    />
                </Grid>
                <Grid columns={2} gap="lg">
                    <TextareaField
                        label={props.termsLabel}
                        error={props.errors['inheritance.terms_and_conditions']}
                        textarea={{
                            value: props.value.termsAndConditions ?? '',
                            maxLength: props.termsLimit,
                            rows: 5,
                            disabled: props.value.termsMode !== 'EXPLICIT',
                            onChange: (event) =>
                                props.onChange({
                                    ...props.value,
                                    termsAndConditions: event.target.value,
                                }),
                        }}
                    />
                    <TextareaField
                        label={props.notesLabel}
                        error={props.errors['inheritance.notes']}
                        textarea={{
                            value: props.value.notes ?? '',
                            maxLength: props.notesLimit,
                            rows: 5,
                            disabled: props.value.notesMode !== 'EXPLICIT',
                            onChange: (event) =>
                                props.onChange({
                                    ...props.value,
                                    notes: event.target.value,
                                }),
                        }}
                    />
                </Grid>
                <Grid columns={2} gap="lg">
                    <ModeSelect
                        name="bank_mode"
                        label={props.labels.bank_mode}
                        value={props.value.bankMode}
                        labels={props.labels}
                        onChange={(bankMode) =>
                            props.onChange({ ...props.value, bankMode })
                        }
                    />
                    <SelectField
                        name="inheritance.bank_account_id"
                        label={props.bankLabel}
                        value={props.value.bankAccountId ?? NONE}
                        disabled={props.value.bankMode !== 'EXPLICIT'}
                        options={[
                            { value: NONE, label: props.labels.none },
                            ...props.bankAccountOptions,
                        ]}
                        error={props.errors['inheritance.bank_account_id']}
                        onValueChange={(value) =>
                            props.onChange({
                                ...props.value,
                                bankAccountId: value === NONE ? null : value,
                            })
                        }
                    />
                </Grid>
            </FormSection>
            <FormSection
                title={props.labels.reminders_title}
                description={props.labels.reminders_description}
            >
                <RecurringReminderOverrides
                    value={props.value}
                    labels={props.labels}
                    relationOptions={props.reminderRelationOptions}
                    maxDayOffset={props.maxDayOffset}
                    errors={props.errors}
                    onChange={props.onChange}
                />
            </FormSection>
        </>
    );
}

function ModeSelect(props: {
    name: string;
    label: string;
    value: RecurringValueMode;
    labels: RecurringInheritanceTranslations;
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
            onValueChange={(value) =>
                props.onChange(value as RecurringValueMode)
            }
        />
    );
}
