import type { DocumentEditorTranslations } from '@/types/document';
import type {
    RecurrenceKind,
    RecurringIntervalUnit,
    RecurringRunOutcome,
    RecurringTemplateFilters,
    RecurringTemplateState,
} from '@/types/recurring';

export type RecurringTranslations = {
    index: {
        head_title: string;
        title: string;
        description: string;
        create: string;
        search_label: string;
        search_placeholder: string;
        sort_label: string;
        outcome_filter_label: string;
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
            | 'next_run'
            | 'outcome'
            | 'automation'
            | 'updated'
            | 'actions'
            | 'open'
            | 'open_invoice',
            string
        >;
        sort_options: Record<RecurringTemplateFilters['sort'], string>;
        outcome_filter_options: Record<
            RecurringTemplateFilters['outcome'],
            string
        >;
        states: Record<RecurringTemplateState, string>;
        outcomes: Record<RecurringRunOutcome, string>;
        automation: Record<'enabled' | 'disabled' | 'review_required', string>;
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
        content_locked: string;
        inheritance: RecurringInheritanceTranslations;
    };
    schedule: {
        title: string;
        description: string;
        recurrence_kind: string;
        custom_interval_count: string;
        custom_interval_unit: string;
        start_date: string;
        end_date: string;
        maximum_occurrence_count: string;
        save: string;
        active_confirmation: string;
        next_run_title: string;
        next_run_empty: string;
        kinds: Record<RecurrenceKind, string>;
        units: Record<RecurringIntervalUnit, string>;
    };
    automation: {
        title: string;
        description: string;
        enabled: string;
        enabled_description: string;
        confirmation: string;
        save: string;
        review_title: string;
        review_description: string;
    };
    lifecycle: {
        activate: string;
        pause: string;
        resume: string;
        complete: string;
        duplicate: string;
        cancel: string;
        retry: string;
        title: Record<LifecycleAction, string>;
        description: Record<LifecycleAction, string>;
        confirm: Record<LifecycleAction, string>;
    };
    execution: {
        title: string;
        description: string;
        successful_count: string;
        last_outcome: string;
        last_started: string;
        last_completed: string;
        last_failure: string;
        not_run: string;
        open_invoice: string;
    };
    deletion: {
        delete: string;
        title: string;
        description: string;
        high_risk_description: string;
        dependency_title: string;
        dependency_description: string;
        confirm: string;
    };
};

type LifecycleAction =
    'activate' | 'pause' | 'resume' | 'complete' | 'duplicate' | 'retry';

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
