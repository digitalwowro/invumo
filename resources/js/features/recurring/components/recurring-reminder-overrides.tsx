import { CheckboxField, TextField } from '@/components/app/form-field';
import { Grid, Stack } from '@/components/app/layout';
import { SectionHeader } from '@/components/app/section-header';
import { SelectField } from '@/components/app/select-field';
import { Surface } from '@/components/app/surface';
import { Button } from '@/components/ui/button';
import { interpolate } from '@/lib/translations';
import type {
    RecurringInheritance,
    RecurringInheritanceTranslations,
    RecurringReminderMode,
    RecurringReminderRule,
} from '@/types/recurring';

type Props = {
    value: RecurringInheritance;
    labels: RecurringInheritanceTranslations;
    relationOptions: Array<{ value: string; label: string }>;
    maxDayOffset: number;
    errors: Record<string, string>;
    onChange: (value: RecurringInheritance) => void;
};

export function RecurringReminderOverrides(props: Props) {
    const rules = props.value.reminderRules;
    const change = (reminderRules: RecurringReminderRule[]) =>
        props.onChange({ ...props.value, reminderRules });

    return (
        <Stack gap="lg">
            <SelectField
                name="inheritance.reminder_mode"
                label={props.labels.reminder_mode}
                value={props.value.reminderMode}
                options={[
                    {
                        value: 'INHERIT_COMPANY',
                        label: props.labels.reminder_inherit,
                    },
                    {
                        value: 'DISABLED',
                        label: props.labels.reminder_disabled,
                    },
                    {
                        value: 'OVERRIDE',
                        label: props.labels.reminder_override,
                    },
                ]}
                error={props.errors['inheritance.reminder_mode']}
                onValueChange={(value) =>
                    props.onChange({
                        ...props.value,
                        reminderMode: value as RecurringReminderMode,
                    })
                }
            />
            {props.value.reminderMode === 'OVERRIDE' && (
                <>
                    <SectionHeader
                        title={props.labels.reminders_title}
                        action={
                            <Button
                                type="button"
                                variant="secondary"
                                onClick={() =>
                                    change([
                                        ...rules,
                                        {
                                            key: crypto.randomUUID(),
                                            sourceRuleId: null,
                                            relation: 'BEFORE_DUE',
                                            dayOffset: 0,
                                            enabled: true,
                                        },
                                    ])
                                }
                            >
                                {props.labels.add_reminder}
                            </Button>
                        }
                    />
                    {rules.map((rule, index) => (
                        <Surface key={rule.key} as="div">
                            <Stack gap="lg">
                                <SectionHeader
                                    title={interpolate(props.labels.reminder, {
                                        number: index + 1,
                                    })}
                                    action={
                                        <Button
                                            type="button"
                                            variant="destructive"
                                            onClick={() =>
                                                change(
                                                    rules.filter(
                                                        (_, current) =>
                                                            current !== index,
                                                    ),
                                                )
                                            }
                                        >
                                            {props.labels.remove_reminder}
                                        </Button>
                                    }
                                />
                                <Grid columns={3} gap="lg">
                                    <SelectField
                                        name={`inheritance.reminder_rules.${index}.relation`}
                                        label={props.labels.relation}
                                        value={rule.relation}
                                        options={props.relationOptions}
                                        error={error(props, index, 'relation')}
                                        onValueChange={(value) =>
                                            changeRule(
                                                props,
                                                index,
                                                'relation',
                                                value,
                                            )
                                        }
                                    />
                                    <TextField
                                        label={props.labels.day_offset}
                                        error={error(
                                            props,
                                            index,
                                            'day_offset',
                                        )}
                                        input={{
                                            type: 'number',
                                            min: 0,
                                            max: props.maxDayOffset,
                                            value: rule.dayOffset,
                                            onChange: (event) =>
                                                changeRule(
                                                    props,
                                                    index,
                                                    'dayOffset',
                                                    Number(event.target.value),
                                                ),
                                        }}
                                    />
                                    <CheckboxField
                                        label={props.labels.enabled}
                                        error={error(props, index, 'enabled')}
                                        checkbox={{
                                            checked: rule.enabled,
                                            onCheckedChange: (checked) =>
                                                changeRule(
                                                    props,
                                                    index,
                                                    'enabled',
                                                    checked === true,
                                                ),
                                        }}
                                    />
                                </Grid>
                            </Stack>
                        </Surface>
                    ))}
                </>
            )}
        </Stack>
    );
}

function changeRule(
    props: Props,
    index: number,
    field: 'relation' | 'dayOffset' | 'enabled',
    value: string | number | boolean,
) {
    props.onChange({
        ...props.value,
        reminderRules: props.value.reminderRules.map((rule, current) =>
            current === index ? { ...rule, [field]: value } : rule,
        ) as RecurringReminderRule[],
    });
}

function error(props: Props, index: number, field: string) {
    return props.errors[`inheritance.reminder_rules.${index}.${field}`];
}
