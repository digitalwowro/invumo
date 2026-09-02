import { router } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { FormActions, SaveButton } from '@/components/app/form-actions';
import { TextareaField, TextField } from '@/components/app/form-field';
import { FormSection } from '@/components/app/form-section';
import { Stack } from '@/components/app/layout';
import { ConfirmationDialog } from '@/components/app/responsive-dialog';
import { SystemMessage } from '@/components/app/system-message';
import { UnsavedChangesGuard } from '@/components/app/unsaved-changes-guard';
import { Button } from '@/components/ui/button';
import { EmailTemplatePreview } from '@/features/delivery/components/email-template-preview';
import {
    EmailTemplatePreviewError,
    requestEmailTemplatePreview,
} from '@/features/delivery/lib/company-email-template-preview';
import type {
    CompanyEmailTemplate,
    CompanyEmailTemplateFormData,
    CompanyEmailTemplateLimits,
    CompanyEmailTemplateTranslations,
    RenderedEmailTemplate,
} from '@/types/company-email-template';

type Props = {
    template: CompanyEmailTemplate;
    limits: CompanyEmailTemplateLimits;
    saveUrl: string;
    previewUrl: string;
    labels: CompanyEmailTemplateTranslations;
    cancelLabel: string;
    closeLabel: string;
};

export function CompanyEmailTemplateEditor({
    template,
    limits,
    saveUrl,
    previewUrl,
    labels,
    cancelLabel,
    closeLabel,
}: Props) {
    const initial = formData(template);
    const [data, setData] = useState(initial);
    const [preview, setPreview] = useState<RenderedEmailTemplate>(
        template.preview,
    );
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const [previewing, setPreviewing] = useState(false);
    const [previewFailed, setPreviewFailed] = useState(false);
    const dirty = JSON.stringify(data) !== JSON.stringify(initial);

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.put(saveUrl, data, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onError: setErrors,
        });
    }

    async function refreshPreview() {
        setPreviewing(true);
        setPreviewFailed(false);

        try {
            setPreview(await requestEmailTemplatePreview(previewUrl, data));
            setErrors({});
        } catch (error) {
            if (error instanceof EmailTemplatePreviewError) {
                setErrors(error.errors);
            }

            setPreviewFailed(true);
        } finally {
            setPreviewing(false);
        }
    }

    return (
        <Stack gap="2xl">
            <UnsavedChangesGuard
                active={dirty && !processing}
                message={labels.unsaved_warning}
            />
            {previewFailed && (
                <SystemMessage title={labels.preview_failed} tone="error" />
            )}
            <div className="grid min-w-0 gap-6 xl:grid-cols-2">
                <form className="min-w-0" onSubmit={submit}>
                    <FormSection
                        title={labels.content_title}
                        description={labels.content_description}
                    >
                        <TextField
                            label={labels.fields.subject}
                            description={labels.field_descriptions.subject}
                            error={errors.subject}
                            input={{
                                value: data.subject,
                                maxLength: limits.subject,
                                required: true,
                                onChange: (event) =>
                                    setData({
                                        ...data,
                                        subject: event.target.value,
                                    }),
                            }}
                        />
                        <TextareaField
                            label={labels.fields.body}
                            description={labels.field_descriptions.body}
                            error={errors.body}
                            textarea={{
                                value: data.body,
                                maxLength: limits.body,
                                required: true,
                                rows: 12,
                                onChange: (event) =>
                                    setData({
                                        ...data,
                                        body: event.target.value,
                                    }),
                            }}
                        />
                        <TextField
                            label={labels.fields.button_label}
                            description={labels.field_descriptions.button_label}
                            error={errors.button_label}
                            input={{
                                value: data.button_label,
                                maxLength: limits.buttonLabel,
                                required: true,
                                onChange: (event) =>
                                    setData({
                                        ...data,
                                        button_label: event.target.value,
                                    }),
                            }}
                        />
                        <TextareaField
                            label={labels.fields.signature}
                            description={labels.field_descriptions.signature}
                            error={errors.signature}
                            textarea={{
                                value: data.signature,
                                maxLength: limits.signature,
                                rows: 4,
                                onChange: (event) =>
                                    setData({
                                        ...data,
                                        signature: event.target.value,
                                    }),
                            }}
                        />
                        <FormActions separated>
                            {template.override && (
                                <ConfirmationDialog
                                    triggerLabel={labels.reset}
                                    title={labels.reset_title}
                                    description={labels.reset_description}
                                    confirmLabel={labels.confirm_reset}
                                    cancelLabel={cancelLabel}
                                    closeLabel={closeLabel}
                                    tone="default"
                                    onConfirm={() =>
                                        router.delete(template.resetUrl, {
                                            preserveScroll: true,
                                        })
                                    }
                                />
                            )}
                            <Button
                                type="button"
                                variant="secondary"
                                disabled={previewing}
                                onClick={refreshPreview}
                            >
                                {labels.preview}
                            </Button>
                            <SaveButton processing={processing} dirty={dirty}>
                                {labels.save}
                            </SaveButton>
                        </FormActions>
                    </FormSection>
                </form>
                <EmailTemplatePreview
                    preview={preview}
                    title={labels.preview_title}
                    description={labels.preview_description}
                    override={template.override}
                    overrideLabel={labels.company_override}
                    systemLabel={labels.system_default}
                />
            </div>
        </Stack>
    );
}

function formData(
    template: CompanyEmailTemplate,
): CompanyEmailTemplateFormData {
    return {
        event_type: template.eventType,
        language_code: template.languageCode,
        subject: template.subject,
        body: template.body,
        button_label: template.buttonLabel,
        signature: template.signature ?? '',
    };
}
