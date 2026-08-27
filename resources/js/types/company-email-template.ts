export type EmailTemplateEvent =
    'QUOTE_SENT' | 'INVOICE_SENT' | 'PAYMENT_REMINDER' | 'PAYMENT_RECEIVED';

export type RenderedEmailTemplate = {
    subject: string;
    body: string;
    buttonLabel: string;
    signature: string | null;
    buttonUrl: string;
};

export type CompanyEmailTemplate = {
    eventType: EmailTemplateEvent;
    languageCode: string;
    subject: string;
    body: string;
    buttonLabel: string;
    signature: string | null;
    override: boolean;
    resetUrl: string;
    preview: RenderedEmailTemplate;
};

export type CompanyEmailTemplateFormData = {
    event_type: EmailTemplateEvent;
    language_code: string;
    subject: string;
    body: string;
    button_label: string;
    signature: string;
};

export type CompanyEmailTemplateLimits = {
    subject: number;
    body: number;
    buttonLabel: number;
    signature: number;
};

export type EmailTemplatePlaceholderOption = {
    eventType: EmailTemplateEvent;
    items: { token: string; label: string }[];
};

export type CompanyEmailTemplatePageProps = {
    templates: CompanyEmailTemplate[];
    eventOptions: { value: string; label: string }[];
    languageOptions: { value: string; label: string }[];
    placeholderOptions: EmailTemplatePlaceholderOption[];
    limits: CompanyEmailTemplateLimits;
    saveUrl: string;
    previewUrl: string;
};

export type CompanyEmailTemplateTranslations = {
    head_title: string;
    title: string;
    description: string;
    selection_title: string;
    selection_description: string;
    content_title: string;
    content_description: string;
    preview_title: string;
    preview_description: string;
    placeholders_title: string;
    placeholders_description: string;
    system_default: string;
    company_override: string;
    event_placeholder: string;
    language_placeholder: string;
    save: string;
    preview: string;
    reset: string;
    reset_title: string;
    reset_description: string;
    confirm_reset: string;
    preview_failed: string;
    unsaved_warning: string;
    fields: Record<string, string>;
    field_descriptions: Record<string, string>;
    events: Record<EmailTemplateEvent, string>;
    placeholders: Record<string, string>;
    feedback: { saved: string; reset: string };
    errors: { invalid_template: string };
};
