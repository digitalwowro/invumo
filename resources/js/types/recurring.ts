import type {
    DocumentCatalogFormOptions,
    DocumentCustomerFormOptions,
    DocumentCustomerSelection,
    DocumentLineDraft,
    DocumentLineLimits,
    DocumentProductDefaults,
    DocumentSourceUrls,
} from '@/types/document';

export type RecurringTemplateState =
    'DRAFT' | 'ACTIVE' | 'PAUSED' | 'COMPLETED';

export type RecurrenceKind =
    'WEEKLY' | 'MONTHLY' | 'QUARTERLY' | 'YEARLY' | 'CUSTOM';
export type RecurringIntervalUnit = 'DAY' | 'WEEK' | 'MONTH' | 'YEAR';
export type RecurringRunOutcome = 'SUCCEEDED' | 'FAILED' | 'SKIPPED';

export type RecurringSchedule = {
    recurrenceKind: RecurrenceKind | null;
    customIntervalCount: number | null;
    customIntervalUnit: RecurringIntervalUnit | null;
    startDate: string | null;
    endDate: string | null;
    maximumOccurrenceCount: number | null;
    nextOccurrenceDate: string | null;
    scheduleTimezone: string | null;
    scheduleLocalTime: string | null;
    nextRunAt: string | null;
};

export type RecurringTemplateRow = {
    id: string;
    internalName: string;
    customerName: string;
    customerReference: string | null;
    state: RecurringTemplateState;
    nextRunAt: string | null;
    lastRunOutcome: RecurringRunOutcome | null;
    lastInvoiceUrl: string | null;
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
    schedule: RecurringSchedule;
    execution: RecurringExecution;
    customer: DocumentCustomerSelection;
    currencyCode: string | null;
    currencyPrecision: number | null;
    lines: DocumentLineDraft[];
};

export type RecurringExecution = {
    successfulOccurrenceCount: number;
    lastRunOutcome: RecurringRunOutcome | null;
    lastRunStartedAt: string | null;
    lastRunCompletedAt: string | null;
    lastFailure: string | null;
    lastInvoiceUrl: string | null;
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
