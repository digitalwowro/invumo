export type AuditActorType =
    | 'USER'
    | 'PUBLIC_CUSTOMER'
    | 'PROVIDER_WEBHOOK'
    | 'SCHEDULED_JOB'
    | 'SYSTEM';

export type AuditValue = Record<string, unknown>;

export type CompanyAuditRow = {
    id: string;
    actorType: AuditActorType;
    actorName: string | null;
    actorReference: string | null;
    impersonatorName: string | null;
    action: string;
    targetType: string;
    targetId: string;
    occurredAt: string;
    reason: string | null;
    before: AuditValue | null;
    after: AuditValue | null;
};

export type CompanyAuditCursorPage = {
    items: CompanyAuditRow[];
    previousUrl: string | null;
    nextUrl: string | null;
};

export type CompanyAuditFilters = {
    q: string;
    dateFrom: string;
    dateTo: string;
    actorType: 'all' | AuditActorType;
    targetType: string;
    sort: 'newest' | 'oldest';
    perPage: number;
};

export type CompanyAuditTranslations = {
    head_title: string;
    title: string;
    description: string;
    search_label: string;
    search_placeholder: string;
    actor_type_label: string;
    target_type_label: string;
    all_targets: string;
    date_from: string;
    date_to: string;
    sort_label: string;
    per_page_label: string;
    clear: string;
    previous: string;
    next: string;
    not_available: string;
    target_context: string;
    original_operator: string;
    reason: string;
    changes: string;
    changes_title: string;
    changes_description: string;
    before: string;
    after: string;
    empty_title: string;
    empty_description: string;
    no_results_title: string;
    no_results_description: string;
    actor_types: Record<'all' | AuditActorType, string>;
    target_types: Record<string, string>;
    actions: Record<string, string>;
    sort_options: Record<CompanyAuditFilters['sort'], string>;
};
