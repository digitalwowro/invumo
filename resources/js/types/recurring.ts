import type {
    DocumentCatalogFormOptions,
    DocumentCustomerFormOptions,
    DocumentCustomerSelection,
    DocumentEditorTranslations,
    DocumentLineDraft,
    DocumentLineLimits,
    DocumentProductDefaults,
    DocumentSourceUrls,
} from '@/types/document';

export type RecurringTemplateState =
    'DRAFT' | 'ACTIVE' | 'PAUSED' | 'COMPLETED';

export type RecurringTemplateRow = {
    id: string;
    internalName: string;
    customerName: string;
    customerReference: string | null;
    state: RecurringTemplateState;
    updatedAt: string;
    editUrl: string;
    deleteUrl: string;
    canDelete: boolean;
};

export type RecurringTemplateCursorPage = {
    items: RecurringTemplateRow[];
    previousUrl: string | null;
    nextUrl: string | null;
};

export type RecurringTemplateFilters = {
    q: string;
    sort: 'name_asc' | 'name_desc' | 'recent';
    perPage: number;
};

export type RecurringTemplateDraft = {
    id: string;
    internalName: string;
    customerReference: string | null;
    state: RecurringTemplateState;
    editVersion: number;
    customer: DocumentCustomerSelection;
    currencyCode: string | null;
    currencyPrecision: number | null;
    lines: DocumentLineDraft[];
};

export type RecurringTemplateLimits = DocumentLineLimits & {
    internalName: number;
    customerReference: number;
    termsAndConditions: number;
    notes: number;
    maxDayOffset: number;
};

export type RecurringValueMode = 'INHERIT' | 'EXPLICIT';
export type RecurringReminderMode = 'INHERIT_COMPANY' | 'DISABLED' | 'OVERRIDE';

export type RecurringRecipient = {
    key: string;
    role: 'TO' | 'CC' | 'BCC';
    contactId: string | null;
    name: string;
    email: string;
};

export type RecurringReminderRule = {
    key: string;
    sourceRuleId: string | null;
    relation: 'BEFORE_DUE' | 'AFTER_DUE';
    dayOffset: number;
    enabled: boolean;
};

export type RecurringInheritance = {
    identityMode: RecurringValueMode;
    identity: Record<string, string | null>;
    recipientsMode: RecurringValueMode;
    recipients: RecurringRecipient[];
    currencyMode: RecurringValueMode;
    currencyCode: string | null;
    currencyPrecision: number | null;
    languageMode: RecurringValueMode;
    documentLanguage: string | null;
    paymentTermMode: RecurringValueMode;
    paymentTermDays: number | null;
    taxMode: RecurringValueMode;
    taxPresetId: string | null;
    deliveryMode: RecurringValueMode;
    emailAttachmentMode: 'SECURE_LINK_ONLY' | 'ATTACH_PDF';
    termsMode: RecurringValueMode;
    termsAndConditions: string | null;
    notesMode: RecurringValueMode;
    notes: string | null;
    bankMode: RecurringValueMode;
    bankAccountId: string | null;
    reminderMode: RecurringReminderMode;
    reminderRules: RecurringReminderRule[];
};

export type RecurringInheritanceProps = {
    inheritance: RecurringInheritance;
    currencyOptions: Array<{ value: string; label: string; precision: number }>;
    languageOptions: Array<{ value: string; label: string }>;
    taxPresetOptions: Array<{ value: string; label: string }>;
    bankAccountOptions: Array<{ value: string; label: string }>;
    reminderRelationOptions: Array<{ value: string; label: string }>;
};

export type RecurringSourceProps = {
    sourceUrls: DocumentSourceUrls;
    inlineCustomerStoreUrl: string;
    inlineProductStoreUrl: string;
    inlineCreatedCustomer: DocumentCustomerSelection | null;
    inlineCreatedProduct: DocumentProductDefaults | null;
    sourceAbilities: { createCustomer: boolean; createProduct: boolean };
    customerForm: DocumentCustomerFormOptions;
    catalogForm: DocumentCatalogFormOptions;
};

export type RecurringTranslations = {
    index: {
        head_title: string;
        title: string;
        description: string;
        create: string;
        search_label: string;
        search_placeholder: string;
        sort_label: string;
        per_page_label: string;
        clear: string;
        previous: string;
        next: string;
        not_available: string;
        loading: string;
        empty_title: string;
        empty_description: string;
        no_results_title: string;
        no_results_description: string;
        error_title: string;
        error_description: string;
        columns: Record<
            | 'template'
            | 'customer'
            | 'reference'
            | 'state'
            | 'updated'
            | 'actions'
            | 'open',
            string
        >;
        sort_options: Record<RecurringTemplateFilters['sort'], string>;
        states: Record<RecurringTemplateState, string>;
    };
    create: {
        head_title: string;
        title: string;
        description: string;
        section_title: string;
        section_description: string;
        internal_name: string;
        internal_name_description: string;
        submit: string;
    };
    editor: DocumentEditorTranslations & {
        identity_section: string;
        identity_description: string;
        internal_name: string;
        internal_name_description: string;
        customer_reference: string;
        customer_reference_description: string;
        inheritance: RecurringInheritanceTranslations;
    };
    deletion: {
        delete: string;
        title: string;
        description: string;
        confirm: string;
    };
};

export type RecurringInheritanceTranslations = {
    inherit: string;
    explicit: string;
    none: string;
    identity_title: string;
    identity_description: string;
    identity_mode: string;
    contact_name: string;
    contact_position_title: string;
    values_title: string;
    values_description: string;
    currency_mode: string;
    language_mode: string;
    payment_term_mode: string;
    payment_term_days: string;
    tax_mode: string;
    delivery_mode: string;
    recipients_title: string;
    recipients_description: string;
    recipients_mode: string;
    add_recipient: string;
    remove_recipient: string;
    recipient: string;
    role: string;
    name: string;
    email: string;
    content_title: string;
    content_description: string;
    terms_mode: string;
    notes_mode: string;
    bank_mode: string;
    reminders_title: string;
    reminders_description: string;
    reminder_mode: string;
    reminder_inherit: string;
    reminder_disabled: string;
    reminder_override: string;
    add_reminder: string;
    remove_reminder: string;
    reminder: string;
    relation: string;
    day_offset: string;
    enabled: string;
};
