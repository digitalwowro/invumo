import { TextareaField } from '@/components/app/form-field';
import { FormSection } from '@/components/app/form-section';
import { Grid } from '@/components/app/layout';
import { SelectField } from '@/components/app/select-field';
import type {
    QuoteCurrencyOption,
    QuoteCustomerSelection,
    QuoteSourceOption,
    QuoteTranslations,
} from '@/types/quote';

const UNSET = '__UNSET__';

type Props = {
    customer: QuoteCustomerSelection;
    currencyCode: string | null;
    documentLanguage: string | null;
    bankAccountId: string | null;
    bankAccountLabel: string | null;
    termsAndConditions: string;
    notes: string;
    currencyOptions: QuoteCurrencyOption[];
    languageOptions: QuoteSourceOption[];
    bankAccountOptions: QuoteSourceOption[];
    termsLimit: number;
    notesLimit: number;
    labels: QuoteTranslations['edit'];
    errors: Record<string, string>;
    onChange: (field: string, value: string | null) => void;
};

export function QuoteDefaultsSection(props: Props) {
    const delivery =
        props.customer.emailAttachmentMode === 'ATTACH_PDF'
            ? props.labels.attach_pdf
            : props.labels.secure_link_only;

    return (
        <FormSection
            title={props.labels.defaults_section}
            description={props.labels.defaults_description}
        >
            <dl className="grid gap-4 rounded-lg border border-border p-4 md:grid-cols-2 xl:grid-cols-4">
                <Summary
                    label={props.labels.select_customer}
                    value={
                        props.customer.displayName ?? props.labels.no_customer
                    }
                />
                <Summary
                    label={props.labels.tax_default}
                    value={
                        props.customer.taxDefault?.name ?? props.labels.no_tax
                    }
                />
                <Summary
                    label={props.labels.recipients}
                    value={String(props.customer.recipientCount)}
                />
                <Summary label={props.labels.delivery} value={delivery} />
            </dl>
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
                    error={props.errors.bank_account_id}
                    value={props.bankAccountId ?? UNSET}
                    onValueChange={(value) =>
                        props.onChange(
                            'bankAccountId',
                            value === UNSET ? null : value,
                        )
                    }
                    options={[
                        { value: UNSET, label: props.labels.no_bank_account },
                        ...props.bankAccountOptions,
                    ]}
                />
            </Grid>
            {props.bankAccountLabel && (
                <p className="text-sm text-foreground-muted">
                    {props.labels.bank_account}:{' '}
                    <span className="font-medium text-foreground">
                        {props.bankAccountLabel}
                    </span>
                </p>
            )}
            <TextareaField
                label={props.labels.fields.terms_and_conditions}
                error={props.errors.terms_and_conditions}
                textarea={{
                    value: props.termsAndConditions,
                    maxLength: props.termsLimit,
                    rows: 5,
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
        </FormSection>
    );
}

function Summary({ label, value }: { label: string; value: string }) {
    return (
        <div className="min-w-0">
            <dt className="text-sm text-foreground-muted">{label}</dt>
            <dd className="truncate font-medium">{value}</dd>
        </div>
    );
}
