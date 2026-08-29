import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { FormActions, SubmitButton } from '@/components/app/form-actions';
import { CheckboxField } from '@/components/app/form-field';
import { FormSection } from '@/components/app/form-section';
import { Stack } from '@/components/app/layout';
import { SystemMessage } from '@/components/app/system-message';
import type { RecurringTemplateDraft } from '@/types/recurring';
import type { RecurringTranslations } from '@/types/recurring-translations';

type Props = {
    template: RecurringTemplateDraft;
    updateUrl: string;
    canManage: boolean;
    labels: RecurringTranslations['automation'];
};

export function RecurringAutomaticEmailForm(props: Props) {
    const form = useForm({
        edit_version: props.template.editVersion,
        automatic_email_enabled:
            props.template.automation.automaticEmailEnabled,
        confirmed: false,
    });
    const automation = props.template.automation;

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.patch(props.updateUrl, { preserveScroll: true });
    };

    return (
        <FormSection
            title={props.labels.title}
            description={props.labels.description}
        >
            <form onSubmit={submit}>
                <Stack gap="lg">
                    {automation.currencyReviewRequired && (
                        <SystemMessage
                            title={props.labels.review_title}
                            description={props.labels.review_description.replace(
                                ':currency',
                                automation.currencyReviewCurrency ?? '',
                            )}
                            tone="warning"
                        />
                    )}
                    <CheckboxField
                        label={props.labels.enabled}
                        description={props.labels.enabled_description}
                        error={form.errors.automatic_email_enabled}
                        checkbox={{
                            checked: form.data.automatic_email_enabled,
                            disabled: !props.canManage,
                            onCheckedChange: (checked) =>
                                form.setData(
                                    'automatic_email_enabled',
                                    checked === true,
                                ),
                        }}
                    />
                    {props.canManage && (
                        <CheckboxField
                            label={props.labels.confirmation}
                            error={form.errors.confirmed}
                            checkbox={{
                                checked: form.data.confirmed,
                                onCheckedChange: (checked) =>
                                    form.setData('confirmed', checked === true),
                            }}
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
        </FormSection>
    );
}
