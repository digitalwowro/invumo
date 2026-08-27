import { ChoiceField } from '@/components/app/choice-field';
import { CheckboxField, TextField } from '@/components/app/form-field';
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
            <CheckboxField
                id="public_links_enabled_by_default"
                label={labels.fields.public_links_enabled_by_default}
                description={
                    labels.field_descriptions.public_links_enabled_by_default
                }
                error={errors.public_links_enabled_by_default}
                checkbox={{
                    name: 'public_links_enabled_by_default',
                    value: '1',
                    defaultChecked: defaults.publicLinksEnabled,
                }}
            />
            <TextField
                id="default_public_link_validity_days"
                label={labels.fields.default_public_link_validity_days}
                description={
                    labels.field_descriptions.default_public_link_validity_days
                }
                error={errors.default_public_link_validity_days}
                input={{
                    name: 'default_public_link_validity_days',
                    type: 'number',
                    min: 1,
                    max: 3650,
                    defaultValue: defaults.publicLinkValidityDays,
                    required: true,
                }}
            />
        </FormSection>
    );
}
