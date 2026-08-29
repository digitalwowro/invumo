import { Form } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { FormActions, SubmitButton } from '@/components/app/form-actions';
import { CheckboxField, TextField } from '@/components/app/form-field';
import { Grid, Stack } from '@/components/app/layout';
import { SelectField } from '@/components/app/select-field';
import { UnsavedChangesGuard } from '@/components/app/unsaved-changes-guard';
import { Button } from '@/components/ui/button';
import { FieldGroup } from '@/components/ui/field';
import type {
    ReminderRelation,
    ReminderRelationOption,
    ReminderRule,
    ReminderRuleLabels,
} from '@/types/reminder';

type Props = {
    rules: ReminderRule[];
    relationOptions: ReminderRelationOption[];
    maxRules: number;
    maxDayOffset: number;
    saveUrl: string;
    editVersion?: number;
    allowRemoval?: boolean;
    labels: ReminderRuleLabels;
};

type EditableReminderRule = Omit<ReminderRule, 'dayOffset'> & {
    dayOffset: number | '';
};

export function ReminderRuleForm({
    rules: initialRules,
    relationOptions,
    maxRules,
    maxDayOffset,
    saveUrl,
    editVersion,
    allowRemoval = false,
    labels,
}: Props) {
    const [rules, setRules] = useState<EditableReminderRule[]>(initialRules);
    const update = (index: number, values: Partial<EditableReminderRule>) =>
        setRules((current) =>
            current.map((rule, position) =>
                position === index ? { ...rule, ...values } : rule,
            ),
        );

    return (
        <Form
            action={saveUrl}
            method="put"
            options={{ preserveScroll: true }}
            setDefaultsOnSuccess
        >
            {({ errors, isDirty, processing }) => (
                <Stack gap="lg">
                    <UnsavedChangesGuard
                        active={isDirty && !processing}
                        message={labels.unsaved_warning}
                    />
                    {editVersion && (
                        <input
                            type="hidden"
                            name="edit_version"
                            value={editVersion}
                        />
                    )}
                    {rules.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            {labels.empty}
                        </p>
                    )}
                    {rules.map((rule, index) => (
                        <div
                            key={rule.id ?? `new-${index}`}
                            className="rounded-lg border border-divider bg-surface-inset p-4"
                        >
                            {rule.id && (
                                <input
                                    type="hidden"
                                    name={`rules[${index}][id]`}
                                    value={rule.id}
                                />
                            )}
                            <input
                                type="hidden"
                                name={`rules[${index}][enabled]`}
                                value={rule.enabled ? '1' : '0'}
                            />
                            <FieldGroup>
                                <Grid columns={3} gap="lg">
                                    <SelectField
                                        name={`rules[${index}][relation]`}
                                        label={labels.relation}
                                        error={
                                            errors[`rules.${index}.relation`]
                                        }
                                        value={rule.relation}
                                        onValueChange={(value) =>
                                            update(index, {
                                                relation:
                                                    value as ReminderRelation,
                                            })
                                        }
                                        options={relationOptions}
                                        required
                                    />
                                    <TextField
                                        label={labels.day_offset}
                                        error={
                                            errors[`rules.${index}.day_offset`]
                                        }
                                        input={{
                                            name: `rules[${index}][day_offset]`,
                                            type: 'number',
                                            min: 0,
                                            max: maxDayOffset,
                                            value: rule.dayOffset,
                                            onChange: (event) =>
                                                update(index, {
                                                    dayOffset:
                                                        event.target.value ===
                                                        ''
                                                            ? ''
                                                            : Number(
                                                                  event.target
                                                                      .value,
                                                              ),
                                                }),
                                            required: true,
                                        }}
                                    />
                                    <div className="flex items-end justify-between gap-3 pb-2">
                                        <CheckboxField
                                            label={labels.enabled}
                                            error={
                                                errors[`rules.${index}.enabled`]
                                            }
                                            checkbox={{
                                                checked: rule.enabled,
                                                onCheckedChange: (checked) =>
                                                    update(index, {
                                                        enabled:
                                                            checked === true,
                                                    }),
                                            }}
                                        />
                                        {allowRemoval && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                aria-label={labels.remove}
                                                onClick={() =>
                                                    setRules((current) =>
                                                        current.filter(
                                                            (_, position) =>
                                                                position !==
                                                                index,
                                                        ),
                                                    )
                                                }
                                            >
                                                <Trash2 aria-hidden="true" />
                                            </Button>
                                        )}
                                    </div>
                                </Grid>
                            </FieldGroup>
                        </div>
                    ))}
                    {errors.rules && (
                        <p className="text-sm text-danger-text">
                            {errors.rules}
                        </p>
                    )}
                    <FormActions align="start">
                        <Button
                            type="button"
                            variant="secondary"
                            disabled={rules.length >= maxRules}
                            onClick={() =>
                                setRules((current) => [
                                    ...current,
                                    {
                                        id: null,
                                        relation: 'BEFORE_DUE',
                                        dayOffset: 0,
                                        enabled: true,
                                    },
                                ])
                            }
                        >
                            <Plus data-icon="inline-start" aria-hidden="true" />
                            {labels.add}
                        </Button>
                        <SubmitButton processing={processing}>
                            {labels.save}
                        </SubmitButton>
                    </FormActions>
                </Stack>
            )}
        </Form>
    );
}
