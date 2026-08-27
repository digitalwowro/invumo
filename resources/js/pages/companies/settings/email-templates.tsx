import { Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { FormSection } from '@/components/app/form-section';
import { Grid, Stack } from '@/components/app/layout';
import { SectionHeader } from '@/components/app/section-header';
import { SelectField } from '@/components/app/select-field';
import { SystemMessage } from '@/components/app/system-message';
import { CompanyEmailTemplateEditor } from '@/features/delivery/components/company-email-template-editor';
import { EmailTemplatePlaceholders } from '@/features/delivery/components/email-template-placeholders';
import type { CompaniesUiTranslations } from '@/types/company';
import type {
    CompanyEmailTemplatePageProps,
    EmailTemplateEvent,
} from '@/types/company-email-template';

type Props = CompanyEmailTemplatePageProps & {
    status?: string;
    translations: CompaniesUiTranslations;
};

export default function CompanyEmailTemplates({
    templates,
    eventOptions,
    languageOptions,
    placeholderOptions,
    limits,
    saveUrl,
    previewUrl,
    status,
    translations,
}: Props) {
    const { i18n } = usePage().props;
    const labels = translations.settings.email_templates;
    const [eventType, setEventType] = useState<EmailTemplateEvent>(
        templates[0].eventType,
    );
    const [languageCode, setLanguageCode] = useState(templates[0].languageCode);
    const template = templates.find(
        (candidate) =>
            candidate.eventType === eventType &&
            candidate.languageCode === languageCode,
    );
    const placeholders =
        placeholderOptions.find((option) => option.eventType === eventType)
            ?.items ?? [];

    if (!template) {
        return null;
    }

    return (
        <>
            <Head title={labels.head_title} />
            <Stack gap="2xl">
                <SectionHeader
                    title={labels.title}
                    description={labels.description}
                />
                {status && <SystemMessage title={status} tone="money" />}
                <FormSection
                    title={labels.selection_title}
                    description={labels.selection_description}
                >
                    <Grid columns={2} gap="lg">
                        <SelectField
                            name="event_type"
                            label={labels.fields.event_type}
                            placeholder={labels.event_placeholder}
                            value={eventType}
                            onValueChange={(value) =>
                                setEventType(value as EmailTemplateEvent)
                            }
                            options={eventOptions}
                        />
                        <SelectField
                            name="language_code"
                            label={labels.fields.language_code}
                            placeholder={labels.language_placeholder}
                            value={languageCode}
                            onValueChange={setLanguageCode}
                            options={languageOptions}
                        />
                    </Grid>
                </FormSection>
                <CompanyEmailTemplateEditor
                    key={JSON.stringify([
                        template.eventType,
                        template.languageCode,
                        template.override,
                        template.subject,
                        template.body,
                        template.buttonLabel,
                        template.signature,
                    ])}
                    template={template}
                    limits={limits}
                    saveUrl={saveUrl}
                    previewUrl={previewUrl}
                    labels={labels}
                    cancelLabel={i18n.common.actions.cancel}
                    closeLabel={i18n.common.accessibility.close_navigation}
                />
                <EmailTemplatePlaceholders
                    title={labels.placeholders_title}
                    description={labels.placeholders_description}
                    items={placeholders}
                />
            </Stack>
        </>
    );
}
