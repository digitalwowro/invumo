import { TextField } from '@/components/app/form-field';
import { FormSection } from '@/components/app/form-section';
import { Grid } from '@/components/app/layout';
import { SelectField } from '@/components/app/select-field';
import type { CompanyOption } from '@/types/company';
import type {
    CompanyDocumentDefaults,
    CompanyDocumentDefaultsTranslations,
} from '@/types/company-document-defaults';

type Props = {
    defaults: CompanyDocumentDefaults;
    languageOptions: CompanyOption[];
    errors: Record<string, string>;
    labels: CompanyDocumentDefaultsTranslations;
};

export function CompanyDocumentPolicyFields({
    defaults,
    languageOptions,
    errors,
    labels,
}: Props) {
    return (
        <FormSection
            title={labels.policy_title}
            description={labels.policy_description}
        >
            <Grid columns={3} gap="lg">
                <SelectField
                    id="default_document_language"
                    name="default_document_language"
                    label={labels.fields.default_document_language}
                    description={
                        labels.field_descriptions.default_document_language
                    }
                    error={errors.default_document_language}
                    placeholder={labels.language_placeholder}
                    defaultValue={defaults.documentLanguage ?? undefined}
                    required
                    options={languageOptions}
                />
                <TextField
                    id="default_payment_term_days"
                    label={labels.fields.default_payment_term_days}
                    description={
                        labels.field_descriptions.default_payment_term_days
                    }
                    error={errors.default_payment_term_days}
                    input={{
                        type: 'number',
                        name: 'default_payment_term_days',
                        defaultValue: defaults.paymentTermDays ?? undefined,
                        min: 0,
                        step: 1,
                        inputMode: 'numeric',
                        required: true,
                    }}
                />
                <TextField
                    id="default_quote_validity_days"
                    label={labels.fields.default_quote_validity_days}
                    description={
                        labels.field_descriptions.default_quote_validity_days
                    }
                    error={errors.default_quote_validity_days}
                    input={{
                        type: 'number',
                        name: 'default_quote_validity_days',
                        defaultValue: defaults.quoteValidityDays,
                        min: 0,
                        step: 1,
                        inputMode: 'numeric',
                        required: true,
                    }}
                />
            </Grid>
        </FormSection>
    );
}
