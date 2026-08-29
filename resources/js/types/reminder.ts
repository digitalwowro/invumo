export type ReminderRelation = 'BEFORE_DUE' | 'AFTER_DUE';
export type ReminderStatus =
    | 'PENDING'
    | 'CLAIMED'
    | 'SENT'
    | 'SKIPPED'
    | 'SUPERSEDED'
    | 'SUPPRESSED'
    | 'FAILED';

export type ReminderRule = {
    id: string | null;
    relation: ReminderRelation;
    dayOffset: number;
    enabled: boolean;
};

export type ReminderRelationOption = {
    value: ReminderRelation;
    label: string;
};

export type ReminderRuleLabels = {
    rules_title: string;
    rules_description: string;
    relation: string;
    day_offset: string;
    enabled: string;
    add: string;
    remove: string;
    save: string;
    unsaved_warning: string;
    empty: string;
};

export type CompanyReminderTranslations = ReminderRuleLabels & {
    head_title: string;
    title: string;
    description: string;
    history_note: string;
    failures_title: string;
    failures_description: string;
    failures_empty_title: string;
    failures_empty_description: string;
    failure_status: string;
    failure_columns: {
        invoice: string;
        scheduled: string;
        reason: string;
        attempts: string;
        status: string;
        actions: string;
    };
    open_invoice: string;
    retry: string;
    retry_title: string;
    retry_warning: string;
    retry_confirm: string;
    retry_cancel: string;
};

export type CompanyReminderFailure = {
    id: string;
    invoiceNumber: string;
    scheduledAt: string;
    failure: string;
    attemptCount: number;
    invoiceUrl: string;
    retryUrl: string;
};

export type InvoiceReminder = {
    rules: ReminderRule[];
    history: {
        id: string;
        relation: ReminderRelation;
        dayOffset: number;
        scheduledAt: string;
        status: ReminderStatus;
        attemptCount: number;
        failure: string | null;
        retryUrl: string | null;
    }[];
    saveUrl: string | null;
    limits: { rules: number; dayOffset: number };
};

export type InvoiceReminderTranslations = ReminderRuleLabels & {
    title: string;
    description: string;
    history_title: string;
    history_empty: string;
    scheduled_for: string;
    attempts: string;
    retry: string;
    retry_title: string;
    retry_warning: string;
    retry_confirm: string;
    retry_cancel: string;
    statuses: Record<ReminderStatus, string>;
    relations: Record<ReminderRelation, string>;
};
