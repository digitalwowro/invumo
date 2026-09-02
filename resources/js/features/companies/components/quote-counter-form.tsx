import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { FormActions, SaveButton } from '@/components/app/form-actions';
import {
    CheckboxField,
    TextareaField,
    TextField,
} from '@/components/app/form-field';
import { FormSection } from '@/components/app/form-section';
import { Stack } from '@/components/app/layout';
import { UnsavedChangesGuard } from '@/components/app/unsaved-changes-guard';
import type {
    CompanyNumberSeriesTranslations,
    QuoteNumberCounter,
} from '@/types/company-number-series';

type Props = {
    counter: QuoteNumberCounter;
    labels: CompanyNumberSeriesTranslations;
};

export function QuoteCounterForm({ counter, labels }: Props) {
    const form = useForm({
        next_value: counter.nextValue,
        reason: '',
        confirmed_reuse: false,
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.patch(counter.updateUrl, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset('reason', 'confirmed_reuse');
                form.setDefaults({
                    next_value: form.data.next_value,
                    reason: '',
                    confirmed_reuse: false,
                });
            },
        });
    };

    return (
        <form onSubmit={submit}>
            <UnsavedChangesGuard
                active={form.isDirty && !form.processing}
                message={labels.counter_unsaved_warning}
            />
            <FormSection
                title={labels.counter_title}
                description={labels.counter_description}
                actions={
                    <FormActions>
                        <SaveButton
                            processing={form.processing}
                            dirty={form.isDirty}
                        >
                            {labels.counter_save}
                        </SaveButton>
                    </FormActions>
                }
            >
                <Stack gap="lg">
                    <p className="text-sm text-foreground-muted">
                        {labels.counter_period}: {counter.periodKey}
                    </p>
                    <TextField
                        label={labels.counter_next_value}
                        error={form.errors.next_value}
                        input={{
                            type: 'number',
                            min: 1,
                            step: 1,
                            required: true,
                            value: form.data.next_value,
                            onChange: (event) =>
                                form.setData('next_value', event.target.value),
                        }}
                    />
                    <TextareaField
                        label={labels.counter_reason}
                        error={form.errors.reason}
                        textarea={{
                            required: true,
                            maxLength: 500,
                            rows: 3,
                            value: form.data.reason,
                            onChange: (event) =>
                                form.setData('reason', event.target.value),
                        }}
                    />
                    <CheckboxField
                        label={labels.counter_confirmation}
                        description={labels.counter_confirmation_description}
                        checkbox={{
                            checked: form.data.confirmed_reuse,
                            onCheckedChange: (checked) =>
                                form.setData(
                                    'confirmed_reuse',
                                    checked === true,
                                ),
                        }}
                    />
                </Stack>
            </FormSection>
        </form>
    );
}
