import { ChoiceField } from '@/components/app/choice-field';
import { FormSection } from '@/components/app/form-section';
import type { CompanyOption } from '@/types/company';
import type {
    CompanyDocumentDefaults,
    CompanyDocumentDefaultsTranslations,
} from '@/types/company-document-defaults';

type Props = {
    defaults: CompanyDocumentDefaults;
    attachmentModeOptions: CompanyOption[];
    errors: Record<string, string>;
    labels: CompanyDocumentDefaultsTranslations;
};

export function CompanyDocumentDeliveryFields({
    defaults,
    attachmentModeOptions,
    errors,
    labels,
}: Props) {
    return (
        <FormSection
            title={labels.delivery_title}
            description={labels.delivery_description}
        >
            <ChoiceField
                id="default_email_attachment_mode"
                name="default_email_attachment_mode"
                label={labels.fields.default_email_attachment_mode}
                description={
                    labels.field_descriptions.default_email_attachment_mode
                }
                error={errors.default_email_attachment_mode}
                defaultValue={defaults.emailAttachmentMode}
                required
                options={attachmentModeOptions}
            />
        </FormSection>
    );
}
