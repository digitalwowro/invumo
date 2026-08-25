export type CompanyDocumentDefaults = {
    documentLanguage: string | null;
    paymentTermDays: string | null;
    quoteValidityDays: string;
    termsAndConditions: string | null;
    quoteNotes: string | null;
    invoiceNotes: string | null;
};

export type CompanyDocumentLimits = {
    maxDayOffset: number;
    termsAndConditionsCharacters: number;
    notesCharacters: number;
};

export type CompanyDocumentDefaultsTranslations = {
    head_title: string;
    title: string;
    description: string;
    policy_title: string;
    policy_description: string;
    content_title: string;
    content_description: string;
    language_placeholder: string;
    save: string;
    unsaved_warning: string;
    fields: {
        default_document_language: string;
        default_payment_term_days: string;
        default_quote_validity_days: string;
        default_terms_and_conditions: string;
        default_quote_notes: string;
        default_invoice_notes: string;
    };
    field_descriptions: {
        default_document_language: string;
        default_payment_term_days: string;
        default_quote_validity_days: string;
        default_terms_and_conditions: string;
        default_quote_notes: string;
        default_invoice_notes: string;
    };
    language_options: Record<'en' | 'ro', string>;
    feedback: { saved: string };
};
