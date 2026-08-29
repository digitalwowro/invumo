import { useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { FormActions, SubmitButton } from '@/components/app/form-actions';
import { CheckboxField, TextField } from '@/components/app/form-field';
import { FormSection } from '@/components/app/form-section';
import { Grid, Stack } from '@/components/app/layout';
import { SelectField } from '@/components/app/select-field';
import { SystemMessage } from '@/components/app/system-message';
import type {
    RecurrenceKind,
    RecurringIntervalUnit,
    RecurringTemplateDraft,
    RecurringTranslations,
} from '@/types/recurring';

type Props = {
    template: RecurringTemplateDraft;
    updateUrl: string;
    canManage: boolean;
    labels: RecurringTranslations['schedule'];
};

export function RecurringTemplateScheduleForm(props: Props) {
    const { i18n } = usePage().props;
    const schedule = props.template.schedule;
    const form = useForm({
        edit_version: props.template.editVersion,
        recurrence_kind: schedule.recurrenceKind ?? 'MONTHLY',
        custom_interval_count: schedule.customIntervalCount,
        custom_interval_unit: schedule.customIntervalUnit ?? 'MONTH',
        start_date: schedule.startDate ?? '',
        end_date: schedule.endDate ?? '',
        maximum_occurrence_count: schedule.maximumOccurrenceCount,
        confirmed: false,
    });
    const custom = form.data.recurrence_kind === 'CUSTOM';
    const needsConfirmation = props.template.state !== 'DRAFT';
    const errors = form.errors as Record<string, string>;

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            custom_interval_count:
                data.recurrence_kind === 'CUSTOM'
                    ? data.custom_interval_count
                    : null,
            custom_interval_unit:
                data.recurrence_kind === 'CUSTOM'
                    ? data.custom_interval_unit
                    : null,
        }));
        form.patch(props.updateUrl, { preserveScroll: true });
    };

    return (
        <FormSection
            title={props.labels.title}
            description={props.labels.description}
        >
            <Stack gap="lg">
                {schedule.nextRunAt ? (
                    <SystemMessage
                        title={props.labels.next_run_title}
                        description={new Intl.DateTimeFormat(i18n.locale, {
                            dateStyle: 'long',
                            timeStyle: 'short',
                            timeZone: schedule.scheduleTimezone ?? undefined,
                        }).format(new Date(schedule.nextRunAt))}
                        tone="info"
                    />
                ) : (
                    <SystemMessage
                        title={props.labels.next_run_title}
                        description={props.labels.next_run_empty}
                        tone="info"
                    />
                )}
                <form onSubmit={submit}>
                    <Stack gap="lg">
                        <Grid columns={2} gap="lg">
                            <SelectField
                                name="recurrence_kind"
                                label={props.labels.recurrence_kind}
                                value={form.data.recurrence_kind}
                                disabled={!props.canManage}
                                error={form.errors.recurrence_kind}
                                options={Object.entries(props.labels.kinds).map(
                                    ([value, label]) => ({ value, label }),
                                )}
                                onValueChange={(value) =>
                                    form.setData(
                                        'recurrence_kind',
                                        value as RecurrenceKind,
                                    )
                                }
                            />
                            <TextField
                                label={props.labels.start_date}
                                error={form.errors.start_date}
                                input={{
                                    type: 'date',
                                    value: form.data.start_date,
                                    disabled: !props.canManage,
                                    onChange: (event) =>
                                        form.setData(
                                            'start_date',
                                            event.target.value,
                                        ),
                                }}
                            />
                            {custom && (
                                <>
                                    <TextField
                                        label={
                                            props.labels.custom_interval_count
                                        }
                                        error={
                                            form.errors.custom_interval_count
                                        }
                                        input={{
                                            type: 'number',
                                            min: 1,
                                            max: 10000,
                                            value:
                                                form.data
                                                    .custom_interval_count ??
                                                '',
                                            disabled: !props.canManage,
                                            onChange: (event) =>
                                                form.setData(
                                                    'custom_interval_count',
                                                    event.target.value === ''
                                                        ? null
                                                        : Number(
                                                              event.target
                                                                  .value,
                                                          ),
                                                ),
                                        }}
                                    />
                                    <SelectField
                                        name="custom_interval_unit"
                                        label={
                                            props.labels.custom_interval_unit
                                        }
                                        value={form.data.custom_interval_unit}
                                        disabled={!props.canManage}
                                        error={form.errors.custom_interval_unit}
                                        options={Object.entries(
                                            props.labels.units,
                                        ).map(([value, label]) => ({
                                            value,
                                            label,
                                        }))}
                                        onValueChange={(value) =>
                                            form.setData(
                                                'custom_interval_unit',
                                                value as RecurringIntervalUnit,
                                            )
                                        }
                                    />
                                </>
                            )}
                            <TextField
                                label={props.labels.end_date}
                                error={form.errors.end_date}
                                input={{
                                    type: 'date',
                                    value: form.data.end_date,
                                    disabled: !props.canManage,
                                    onChange: (event) =>
                                        form.setData(
                                            'end_date',
                                            event.target.value,
                                        ),
                                }}
                            />
                            <TextField
                                label={props.labels.maximum_occurrence_count}
                                error={form.errors.maximum_occurrence_count}
                                input={{
                                    type: 'number',
                                    min: 1,
                                    max: 1000000,
                                    value:
                                        form.data.maximum_occurrence_count ??
                                        '',
                                    disabled: !props.canManage,
                                    onChange: (event) =>
                                        form.setData(
                                            'maximum_occurrence_count',
                                            event.target.value === ''
                                                ? null
                                                : Number(event.target.value),
                                        ),
                                }}
                            />
                        </Grid>
                        {needsConfirmation && props.canManage && (
                            <CheckboxField
                                label={props.labels.active_confirmation}
                                error={form.errors.confirmed}
                                checkbox={{
                                    checked: form.data.confirmed,
                                    onCheckedChange: (checked) =>
                                        form.setData(
                                            'confirmed',
                                            checked === true,
                                        ),
                                }}
                            />
                        )}
                        {errors.schedule && (
                            <SystemMessage
                                title={errors.schedule}
                                tone="error"
                            />
                        )}
                        {props.canManage && (
                            <FormActions separated>
                                <SubmitButton processing={form.processing}>
                                    {props.labels.save}
                                </SubmitButton>
                            </FormActions>
                        )}
                    </Stack>
                </form>
            </Stack>
        </FormSection>
    );
}
