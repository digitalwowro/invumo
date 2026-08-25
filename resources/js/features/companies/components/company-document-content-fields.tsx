import { FormActions, SubmitButton } from '@/components/app/form-actions';
import { TextareaField } from '@/components/app/form-field';
import { FormSection } from '@/components/app/form-section';
import { Grid, Stack } from '@/components/app/layout';
import type {
    CompanyDocumentDefaults,
    CompanyDocumentDefaultsTranslations,
} from '@/types/company-document-defaults';

type Props = {
    defaults: CompanyDocumentDefaults;
    errors: Record<string, string>;
    labels: CompanyDocumentDefaultsTranslations;
    processing: boolean;
};

export function CompanyDocumentContentFields({
    defaults,
    errors,
    labels,
    processing,
}: Props) {
    return (
        <FormSection
            title={labels.content_title}
            description={labels.content_description}
            actions={
                <FormActions>
                    <SubmitButton processing={processing}>
                        {labels.save}
                    </SubmitButton>
                </FormActions>
            }
        >
            <Stack gap="lg">
                <TextareaField
                    id="default_terms_and_conditions"
                    label={labels.fields.default_terms_and_conditions}
                    description={
                        labels.field_descriptions.default_terms_and_conditions
                    }
                    error={errors.default_terms_and_conditions}
                    textarea={{
                        name: 'default_terms_and_conditions',
                        defaultValue: defaults.termsAndConditions ?? undefined,
                        rows: 6,
                    }}
                />
                <Grid columns={2} gap="lg">
                    <TextareaField
                        id="default_quote_notes"
                        label={labels.fields.default_quote_notes}
                        description={
                            labels.field_descriptions.default_quote_notes
                        }
                        error={errors.default_quote_notes}
                        textarea={{
                            name: 'default_quote_notes',
                            defaultValue: defaults.quoteNotes ?? undefined,
                            rows: 5,
                        }}
                    />
                    <TextareaField
                        id="default_invoice_notes"
                        label={labels.fields.default_invoice_notes}
                        description={
                            labels.field_descriptions.default_invoice_notes
                        }
                        error={errors.default_invoice_notes}
                        textarea={{
                            name: 'default_invoice_notes',
                            defaultValue: defaults.invoiceNotes ?? undefined,
                            rows: 5,
                        }}
                    />
                </Grid>
            </Stack>
        </FormSection>
    );
}
