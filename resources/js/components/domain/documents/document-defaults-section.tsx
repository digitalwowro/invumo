import { FactStrip } from '@/components/app/fact-strip';
import { TextareaField } from '@/components/app/form-field';
import { FormSection } from '@/components/app/form-section';
import { Grid } from '@/components/app/layout';
import { SelectField } from '@/components/app/select-field';
import { Badge } from '@/components/ui/badge';
import type {
    DocumentCurrencyOption,
    DocumentEditorTranslations,
    DocumentSourceOption,
    DocumentTaxDefault,
} from '@/types/document';

const UNSET = '__UNSET__';

type Props = {
    currencyCode: string | null;
    documentLanguage: string | null;
    bankAccountId: string | null;
    bankAccountLabel: string | null;
    taxDefault: DocumentTaxDefault | null;
    recipientCount: number;
    emailAttachmentMode: 'SECURE_LINK_ONLY' | 'ATTACH_PDF';
    termsAndConditions: string;
    notes: string;
    isCustomized: boolean;
    currencyOptions: DocumentCurrencyOption[];
    languageOptions: DocumentSourceOption[];
    bankAccountOptions: DocumentSourceOption[];
    termsLimit: number;
    notesLimit: number;
    labels: DocumentEditorTranslations;
    errors: Record<string, string>;
    onChange: (field: string, value: string | null) => void;
};

export function DocumentDefaultsSection(props: Props) {
    return (
        <FormSection
            title={props.labels.defaults_section}
            description={props.labels.defaults_description}
            flush
            headerActions={
                <Badge variant={props.isCustomized ? 'quiet' : 'muted'}>
                    {props.isCustomized
                        ? props.labels.provenance_customized
                        : props.labels.provenance_default}
                </Badge>
            }
        >
            <FactStrip
                tone="subtle"
                className="border-b border-divider"
                facts={[
                    {
                        label: props.labels.tax_default,
                        value: props.taxDefault?.name ?? props.labels.no_tax,
                    },
                    {
                        label: props.labels.recipients,
                        value: String(props.recipientCount),
                    },
                    {
                        label: props.labels.delivery,
                        value:
                            props.emailAttachmentMode === 'ATTACH_PDF'
                                ? props.labels.attach_pdf
                                : props.labels.secure_link_only,
                    },
                ]}
            />
            <div className="flex flex-col gap-6 p-5 sm:p-6">
                <Grid columns={3} gap="lg">
                    <SelectField
                        name="currency_code"
                        label={props.labels.currency}
                        error={props.errors.currency_code}
                        value={props.currencyCode ?? UNSET}
                        onValueChange={(value) =>
                            props.onChange(
                                'currencyCode',
                                value === UNSET ? null : value,
                            )
                        }
                        options={[
                            { value: UNSET, label: props.labels.not_available },
                            ...props.currencyOptions,
                        ]}
                    />
                    <SelectField
                        name="document_language"
                        label={props.labels.language}
                        error={props.errors.document_language}
                        value={props.documentLanguage ?? UNSET}
                        onValueChange={(value) =>
                            props.onChange(
                                'documentLanguage',
                                value === UNSET ? null : value,
                            )
                        }
                        options={[
                            { value: UNSET, label: props.labels.not_available },
                            ...props.languageOptions,
                        ]}
                    />
                    <SelectField
                        name="bank_account_id"
                        label={props.labels.bank_account}
                        description={
                            props.bankAccountLabel
                                ? `${props.labels.bank_account}: ${props.bankAccountLabel}`
                                : undefined
                        }
                        error={props.errors.bank_account_id}
                        value={props.bankAccountId ?? UNSET}
                        onValueChange={(value) =>
                            props.onChange(
                                'bankAccountId',
                                value === UNSET ? null : value,
                            )
                        }
                        options={[
                            {
                                value: UNSET,
                                label: props.labels.no_bank_account,
                            },
                            ...props.bankAccountOptions,
                        ]}
                    />
                </Grid>
                <Grid columns={2} gap="lg">
                    <TextareaField
                        label={props.labels.fields.terms_and_conditions}
                        error={props.errors.terms_and_conditions}
                        textarea={{
                            value: props.termsAndConditions,
                            maxLength: props.termsLimit,
                            rows: 4,
                            onChange: (event) =>
                                props.onChange(
                                    'termsAndConditions',
                                    event.target.value,
                                ),
                        }}
                    />
                    <TextareaField
                        label={props.labels.fields.notes}
                        error={props.errors.notes}
                        textarea={{
                            value: props.notes,
                            maxLength: props.notesLimit,
                            rows: 4,
                            onChange: (event) =>
                                props.onChange('notes', event.target.value),
                        }}
                    />
                </Grid>
            </div>
        </FormSection>
    );
}
